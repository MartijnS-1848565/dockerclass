# Command cheat sheet

## Images

| Command | What it does |
|---|---|
| `docker build -t name .` | build an image from the Dockerfile in `.` |
| `docker images` | list images |
| `docker history name` | show an image's layers and their sizes |
| `docker rmi name` | delete an image |
| `docker image prune` | delete unused (dangling) images |

## Containers

| Command | What it does |
|---|---|
| `docker run --rm -p 8081:80 name` | start a container, publish a port, delete it on exit |
| `docker run -d --name x name` | start it in the background with a name |
| `docker ps` / `docker ps -a` | running / all containers |
| `docker logs -f x` | follow a container's output |
| `docker exec -it x sh` | open a shell inside a running container |
| `docker stop x` / `docker rm x` | stop / delete a container |

## Compose (run in the lab folder)

| Command | What it does |
|---|---|
| `docker compose up -d --build` | build what changed, start everything in the background |
| `docker compose up -d --build api` | rebuild and restart one service |
| `docker compose ps` | status and health of each service |
| `docker compose logs -f api` | follow one service's logs |
| `docker compose exec api sh` | shell in a running service |
| `docker compose stop api` / `start api` | pause / resume one service |
| `docker compose restart proxy` | restart one service (e.g. after editing nginx config) |
| `docker compose config` | show compose.yaml with `.env` values filled in |
| `docker compose down` | stop and remove containers and networks |
| `docker compose down -v` | ...and the volumes (**deletes the database**) |

## Volumes and networks

| Command | What it does |
|---|---|
| `docker volume ls` | list volumes |
| `docker volume inspect docker-laravel-api-lab_dbdata` | where a volume lives |
| `docker network ls` | list networks |
| `docker network inspect lab-frontend` | which containers are on a network (`frontend` has the fixed name `lab-frontend`; `backend` is `docker-laravel-api-lab_backend`) |

## Other API styles

| Command | What it does |
|---|---|
| `curl http://LAB_ADDR/graphql -H "Content-Type: application/json" -d '{"query":"{ books { title } }"}'` | GraphQL query |
| `docker compose run --rm grpcurl -plaintext grpc:50051 list` | list gRPC services (reflection) |
| `docker compose run --rm grpcurl -plaintext grpc:50051 describe books.v1.BookService` | show gRPC methods |
| `docker compose run --rm grpcurl -plaintext -d '{"id":2}' grpc:50051 books.v1.BookService/GetBook` | call a gRPC method |
| `curl "http://LAB_ADDR/api/soap?wsdl"` | download the SOAP contract |
| `docker compose exec mqtt mosquitto_sub -t 'books/#' -v` | watch MQTT messages (Ctrl+C to stop) |
| `docker compose exec mqtt mosquitto_pub -t lab/test -m hello` | publish an MQTT message |
| `docker compose logs -f listener` | follow the MQTT listener container |

## API documentation (lesson 13)

| Command | What it does |
|---|---|
| open <http://LAB_ADDR/flask/apidocs/> | Swagger UI: read the docs and try requests |
| `curl http://LAB_ADDR/flask/apispec_1.json` | the OpenAPI document |
| `docker run --rm --network lab-frontend openapitools/openapi-generator-cli:v7.25.0 validate -i http://flask:5000/apispec_1.json` | check the OpenAPI document |
| `docker run --rm --network lab-frontend -v "$PWD/out:/out" openapitools/openapi-generator-cli:v7.25.0 generate -i http://flask:5000/apispec_1.json -g python -o /out/books-client` | generate a Python client |
| `docker run --rm --network lab-frontend schemathesis/schemathesis:4.32.3 run http://flask:5000/apispec_1.json --url http://flask:5000` | test the API against its docs |

## Kubernetes (lesson 11)

| Command | What it does |
|---|---|
| `docker compose run --rm k8s up` | create the cluster and deploy (again) |
| `docker compose run --rm k8s test 20` | 20 requests: which node/pod answered |
| `docker compose run --rm k8s status` | nodes, pods, service, ingress |
| `docker compose run --rm k8s kubectl get pods -o wide` | pods and the node each runs on |
| `docker compose run --rm k8s kubectl scale deployment whoami --replicas=3` | change the number of pods |
| `docker compose run --rm k8s kubectl rollout undo deployment/whoami` | go back to the previous version |
| `docker compose run --rm -it k8s shell` | interactive shell with kubectl |
| `docker compose run --rm k8s down` | delete the cluster |

## Docker Swarm (lesson 12)

| Command | What it does |
|---|---|
| `docker compose run --rm swarm up` | start the nodes, form the swarm and deploy (again) |
| `docker compose run --rm swarm test 20` | 20 requests: which node/container answered |
| `docker compose run --rm swarm status` | nodes, services, and where each task runs |
| `docker compose run --rm swarm docker service ps whoami_whoami` | the tasks (containers) and the node each runs on |
| `docker compose run --rm swarm docker service scale whoami_whoami=3` | change the number of replicas |
| `docker compose run --rm swarm docker service rollback whoami_whoami` | go back to the previous version |
| `docker compose run --rm swarm docker node update --availability drain swarm-worker-1` | move all containers off a node |
| `docker compose run --rm -it swarm shell` | interactive shell on the manager node |
| `docker compose run --rm swarm down` | delete the swarm |

## Laravel inside the containers

| Command | What it does |
|---|---|
| `docker compose exec api php artisan route:list --path=api` | list the API routes |
| `docker compose exec api php artisan migrate:status` | which migrations ran |
| `docker compose exec api php artisan tinker` | interactive PHP with the app loaded |
| `docker compose exec api php artisan migrate:fresh --seed` | rebuild the tables and reseed |
