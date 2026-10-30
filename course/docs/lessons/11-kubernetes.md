# Lesson 11: Kubernetes and load balancing

Docker Compose runs containers on **one** machine. When one machine isn't enough, because you need more capacity or you want to survive a machine dying, you need an **orchestrator** that runs containers across a group of machines (a **cluster**). Kubernetes is the most widely used one.

In this lesson you run a 3-node Kubernetes cluster on your laptop, deploy a tiny page 6 times, and watch Kubernetes spread the requests over the copies. The page tells you which **pod** (container) and which **node** (machine) answered.

## Everything runs in containers

You don't need to install Kubernetes, `kubectl` or `k3d`. A **toolbox container** contains them, and it controls your machine's Docker through the mounted Docker socket:

```
your machine (only Docker installed)
├── k8s toolbox container ── docker.sock ──► creates the cluster's containers:
│     kubectl, k3d, docker CLI               ├── k3d-lab-serverlb   load balancer, host port 8090
│                                            ├── k3d-lab-server-0   node: control plane + pods
│                                            ├── k3d-lab-agent-0    node: pods
│                                            └── k3d-lab-agent-1    node: pods
└── the rest of the lab (proxy: /k8s/ → k3d-lab-serverlb, over lab-frontend)
```

[k3d](https://k3d.io) runs each Kubernetes **node** as a Docker container with [k3s](https://k3s.io) inside, a small but complete Kubernetes. On a real cluster each node would be its own machine; everything else in this lesson works the same way.

## Start it

```bash
docker compose run --rm k8s up
```

The first time takes about a minute. The command:

1. creates the cluster from `k8s/cluster.yaml` (1 server and 2 agents), and attaches its load balancer to the lab's `lab-frontend` network so the proxy can reach it ([details](#how-the-labs-proxy-finds-the-cluster)),
2. builds the page's image (from `whoami/`) and **imports** it into the nodes,
3. applies the manifests in `k8s/manifests/`,
4. waits until the pods and the ingress controller are ready.

Then open <http://LAB_ADDR/k8s/> (through the lab's proxy) or <http://LAB_HOST:8090> (straight to the cluster) and **reload a few times**. The node, the pod name and the colour change: each node has its own colour. Click "Auto-refresh every second" to watch it cycle.

From the terminal:

```bash
docker compose run --rm k8s test
```
```text
node=k3d-lab-server-0 container=whoami-fdbc9f49-k6wsw version=v1 requests=8
node=k3d-lab-server-0 container=whoami-fdbc9f49-mxtms version=v1 requests=8
node=k3d-lab-agent-1  container=whoami-fdbc9f49-6g79q version=v1 requests=8
node=k3d-lab-agent-0  container=whoami-fdbc9f49-942g5 version=v1 requests=8
node=k3d-lab-agent-1  container=whoami-fdbc9f49-zf6dt version=v1 requests=8
node=k3d-lab-agent-0  container=whoami-fdbc9f49-6nzs5 version=v1 requests=9
...
```

Six pods, two per node, taking turns. That's **load balancing**.

## The toolbox

```dockerfile title="k8s/tools/Dockerfile"
--8<-- "k8s/tools/Dockerfile"
```

```yaml
  k8s:
    build: ./k8s/tools
    profiles: ["k8s"]          # not started by `docker compose up`
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - ./k8s:/k8s:ro
      - ./whoami:/whoami:ro         # the page (shared with the Swarm lesson)
```

!!! warning "The Docker socket is the key to the house"
    A container with `/var/run/docker.sock` can start, stop and delete **any** container on the host, and can mount any host folder. That's exactly what the toolbox needs to build a cluster, but it's effectively root access. Only mount the socket into containers you trust.

All commands go through one script:

```sh title="k8s/tools/lab-k8s.sh"
--8<-- "k8s/tools/lab-k8s.sh"
```

| Command | What it does |
|---|---|
| `docker compose run --rm k8s up` | create the cluster if needed, build and load the image, apply the manifests |
| `docker compose run --rm k8s status` | nodes, deployment, pods, service, ingress |
| `docker compose run --rm k8s test 20` | 20 requests, one line each |
| `docker compose run --rm k8s kubectl get pods -o wide` | any `kubectl` command |
| `docker compose run --rm -it k8s shell` | an interactive shell with `kubectl` ready to use |
| `docker compose run --rm k8s down` | delete the cluster |

!!! tip "Typing less"
    Inside `shell` you can use `kubectl` directly, which is easier for longer sessions. Or create an alias on your machine (it works from the lab folder): `alias kubectl='docker compose run --rm k8s kubectl'`.

## The page

```php title="whoami/index.php"
--8<-- "whoami/index.php"
```

The page knows nothing about Kubernetes. It just reads environment variables, and Kubernetes fills them in. (The same image runs on Docker Swarm in the next lesson, where Swarm fills them in.)

## The cluster

```yaml title="k8s/cluster.yaml"
--8<-- "k8s/cluster.yaml"
```

## The manifests

Kubernetes is **declarative**: you don't say "start 6 containers", you describe what you **want** ("there should be 6 of these") and Kubernetes keeps making reality match. You hand it YAML files with `kubectl apply`.

### Deployment: what to run, and how many

```yaml title="k8s/manifests/deployment.yaml"
--8<-- "k8s/manifests/deployment.yaml"
```

The **Downward API** (`valueFrom.fieldRef`) is how the pod learns its own name (`metadata.name`), the node it was scheduled on (`spec.nodeName`) and its IP.

### Service: one stable address for many pods

```yaml title="k8s/manifests/service.yaml"
--8<-- "k8s/manifests/service.yaml"
```

### Ingress: the front door

```yaml title="k8s/manifests/ingress.yaml"
--8<-- "k8s/manifests/ingress.yaml"
```

## How a request travels

```
browser
  │  http://LAB_ADDR/k8s/              (or straight in: http://LAB_HOST:8090)
  ▼
lab proxy (nginx) ───────── looks up k3d-lab-serverlb on the lab-frontend network
  ▼
k3d-lab-serverlb ────────── the k3d load balancer container (→ port 80 on the nodes)
  ▼
Traefik ─────────────────── the ingress controller, built into k3s; reads the Ingress
  │                          and picks a ready pod: request 1 → pod A, request 2 → pod B, ...
  ▼
Service "whoami" ────────── keeps the list of ready pods (label app=whoami, readiness probe OK)
  ▼
pod on k3d-lab-agent-0 / agent-1 / server-0
```

!!! info "Per request, not per connection"
    Traefik is an HTTP (layer 7) proxy, so it balances **every request**, even when the browser reuses one connection. Balancers that work on TCP connections (layer 4) stick with one pod for as long as the browser keeps the connection open. You'd then see the same pod on every reload, even though balancing works.

Compare that with the lab's own nginx proxy: it also routes requests to containers, but each site has one container behind it (except `/swarm/`, see Lesson 12). Traefik does the same job for a group of identical pods.

## How the lab's proxy finds the cluster

The cluster isn't part of `compose.yaml`: k3d creates its containers itself. To let the proxy reach it **by name**, two things are needed.

**1. A shared network.** The lab's `frontend` network has a fixed name, `lab-frontend`, and `up` attaches the k3d load balancer to it:

```sh
docker network connect lab-frontend k3d-lab-serverlb
```

Now `k3d-lab-serverlb` is on the same network as the proxy, and Docker's DNS resolves that name for it, just like `api` or `client`. (`docker network connect` adds a network to a running container. A container can be on several networks; this one is now on `k3d-lab` and `lab-frontend`.)

**2. Looking the name up at the right moment.** The proxy's config for `/k8s/`:

```nginx title="proxy/conf.d/default.conf (excerpt)"
resolver 127.0.0.11 valid=10s;

location /k8s/ {
    set $k8s k3d-lab-serverlb:80;
    rewrite ^/k8s/(.*)$ /$1 break;   # the cluster doesn't know about /k8s/: strip it
    proxy_pass http://$k8s;
}
```

With a plain `proxy_pass http://k3d-lab-serverlb:80;`, nginx looks the name up **once, when it starts**. If the cluster isn't running at that moment, the name doesn't exist and nginx refuses to start, taking the **whole lab** down with it. Putting the address in a **variable** makes nginx look it up **per request** instead. No cluster then means a `502` on this one path, and the rest of the lab keeps working.

For lookups while it's running, nginx uses its own DNS client, and `resolver` tells it which server to ask. `127.0.0.11` is Docker's built-in DNS server, present inside every container on a compose network. `valid=10s` makes nginx remember an answer for at most 10 seconds, so after the cluster has been recreated with new IPs, requests reach it again within 10 seconds.

## Experiments

Run these with `docker compose run --rm k8s ...`, or open `shell` and drop the prefix. Keep the page open on auto-refresh while you try them.

### Self-healing

```bash
docker compose run --rm k8s kubectl get pods
docker compose run --rm k8s kubectl delete pod <one of the pod names>
docker compose run --rm k8s kubectl get pods
```

There are 6 pods again within seconds, with one **new** name. The Deployment wanted 6, saw 5, and started one. The page's request counter for the new pod starts at 1 again: state stored inside a pod disappears with it.

### Scaling

```bash
docker compose run --rm k8s kubectl scale deployment whoami --replicas=3
docker compose run --rm k8s test
docker compose run --rm k8s kubectl scale deployment whoami --replicas=10
docker compose run --rm k8s test 20
```

`kubectl scale` changes the cluster directly. The next `up` puts it back to the 6 in `deployment.yaml`, because the file is the desired state.

### A node goes away

Take a node out of service ("drain" it): its pods are evicted and recreated on the other nodes.

```bash
docker compose run --rm k8s kubectl drain k3d-lab-agent-1 --ignore-daemonsets --delete-emptydir-data
docker compose run --rm k8s kubectl get pods -o wide     # nothing left on agent-1
docker compose run --rm k8s test                         # only server-0 and agent-0 answer
docker compose run --rm k8s kubectl uncordon k3d-lab-agent-1
```

After `uncordon` the node can receive pods again, but the running pods **don't move back** by themselves. Kubernetes only decides where a pod goes when it **starts**. Scale down and up, or do a rollout, to spread them again.

For the real thing, stop a node container: `docker stop k3d-lab-agent-0`. Kubernetes marks the node `NotReady` within a minute, and replaces its pods after about 5 minutes (the default grace period, so a short network hiccup doesn't cause a stampede). Start it again with `docker start k3d-lab-agent-0`.

### Rolling update

Change `value: "v1"` to `value: "v2"` under `APP_VERSION` in `k8s/manifests/deployment.yaml`, then:

```bash
docker compose run --rm k8s up
```

Kubernetes replaces the pods **a few at a time**, and the site stays up throughout: reload during the rollout and you'll see v1 and v2 mixed. Something wrong with v2? Go back:

```bash
docker compose run --rm k8s kubectl rollout undo deployment/whoami
```

### Watch it live

In a second terminal, keep a live view of the pods while you do the experiments above:

```bash
docker compose run --rm k8s kubectl get pods -o wide --watch
```

## Exercises

1. **Strict spreading.** After a few experiments the pods are often unevenly spread (e.g. 3/1/2). Why is that allowed? Change `whenUnsatisfiable: ScheduleAnyway` to `DoNotSchedule`, run `up`, and scale to 7. Where does the 7th pod go? Then scale to 6 and drain a node: what happens to pods that can't be placed?
2. **Readiness.** Change the readiness probe's path to `/nope` and run `up`. Do the new pods get traffic? What does `kubectl get pods` show, and why does the old version keep running? Fix it with `rollout undo` or by restoring the path.
3. **A new image.** Change something visible in `whoami/index.php` (a heading, the colours), build it as `whoami-lab:2.0` (change `IMAGE` in `lab-k8s.sh` and `image:` in the Deployment, then rebuild the toolbox with `docker compose build k8s`) and roll it out. Why does reusing tag `1.0` for a new image cause trouble?
4. **Resource limits.** Lower the memory limit to `4Mi`. What happens to the pods? (`kubectl describe pod <name>` shows the reason.)
5. **Look under the hood.** Run `docker ps --filter name=k3d-lab`. Which containers are the nodes? Now run `docker exec k3d-lab-agent-0 crictl ps`: those are the pod containers, running *inside* the node container.
6. **Compose vs Kubernetes.** The `client` service in the lab runs once. Try `docker compose up -d --scale client=3`. What does Compose do about load balancing between them, and what's missing compared with this lesson (self-healing, rolling updates, multiple machines)?

## Clean up

```bash
docker compose run --rm k8s down
```

This deletes the cluster containers. The rest of the lab keeps running.
