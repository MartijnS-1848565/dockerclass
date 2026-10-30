# Lesson 12: Docker Swarm

In [Lesson 11](11-kubernetes.md) you ran a page 6 times on a 3-node Kubernetes cluster. This lesson does **exactly the same thing** with **Docker Swarm**: the same page, the same image, 3 nodes, 6 copies, load balancing, self-healing and rolling updates.

Swarm is the orchestrator that's **built into Docker**. There's nothing extra to install, a swarm is set up with two commands, and you describe the deployment in the compose format you already know. The price is that it can do a lot less than Kubernetes. Comparing the two on the same job shows you that trade-off. The comparison is at the [end of this lesson](#swarm-or-kubernetes).

## Everything runs in containers

Again, you only need Docker. Each Swarm **node** is a container running its own Docker engine ("Docker-in-Docker", image `docker:dind`), and a small **toolbox container** sets everything up through your machine's Docker socket:

```
your machine (only Docker installed)
├── swarm toolbox ── docker.sock ──► creates the nodes:
│     docker CLI + lab-swarm.sh      ├── swarm-manager    manager + tasks, port 8091
│                                    ├── swarm-worker-1   tasks
│                                    └── swarm-worker-2   tasks
└── the rest of the lab (proxy: /swarm/ → any of the 3 nodes, over lab-frontend)
```

On real hardware each node would be a separate machine with Docker installed. Everything else in this lesson works the same way there.

## Start it

```bash
docker compose run --rm swarm up
```

```text
== Starting nodes on swarm-lab and lab-frontend: swarm-manager swarm-worker-1 swarm-worker-2
== docker swarm init (on swarm-manager)
== docker swarm join (on swarm-worker-1)
== docker swarm join (on swarm-worker-2)
== Building whoami-lab:1.0
== Loading whoami-lab:1.0 into the nodes
== docker stack deploy
Creating network whoami_default
Creating service whoami_whoami
== Waiting for 6 healthy replicas
NAME              NODE             CURRENT STATE
whoami_whoami.1   swarm-worker-2   Running 1 second ago
whoami_whoami.2   swarm-manager    Running 1 second ago
whoami_whoami.3   swarm-worker-1   Running 1 second ago
whoami_whoami.4   swarm-worker-2   Running 1 second ago
whoami_whoami.5   swarm-manager    Running 1 second ago
whoami_whoami.6   swarm-worker-1   Running 1 second ago

Open http://LAB_ADDR/swarm/ (through the lab's proxy) or http://LAB_HOST:8091 and reload a few times.
```

The first time takes under a minute.

The command:

1. starts the 3 node containers, on the swarm's own network and on the lab's `lab-frontend` network (so the proxy can reach them, [see below](#how-the-labs-proxy-reaches-the-swarm)),
2. runs `docker swarm init` on the manager and `docker swarm join` on each worker,
3. builds the page's image (from `whoami/`) and loads it into every node,
4. deploys `swarm/stack.yaml` with `docker stack deploy` and waits until all 6 copies are healthy.

Then open <http://LAB_ADDR/swarm/> (through the lab's proxy) or <http://LAB_HOST:8091> (straight to the manager) and **reload a few times**. The node, the container name and the colour change: each node has its own colour. Click "Auto-refresh every second" to watch it cycle.

From the terminal:

```bash
docker compose run --rm swarm test
```

```text
node=swarm-worker-2 container=whoami_whoami.1.67p8070m25j1gzek8p7njn9ov version=v1 requests=2
node=swarm-worker-1 container=whoami_whoami.6.epc46jz2wi3mx9y4g1bhmoujt version=v1 requests=2
node=swarm-manager  container=whoami_whoami.2.k1dd5n9by29fqtvo64crixfue version=v1 requests=2
node=swarm-manager  container=whoami_whoami.5.np5x8ncw7epfmjr50hwr5xsnn version=v1 requests=2
node=swarm-worker-1 container=whoami_whoami.3.ljyvgq7rumfdnk6gmtenrgupn version=v1 requests=2
node=swarm-worker-2 container=whoami_whoami.4.dknnwm39mx2aysgh1bzpizfut version=v1 requests=3
...
```

Six tasks, two per node, taking turns. A task's name is `<service>.<slot>.<id>`: slot 1 to 6, plus a unique id.

## Setting up a swarm: two commands

With Kubernetes, k3d did a lot of work for you: it started k3s with its API server, scheduler, network plugin and ingress controller. A swarm needs only this:

```bash
# on the machine that becomes the manager:
docker swarm init
# it prints a join command with a token. On every other machine:
docker swarm join --token SWMTKN-1-... <manager-ip>:2377
```

That's the whole cluster. The scheduler, the cluster store, the overlay network between the nodes and the load balancer are all part of the Docker engine you already have. You'll find these two commands in the `up` section of the script below.

## The toolbox

There's no toolbox image to build this time: the official `docker:cli` image already has everything, so compose only adds the script:

```yaml
  swarm:
    image: docker:27.3-cli
    profiles: ["swarm"]          # not started by `docker compose up`
    entrypoint: ["sh", "/swarm/lab-swarm.sh"]
    command: ["help"]
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - ./swarm:/swarm:ro
      - ./whoami:/whoami:ro         # the page (shared with the Kubernetes lesson)
```

The warning from Lesson 11 applies here too: the Docker socket gives the toolbox full control over Docker on your machine. The node containers also run with `--privileged`, because a Docker engine inside a container needs full access to the kernel. That's fine for a lab, but never run untrusted containers like that.

```sh title="swarm/lab-swarm.sh"
--8<-- "swarm/lab-swarm.sh"
```

| Command | What it does |
|---|---|
| `docker compose run --rm swarm up` | start the nodes, form the swarm if needed, load the image, deploy the stack |
| `docker compose run --rm swarm status` | nodes, services, and which node runs each task |
| `docker compose run --rm swarm test 20` | 20 requests, one line each |
| `docker compose run --rm swarm docker service ls` | any `docker` command, run on the manager |
| `docker compose run --rm -it swarm shell` | an interactive shell on the manager node |
| `docker compose run --rm swarm down` | delete the nodes, and with them the swarm |

!!! tip "Commands go to the manager"
    Swarm commands (`docker service ...`, `docker node ...`, `docker stack ...`) only work on a **manager** node, so the `docker` command runs them there. Inside `shell` you can type them directly.

## The stack file

This is the whole deployment, in **one** file:

```yaml title="swarm/stack.yaml"
--8<-- "swarm/stack.yaml"
```

It's a normal compose file. `image`, `environment`, `ports` and `healthcheck` mean exactly what they mean in the lab's `compose.yaml`. Swarm adds the `deploy:` section: how many replicas, where they may run, how to update them, and resource limits. (`docker compose up` ignores most of `deploy:`, and `docker stack deploy` ignores `build:`.)

A few words change meaning in Swarm:

- A **service** is "this image, N times". It's the closest thing to a Kubernetes Deployment.
- A **task** is one copy of a service: one container on one node, like a pod. If a task dies, Swarm doesn't restart it. It starts a **new** task with a new name.
- A **stack** is a group of services deployed from one file. Its name is put in front of everything, which is why the service is called `whoami_whoami` (stack `whoami`, service `whoami`).

The `{{.Node.Hostname}}` and `{{.Task.Name}}` values are **templates**: Swarm fills them in per task, the way the Downward API did in Kubernetes. The page itself doesn't change at all.

## How a request travels

```
browser
  │  http://LAB_ADDR/swarm/              (or straight in: http://LAB_HOST:8091)
  ▼
lab proxy (nginx) ────────── picks one of the 3 nodes, by name, over the lab-frontend network
  ▼
any node, port 80 ────────── published with "ports:", so EVERY node listens on port 80
  │
  ▼
routing mesh (IPVS) ─────── Linux kernel load balancer: picks a healthy task, round-robin,
  │                          on ANY node, and sends the connection over the overlay network
  ▼
task on swarm-manager / swarm-worker-1 / swarm-worker-2
```

Look at the `test` output again. `test` sends every request to the **manager**, but many were answered by containers on the **workers**. That's the **routing mesh**: a published port is open on every node of the swarm, even on nodes that run no copy of the service, and the node that receives a connection passes it on to a healthy task. Kubernetes needed a Service and an Ingress controller for this. In Swarm, the `ports:` line is enough.

!!! info "Per connection, not per request"
    The routing mesh works on **TCP connections** (layer 4). It never looks at the HTTP inside. Every new connection goes to the next task, but all requests on one connection go to the same task. Here every reload still lands somewhere else, because the page's PHP built-in server closes the connection after each response (`Connection: close`; check with `curl -v`). Most real web servers keep the connection open, and then a browser sticks to one task for as long as that connection lives. Traefik in Lesson 11 balanced **per request**, because it reads the HTTP. To get that in Swarm, you deploy an HTTP proxy (Traefik, nginx) as a service yourself.

### How the lab's proxy reaches the swarm

`up` attaches all three nodes to the lab's `lab-frontend` network (`docker network connect`), just like the k3d load balancer in [Lesson 11](11-kubernetes.md#how-the-labs-proxy-finds-the-cluster). Because of the routing mesh, port 80 on **any** node reaches the service, so the proxy lists all three in an `upstream` group:

```nginx title="proxy/conf.d/default.conf (excerpt)"
upstream swarm_nodes {
    zone swarm_nodes 64k;
    server swarm-manager:80  resolve;
    server swarm-worker-1:80 resolve;
    server swarm-worker-2:80 resolve;
}

location /swarm/ {
    proxy_pass http://swarm_nodes/;
    proxy_connect_timeout 2s;
}
```

- `resolve` does for a group what the variable did in Lesson 11: nginx looks the names up **while running** (through `resolver 127.0.0.11`), not once at startup. Without a swarm, nginx still starts and `/swarm/` answers `502`. (While the swarm is down, the proxy log shows `could not be resolved` every few seconds. That's expected. And right after you remove a swarm, nginx may still try the old addresses for a little while, so you can get a slow `504` before the quick `502`s.) `resolve` needs the shared memory `zone`.
- nginx takes turns over the three nodes. When one doesn't answer, it retries the request on the next one. `proxy_connect_timeout 2s` keeps that wait short: the default is 60 seconds.

This gives you something the direct port 8091 can't: when a node goes down, the site stays up. The next experiments show it.

## Experiments

Run these with `docker compose run --rm swarm docker ...`, or open `shell` and drop the prefix. Keep <http://LAB_ADDR/swarm/?refresh> open while you try them.

### Self-healing

Kill one of the containers behind Swarm's back, directly on a worker's own Docker engine:

```bash
docker exec swarm-worker-1 sh -c 'docker rm -f $(docker ps -q | head -1)'
docker compose run --rm swarm docker service ps whoami_whoami
```

Within seconds there's a new task in the same slot. Unlike Kubernetes, Swarm keeps the **history**: the killed task stays in the list with state `Failed` (or `Shutdown`), and the replacement shows below it with `\_`. The request counter of the new task starts at 1 again.

### Scaling

```bash
docker compose run --rm swarm docker service scale whoami_whoami=3
docker compose run --rm swarm test
docker compose run --rm swarm docker service scale whoami_whoami=9
docker compose run --rm swarm test 18
```

`docker service scale` changes the swarm directly. The next `up` puts it back to the 6 in `stack.yaml`, because the stack file is the desired state, just like the Deployment in Lesson 11.

### A node goes away

**Drain** a node: Swarm moves its tasks to the other nodes.

```bash
docker compose run --rm swarm docker node update --availability drain swarm-worker-2
docker compose run --rm swarm status                 # nothing running on worker-2 any more
docker compose run --rm swarm test                   # only the manager and worker-1 answer
docker compose run --rm swarm docker node update --availability active swarm-worker-2
```

There are now 3 tasks on each of the two other nodes: exactly the `max_replicas_per_node: 3` limit. As in Kubernetes, the tasks **don't move back** when the node returns. A rolling update (next experiment) spreads them out again. So does `docker service update --force whoami_whoami`, which replaces every task.

For the real thing, stop a node: `docker stop swarm-worker-1`. The manager notices that the node has stopped answering, marks it `Down` in `status`, and starts its tasks elsewhere. Swarm reacts much faster than Kubernetes, which waits about 5 minutes. Start it again with `docker start swarm-worker-1`.

### The proxy fails over

Keep <http://LAB_ADDR/swarm/?refresh> open and stop a node:

```bash
docker stop swarm-worker-2
for i in $(seq 12); do curl -s "http://LAB_ADDR/swarm/?format=text"; done
```

Every request still gets an answer, now only from `swarm-manager` and `swarm-worker-1`. Now and then (about every 10 seconds) one takes 2 seconds: nginx tries the stopped node again, gives up after `proxy_connect_timeout`, and retries on the next one.

Start the worker again and give it about half a minute: after a restart, a node needs that long before its tasks are running and receiving requests again.

```bash
docker start swarm-worker-2
```

Now the **manager**. First try <http://LAB_HOST:8091>, which goes straight to the manager, then stop it:

```bash
docker stop swarm-manager
for i in $(seq 12); do curl -s "http://LAB_ADDR/swarm/?format=text"; done
```

Port 8091 is gone, but `/swarm/` on the proxy keeps working through the two workers. The running tasks don't need a manager; only **changes** (scaling, updates, replacing tasks) do. Bring it back with `docker start swarm-manager`.

### Rolling update

Change `APP_VERSION: "v1"` to `"v2"` in `swarm/stack.yaml`, then:

```bash
docker compose run --rm swarm up
```

`up` runs `docker stack deploy` again, and Swarm updates only what changed: 2 tasks at a time (`parallelism: 2`), starting the new ones before stopping the old ones (`order: start-first`). Reload during the update and you'll see v1 and v2 mixed. To go back:

```bash
docker compose run --rm swarm docker service rollback whoami_whoami
```

You can also change a service without touching the file: `docker service update --env-add APP_VERSION=v3 whoami_whoami`. But then the file no longer describes what's running, and the next `up` overwrites your change.

## Swarm or Kubernetes?

You've now done the same job twice. Here's what maps to what:

| | Kubernetes (Lesson 11) | Docker Swarm (this lesson) |
|---|---|---|
| set up a cluster | install a distribution (k3s, kubeadm, a cloud service) | `docker swarm init` + `docker swarm join` |
| describe the app | Deployment + Service + Ingress, 3 files in Kubernetes' own format | 1 compose file with a `deploy:` section |
| deploy | `kubectl apply -f` | `docker stack deploy -c` |
| one copy of the app | pod | task |
| "keep N copies running" | Deployment | service with `replicas:` |
| the container learns its own name | Downward API (`fieldRef`) | templates (`{{.Task.Name}}`) |
| only send traffic to ready copies | `readinessProbe` | `healthcheck` |
| spread over the nodes | `topologySpreadConstraints` | done by default; `placement` for limits |
| traffic from outside | Ingress controller (Traefik), per HTTP request | routing mesh on every node, per TCP connection |
| roll back | `kubectl rollout undo` | `docker service rollback` |
| command-line tool | `kubectl` (separate) | `docker` (the one you have) |

**Swarm is simple.** It's in every Docker install, a cluster takes two commands, and if you know Compose you already know most of the stack file format. There are few moving parts, so there's little to learn and little to break. For a small team running a handful of services on a few servers it's often all you need.

**But it's limited, and you can't extend it.** Swarm has a fixed set of features:

- **No autoscaling.** The replica count is a number you set. Kubernetes can add pods (and even nodes) automatically when the load goes up.
- **Basic networking.** The routing mesh balances TCP connections. HTTP routing by host name or path, TLS certificates, and per-request balancing all need a proxy that you deploy and configure yourself. Kubernetes has the Ingress (and Gateway) API for this, and network policies to control which pods may talk to each other.
- **Simple scheduling.** Constraints, spread preferences and a per-node maximum. Kubernetes adds affinity and anti-affinity rules, priorities and preemption, taints and tolerations, and more.
- **Nothing to extend it with.** Kubernetes lets you add your own resource types and **operators** (programs that run a database or a message queue for you), and has a huge ecosystem around them: Helm charts, service meshes, GitOps tools, monitoring. Swarm has none of that.
- **Fewer people, fewer places.** Every cloud provider offers managed Kubernetes. Hardly anyone offers managed Swarm, and most jobs, tools and tutorials assume Kubernetes.

**Rule of thumb:** with a few services on a few machines and no team to look after a platform, Swarm gets you a long way with very little effort. When you need autoscaling, fine-grained networking or security, a managed cloud service, or the ecosystem, use Kubernetes. Then you pay up front in complexity for room to grow.

## Exercises

1. **Over the limit.** Scale to 10 replicas. How many run? `docker service ps whoami_whoami` shows why the 10th is `Pending`. Which line in `stack.yaml` causes this, and what would Kubernetes do with its `ScheduleAnyway` spread rule?
2. **A broken health check.** Change the health check URL to `/nope` (it returns 200 anyway: why? Look at `index.php`). Then make it really fail: change the port in the test to `9999` and run `up`. What happens to the rolling update? Look at `docker service ps whoami_whoami` and `docker service inspect --pretty whoami_whoami`. Add `failure_action: rollback` under `update_config` and try again.
3. **Pin it to the workers.** Add `constraints: [node.role == worker]` under `placement`. Where do the tasks go? How many can run now? Do requests to the manager on port 8091 still work, and why?
4. **Find the overlay network.** Run `docker compose run --rm swarm docker network ls`. Which networks have scope `swarm`? One is the routing mesh's `ingress` network; where does the other one come from? (Hint: you didn't define a network in `stack.yaml`.)
5. **Compose vs Swarm.** Try `docker compose -f swarm/stack.yaml up` on your own machine (stop it with Ctrl+C and clean up with `docker compose -f swarm/stack.yaml down`). Compose does read `replicas: 6`. So why does it fail on the `ports:` line, when Swarm had no problem publishing port 80 for 6 copies? What do the `{{.Task.Name}}` values turn into?
6. **Your choice.** For each case, would you pick Compose, Swarm or Kubernetes, and why? (a) The lab itself, on your laptop. (b) A web shop with 3 services on 2 rented servers, run by 2 developers. (c) A SaaS product with 40 services, traffic peaks during the day, and a platform team.

## Clean up

```bash
docker compose run --rm swarm down
```

This deletes the node containers, and with them the swarm and everything deployed on it. The rest of the lab keeps running.
