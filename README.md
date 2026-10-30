# Docker + Laravel API lab

A hands-on lab for learning Docker by running two Laravel apps that talk to each other:

- **api**: a Laravel 13 app that *hosts* a REST API for books, stored in MariaDB.
- **client**: a Laravel 13 website that *consumes* that API over HTTP.

The same books are also served as GraphQL, gRPC and SOAP, and every change is published over MQTT. Each style has its own chapter, and a Flask version of the REST API shows how to document an API with OpenAPI (Swagger UI).

Two final chapters deploy a load-balanced page to a 3-node Kubernetes cluster and to a 3-node Docker Swarm, both made of containers themselves, so the whole lab needs nothing but Docker.

The full course (lessons, exercises and reference docs) runs as a website in its own container.

## Quick start

```bash
docker compose up -d --build   # first build takes a few minutes
docker compose ps              # api and db should show "healthy"
```

| URL | What |
|---|---|
| **http://&lt;host&gt;:8080/course/** | **the course: start here** |
| http://&lt;host&gt;:8080/client/ | the website that consumes the API |
| http://&lt;host&gt;:8080/api/books | the REST API, raw JSON |
| http://&lt;host&gt;:8080/graphiql | GraphQL explorer |
| `<host>:8080` | gRPC (grpcurl / Postman, plaintext) |
| http://&lt;host&gt;:8080/flask/apidocs/ | the documented Flask REST API (Swagger UI) |
| http://&lt;host&gt;:8080/api/soap?wsdl | SOAP contract (WSDL) |
| http://&lt;host&gt;:8080/client/mqtt | live book events over MQTT |
| http://&lt;host&gt;:8080/k8s/ | Kubernetes whoami page (after `docker compose run --rm k8s up`) |
| http://&lt;host&gt;:8080/swarm/ | Swarm whoami page (after `docker compose run --rm swarm up`) |
| http://&lt;host&gt;:8080/adminer/ | DB web UI (server `db`, user `books_user`, password `books_pwd`) |

Everything goes through one nginx reverse proxy, on ports 8080 and 80 (set with `PROXY_PORT` and `PROXY_PORT_2` in [.env](.env); remove `PROXY_PORT_2` if port 80 is taken on your machine).
`<host>` is the name or IP address of the machine running Docker, as you'd type it in your browser. The proxy routes on the path (`/course/`, `/client/`, `/api/`, ...), so everything lives on that one address: no extra host names or `/etc/hosts` entries needed. Once you're in the course, every address on its pages is filled in with the one you used.

Stop with `docker compose down`. Add `-v` to also delete the database.

Kubernetes chapter: `docker compose run --rm k8s up` creates the cluster and deploys, `docker compose run --rm k8s down` deletes it.
Swarm chapter: the same with `docker compose run --rm swarm up` / `down`.

## Layout

| Path | What |
|---|---|
| [compose.yaml](compose.yaml) | the whole stack: proxy, course, client, listener, api, grpc, flask, mqtt, db, adminer (+ grpcurl, k8s and swarm tools) |
| [lesson-01-hello/](lesson-01-hello/) | lesson 1: a single container, no compose |
| [api/](api/) | the Laravel API: REST, GraphQL and SOAP (`src/` holds only the files we add to Laravel) |
| [grpc/](grpc/) | the gRPC service (Python) and its `books.proto` contract |
| [flask/](flask/) | the books REST API in Flask, documented with Flasgger (OpenAPI / Swagger UI) |
| [client/](client/) | the Laravel website consuming the API (the same image runs the MQTT `listener`) |
| [mqtt/](mqtt/) | MQTT broker (Mosquitto) config |
| [proxy/conf.d/](proxy/conf.d/) | nginx reverse-proxy config |
| [whoami/](whoami/) | the load-balancing demo page, used by the Kubernetes and Swarm chapters |
| [k8s/](k8s/) | Kubernetes chapter: toolbox image, k3d cluster, manifests |
| [swarm/](swarm/) | Swarm chapter: stack file and toolbox script |
| [course/](course/) | the course website: pages in `docs/` (markdown, plus the KU Leuven stylesheet and logos), header/footer templates in `overrides/`. Rebuild with `docker compose up -d --build course` |
# dockerclass
