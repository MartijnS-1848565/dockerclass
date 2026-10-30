# Lesson 2: The full stack

One `docker run` per container gets tedious fast. **Docker Compose** describes a whole set of containers in one file, `compose.yaml`, and starts them with one command.

## Start it

```bash
cd docker-laravel-api-lab
docker compose up -d --build   # build the images that need building, then start everything
docker compose ps              # one line per service
```

Open <http://LAB_ADDR/client/>. You can add, edit and delete books. Open the **"What did the API say?"** panel at the bottom of the page to see the raw HTTP call the client made to the API.

## Read compose.yaml

The full file, with comments, is on the [compose.yaml reference page](../reference/compose.md). Things to notice:

`build:` vs `image:`
:   `api`, `client`, `listener`, `grpc`, `course` and the `k8s` toolbox are **built** from a Dockerfile in this lab. `db`, `mqtt`, `adminer`, `grpcurl` and `proxy` use **ready-made images** from Docker Hub.

`ports:`
:   Only `proxy` has one. No other service can be reached directly from your machine.

`environment:`
:   This is how configuration gets into a container. Laravel reads these values with `env()`. Values like `${API_KEY}` come from the `.env` file next to `compose.yaml`.

`depends_on` + `condition: service_healthy`
:   The api waits until the database's **healthcheck** passes, and the client waits for the api. That's more reliable than a `sleep 10` in a startup script.

`networks:`
:   Containers on the same network can reach each other **by service name**: `db`, `api`, and so on.

`volumes:`
:   `dbdata` keeps the database files outside the container, so they survive restarts. See [Lesson 5](05-volumes-and-images.md).

## Day-to-day commands

```bash
docker compose ps                          # what's running, and is it healthy?
docker compose logs -f api                 # follow one service's output (Ctrl+C to stop)
docker compose exec api sh                 # a shell inside the running api container
docker compose exec api php artisan route:list --path=api
docker compose exec api php artisan tinker # then try: App\Models\Book::count()
docker compose restart proxy               # restart one service
docker compose down                        # stop and remove the containers (data survives)
```

## The reverse proxy

All the sites (and gRPC) share one address, `LAB_ADDR`. nginx decides where each request goes based on the **path**: everything under `/client/` goes to the client container, `/api/` to the api, and so on:

```nginx title="proxy/conf.d/default.conf"
--8<-- "proxy/conf.d/default.conf"
```

!!! tip "Why `$http_host` and not `$host`?"
    `$host` drops the port number. Laravel would then build redirects to `http://LAB_HOST/client/` without the port, which breaks every form submit. `$http_host` passes the host exactly as the browser sent it.

??? question "Check yourself: how does nginx find the `client` container?"
    `proxy_pass http://client:8000`: `client` is a **service name**. Docker runs a small DNS server on every user-defined network that turns service names into container IP addresses. Both `proxy` and `client` are on the `frontend` network, so the name resolves.
