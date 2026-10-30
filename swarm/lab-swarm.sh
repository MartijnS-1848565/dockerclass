#!/bin/sh
# Everything for the Swarm chapter, run inside the swarm toolbox container:
#   docker compose run --rm swarm up | status | test | docker ... | shell | down
#
# Each Swarm node is a "Docker-in-Docker" container: a container running its own Docker engine.
# On real hardware each node would be a separate machine with Docker installed.
set -eu

NETWORK=swarm-lab
# The lab's compose network (see compose.yaml). The proxy reaches the nodes over it.
FRONTEND=lab-frontend
MANAGER=swarm-manager
WORKERS="swarm-worker-1 swarm-worker-2"
NODE_IMAGE=docker:27.3-dind
IMAGE=whoami-lab:1.0
STACK=whoami

# Run a docker command on one of the nodes (using that node's own Docker engine).
on() {
    node=$1
    shift
    docker exec -i "$node" docker "$@"
}

# Attach a container to the lab's frontend network, so the proxy can reach it by name.
# "already exists" just means it's attached already; any other error stops the script.
connect_frontend() {
    if ! out=$(docker network connect "$FRONTEND" "$1" 2>&1); then
        case "$out" in
            *"already exists"*) ;;
            *) echo "Could not connect $1 to $FRONTEND: $out" >&2; exit 1 ;;
        esac
    fi
}

start_node() {
    name=$1
    shift
    if docker container inspect "$name" >/dev/null 2>&1; then
        docker start "$name" >/dev/null
    else
        # --privileged: a Docker engine inside a container needs full access to the kernel.
        # /lib/modules: lets it load the kernel modules Swarm's routing mesh needs (ip_vs).
        docker run -d --privileged --name "$name" --hostname "$name" --network "$NETWORK" \
            -v /lib/modules:/lib/modules:ro \
            -e DOCKER_TLS_CERTDIR= \
            "$@" "$NODE_IMAGE" >/dev/null
    fi
    # Connect right away, BEFORE swarm init/join. Joining a swarm makes the node's own engine
    # create a network (docker_gwbridge) on a free 172.x range. If lab-frontend isn't attached
    # yet, that engine can't see its range, may pick the same one, and the connect fails.
    connect_frontend "$name"
}

# A node's address on the swarm's own network. Every node is on two networks, so swarm
# traffic must be pinned to this one: with a mix (some nodes on swarm-lab, some on
# lab-frontend) the tunnels between the nodes break and the routing mesh stops working.
lab_ip() {
    docker inspect -f "{{(index .NetworkSettings.Networks \"$NETWORK\").IPAddress}}" "$1"
}

wait_for_engine() {
    until docker exec "$1" docker info >/dev/null 2>&1; do sleep 1; done
}

require_swarm() {
    if ! docker container inspect "$MANAGER" >/dev/null 2>&1; then
        echo "No swarm yet. Run: docker compose run --rm swarm up" >&2
        exit 1
    fi
}

