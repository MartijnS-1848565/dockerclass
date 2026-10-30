#!/bin/sh
# Everything for the Kubernetes chapter, run inside the k8s tools container:
#   docker compose run --rm k8s up | status | test | kubectl ... | shell | down
set -eu

CLUSTER=lab
IMAGE=whoami-lab:1.0
# The lab's compose network (see compose.yaml). The proxy reaches the cluster over it.
FRONTEND=lab-frontend

# Let this (short-lived) container talk to the cluster's API server:
# join the cluster's Docker network and point kubectl at the server node by name.
connect() {
    if ! k3d cluster get "$CLUSTER" >/dev/null 2>&1; then
        echo "No cluster yet. Run: docker compose run --rm k8s up" >&2
        exit 1
    fi
    docker network connect "k3d-$CLUSTER" "$(hostname)" 2>/dev/null || true
    mkdir -p ~/.kube
    k3d kubeconfig get "$CLUSTER" \
        | sed "s#server: https://.*#server: https://k3d-$CLUSTER-server-0:6443#" > ~/.kube/config
}

case "${1:-help}" in
    up)
        if k3d cluster get "$CLUSTER" >/dev/null 2>&1; then
            echo "== Cluster '$CLUSTER' already exists"
        else
            echo "== Creating cluster '$CLUSTER' (1 server + 2 agents)"
            k3d cluster create --config /k8s/cluster.yaml
        fi

        # Put the cluster's load balancer on the lab's frontend network (see compose.yaml),
        # so the proxy reaches it by name: k3d-lab-serverlb:80.
        echo "== Connecting k3d-$CLUSTER-serverlb to $FRONTEND"
        # "already exists" means it's attached already; any other error stops the script.
        if ! out=$(docker network connect "$FRONTEND" "k3d-$CLUSTER-serverlb" 2>&1); then
            case "$out" in
                *"already exists"*) ;;
                *) echo "Could not connect k3d-$CLUSTER-serverlb to $FRONTEND: $out" >&2; exit 1 ;;
            esac
        fi

        echo "== Building $IMAGE"
        docker build -q -t "$IMAGE" /whoami

        # The nodes are separate containers with their own image store: they can't see
        # images on the host, so copy the image into every node.
        echo "== Loading $IMAGE into the cluster nodes"
        k3d image import "$IMAGE" -c "$CLUSTER"

        connect
        echo "== Applying manifests"
        kubectl apply -f /k8s/manifests/
        kubectl rollout status deployment/whoami --timeout=180s

        # k3s installs Traefik (the ingress controller) only after the cluster has started,
        # so on a fresh cluster the front door may not be open yet.
        echo "== Waiting for the ingress controller (Traefik)"
        until kubectl -n kube-system get deployment traefik >/dev/null 2>&1; do sleep 2; done
        kubectl -n kube-system rollout status deployment/traefik --timeout=180s
        until curl -sf -o /dev/null "http://k3d-$CLUSTER-serverlb/?format=text"; do sleep 2; done

        kubectl get pods -o wide
        echo
        echo "Open /k8s/ on the lab's proxy (http://<host>:8080/k8s/) or port 8090 on the Docker host (http://<host>:8090), and reload a few times."
        ;;
    status)
        connect
        kubectl get nodes
        echo
        kubectl get deployments,pods,services,ingress -o wide
        ;;
    test)
        # Send requests the way the outside world does: through the k3d load balancer and the ingress.
        connect
        for i in $(seq "${2:-12}"); do
            curl -s "http://k3d-$CLUSTER-serverlb/?format=text"
        done
        ;;
    kubectl)
        shift
        connect
        kubectl "$@"
        ;;
    shell)
        connect
        echo "kubectl is ready. Type exit to leave."
        exec sh
        ;;
    down)
        k3d cluster delete "$CLUSTER"
        ;;
    *)
        cat <<USAGE
Usage: docker compose run --rm k8s <command>

  up              create the cluster (if needed), build + load the image, apply the manifests
  status          nodes, deployment, pods, service, ingress
  test [n]        send n requests (default 12) and show which node/pod answered
  kubectl <args>  run any kubectl command, e.g.: kubectl get pods -o wide
  shell           a shell with kubectl, k3d and docker ready to use
  down            delete the cluster
USAGE
        ;;
esac
