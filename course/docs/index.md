# Docker + Laravel API lab

Learn Docker by running two Laravel apps that talk to each other:

- **api**: a Laravel 13 app that *hosts* a REST API for books (`/api/books`), stored in MariaDB.
- **client**: a Laravel 13 website that *consumes* that API over HTTP. It has no database of its own.

The same books are also served as **GraphQL**, **gRPC** and **SOAP**, and every change is announced over **MQTT**. Each style has its own chapter, so you can compare them side by side. Another chapter shows how to **document** a REST API with OpenAPI, using a Flask version of the books API with Swagger UI.

Along the way you'll write Dockerfiles, run a multi-container stack with Docker Compose, connect containers over networks, keep data in volumes, and put everything behind an nginx reverse proxy. That includes this website: it runs in a container too. The last two chapters move to **orchestrators**: a 3-node **Kubernetes** cluster, also made of containers, that load-balances a page over 6 copies, and then the same thing on **Docker Swarm**, so you can weigh the two.

## What's running

| URL | Service | What |
|---|---|---|
| <http://LAB_ADDR/course/> | `course` | this course (you are here) |
| <http://LAB_ADDR/client/> | `client` | the website that consumes the API |
| <http://LAB_ADDR/api/books> | `api` | the REST API, raw JSON |
| <http://LAB_ADDR/graphiql> | `api` | GraphQL explorer ([Lesson 7](api-styles/07-graphql.md)) |
| `LAB_ADDR` | `grpc` | gRPC, for grpcurl or Postman ([Lesson 8](api-styles/08-grpc.md)) |
| <http://LAB_ADDR/flask/apidocs/> | `flask` | the documented Flask REST API in Swagger UI ([Lesson 13](lessons/13-api-documentation.md)) |
| <http://LAB_ADDR/api/soap?wsdl> | `api` | SOAP contract ([Lesson 9](api-styles/09-soap.md)) |
| <http://LAB_ADDR/client/mqtt> | `client` + `mqtt` | live book events over MQTT ([Lesson 10](api-styles/10-mqtt.md)) |
| <http://LAB_ADDR/k8s/> | Kubernetes | the load-balanced whoami page ([Lesson 11](lessons/11-kubernetes.md)), after `docker compose run --rm k8s up` |
| <http://LAB_ADDR/swarm/> | Docker Swarm | the same page on a swarm ([Lesson 12](lessons/12-swarm.md)), after `docker compose run --rm swarm up` |
| <http://LAB_ADDR/adminer/> | `adminer` | DB web UI (server `db`, user `books_user`, password `books_pwd`, database `books`) |

Everything shares one address, `LAB_ADDR`: the **path** decides which container answers. The addresses on these pages follow whatever you typed to open this course, so they also work when the lab runs on another machine.

## How it fits together

```
                     ┌──────────────────── frontend network ────────────────────┐
                     │                                                          │
browser ──:8080──► proxy ──► course    (this website)                           │
                   (nginx) ─► client ── REST / GraphQL / SOAP ──► api ────┐     │
                     │  ────► api                                         │     │
                     │  ────► mqtt ◄── events from api ──► listener       │     │
                     │  ────► grpc, flask ──────────────────────────┐     │     │
                     │  ────► adminer ──────────────────────────┐   │     │     │
                     └──────────────────────────────────────────┼───┼─────┼─────┘
                     ┌──────────────────── backend network ─────┼───┼─────┼─────┐
                     │                                          ▼   ▼     ▼     │
                     │                                       db (MariaDB)       │
                     └──────────────────────────────────────────────────────────┘
```

- Only **proxy** publishes a port (`8080`) to your machine. It looks at the path in the URL (`/client/`, `/api/`, ...) and forwards the request to the right container.
- **client** sits only on the *frontend* network, so it cannot reach the database. It has to go through the API.
- **api**, **grpc**, **flask** and **adminer** sit on both networks.
- **db** keeps its data in a named volume, so books survive restarts.
- **mqtt** is a message broker: the api publishes an event on every change, and the **listener** (and the browser) subscribe.

## Start

```bash
cd docker-laravel-api-lab
docker compose up -d --build   # first build takes a few minutes
docker compose ps              # api and db should show "healthy"
```

Then work through the lessons in order, starting with [Lesson 1: One container](lessons/01-one-container.md).

## Folder layout

```
docker-laravel-api-lab/
├── compose.yaml          # the whole stack: 10 services (+ grpcurl, k8s and swarm tools), 2 networks, 2 volumes
├── .env                  # values substituted into compose.yaml
├── lesson-01-hello/      # a single container, no compose
├── proxy/conf.d/         # nginx reverse-proxy config (bind-mounted)
├── api/                  # the Laravel REST API
│   ├── Dockerfile        # PHP + composer → fresh Laravel → copy src/ on top
│   ├── entrypoint.sh     # migrate, seed, serve
│   └── src/              # ONLY the files we add/change in Laravel (REST, GraphQL, SOAP)
├── client/               # the Laravel website that consumes the API (also runs the MQTT listener)
│   ├── Dockerfile
│   └── src/
├── mqtt/mosquitto.conf   # MQTT broker config (bind-mounted)
├── grpc/                 # the gRPC service (Python)
│   ├── Dockerfile
│   ├── protos/books.proto
│   └── server.py
├── flask/                # lesson 13: the books API in Flask, documented with Flasgger (OpenAPI)
│   ├── Dockerfile
│   └── app.py
├── whoami/               # lessons 11-12: the load-balancing demo page
├── k8s/                  # lesson 11: Kubernetes
│   ├── tools/            # toolbox image: docker CLI + k3d + kubectl, and the lab-k8s script
│   ├── cluster.yaml      # the k3d cluster: 1 server + 2 agents
│   └── manifests/        # Deployment, Service, Ingress
├── swarm/                # lesson 12: Docker Swarm
│   ├── stack.yaml        # the whole deployment: a compose file with a deploy: section
│   └── lab-swarm.sh      # the toolbox script: start the nodes, form the swarm, deploy
└── course/               # this website
    ├── Dockerfile        # multi-stage: MkDocs → nginx
    ├── mkdocs.yml
    ├── nginx.conf        # how nginx serves the built site
    ├── overrides/        # theme templates: KU Leuven + DistriNet logos in header and footer
    └── docs/             # the markdown pages you're reading, plus the stylesheet and logos
```