case "${1:-help}" in
    up)
        docker network inspect "$NETWORK" >/dev/null 2>&1 || docker network create "$NETWORK" >/dev/null

        # Every node is on the swarm's own network and on the lab's frontend network. The proxy
        # reaches the routing mesh as swarm-manager:80, swarm-worker-1:80, ... and fails over.
        echo "== Starting nodes on $NETWORK and $FRONTEND: $MANAGER $WORKERS"
        # The manager also publishes the routing mesh on your machine's port 8091, so you
        # can reach the swarm without the lab's proxy. Every node accepts traffic on port 80.
        start_node "$MANAGER" -p 8091:80
        for worker in $WORKERS; do start_node "$worker"; done
        for node in $MANAGER $WORKERS; do wait_for_engine "$node"; done

        # Step 1 of setting up a swarm: one command on the manager...
        if [ "$(on $MANAGER info --format '{{.Swarm.LocalNodeState}}')" != "active" ]; then
            echo "== docker swarm init (on $MANAGER)"
            on "$MANAGER" swarm init --advertise-addr "$(lab_ip "$MANAGER")" >/dev/null
        fi

        # ...step 2: one command on each worker, with a token from the manager. That's it.
        token=$(on "$MANAGER" swarm join-token -q worker)
        manager_ip=$(lab_ip "$MANAGER")
        for worker in $WORKERS; do
            if [ "$(on "$worker" info --format '{{.Swarm.LocalNodeState}}')" != "active" ]; then
                echo "== docker swarm join (on $worker)"
                on "$worker" swarm join --advertise-addr "$(lab_ip "$worker")" \
                    --token "$token" "$manager_ip:2377" >/dev/null
            fi
        done

        echo "== Building $IMAGE"
        docker build -q -t "$IMAGE" /whoami >/dev/null

        # Each node has its own image store. Normally the nodes pull from a registry;
        # here we copy the image into each node instead.
        echo "== Loading $IMAGE into the nodes"
        for node in $MANAGER $WORKERS; do
            docker save "$IMAGE" | docker exec -i "$node" docker load -q >/dev/null
        done

        echo "== docker stack deploy"
        # --resolve-image never: don't look the image up in a registry, use the nodes' local copy.
        # --detach=true: return right away; the loops below do the waiting.
        on "$MANAGER" stack deploy --resolve-image never --detach=true -c - "$STACK" < /swarm/stack.yaml

        echo "== Waiting for 6 healthy replicas"
        # Replicas reads "6/6 (max 3 per node)"; cut keeps just the "6/6".
        until [ "$(on "$MANAGER" service ls --filter name=${STACK}_whoami --format '{{.Replicas}}' | cut -d' ' -f1)" = "6/6" ]; do sleep 2; done
        until docker exec "$MANAGER" wget -qO- "http://$MANAGER/?format=text" >/dev/null 2>&1; do sleep 2; done

        on "$MANAGER" service ps "${STACK}_whoami" --filter desired-state=running \
            --format 'table {{.Name}}\t{{.Node}}\t{{.CurrentState}}'
        echo
        echo "Open /swarm/ on the lab's proxy (http://<host>:8080/swarm/) or port 8091 on the Docker host (http://<host>:8091), and reload a few times."
        ;;
    status)
        require_swarm
        on "$MANAGER" node ls
        echo
        on "$MANAGER" service ls
        echo
        on "$MANAGER" service ps "${STACK}_whoami" --format 'table {{.Name}}\t{{.Node}}\t{{.DesiredState}}\t{{.CurrentState}}'
        ;;
    test)
        # Each request is a new connection to the routing mesh on the manager's port 80.
        # (Its name, not "localhost": the mesh doesn't answer on a node's loopback address.)
        require_swarm
        for i in $(seq "${2:-12}"); do
            docker exec "$MANAGER" wget -qO- "http://$MANAGER/?format=text"
        done
        ;;
    docker)
        # Any docker command, run on the manager: docker compose run --rm swarm docker service ls
        require_swarm
        shift
        on "$MANAGER" "$@"
        ;;
    shell)
        require_swarm
        echo "A shell on $MANAGER, with its docker CLI. Type exit to leave."
        exec docker exec -it "$MANAGER" sh
        ;;
    down)
        docker rm -f $MANAGER $WORKERS >/dev/null 2>&1 || true
        docker network rm "$NETWORK" >/dev/null 2>&1 || true
        echo "Swarm removed."
        ;;
    *)
        cat <<USAGE
Usage: docker compose run --rm swarm <command>

  up             start 3 nodes, form the swarm, load the image, deploy the stack
  status         nodes, services, and which node runs each container
  test [n]       send n requests (default 12) and show which node/container answered
  docker <args>  run any docker command on the manager, e.g.: docker service ls
  shell          a shell on the manager node
  down           delete the nodes (and with them the whole swarm)
USAGE
        ;;
esac
