# Troubleshooting

| Symptom | Check |
|---|---|
| `port is already allocated` | Something else uses port 8080 or 80. Change `PROXY_PORT` (8080) in `.env`, or remove `PROXY_PORT_2` (80), and run `docker compose up -d`. |
| `502 Bad Gateway` from nginx | The container behind it isn't up yet. Run `docker compose ps`, then `docker compose logs <service>`. |
| api is not healthy / keeps restarting | Run `docker compose logs api`. It's usually a migration or database connection error. |
| Client shows "Cannot reach the Book API" | Is `api` running (`docker compose ps`)? Is `BOOK_API_URL` set to `http://api:8000/api`? |
| Client shows "HTTP 401" | `BOOK_API_KEY` (client) and `API_KEY` (api) must match. Both come from `API_KEY` in `.env`. |
| A page under `/client/`, `/flask/`, ... links to the wrong place (no prefix) | The app behind the proxy doesn't know its path prefix. The proxy sends it in `X-Forwarded-Prefix`; the app has to trust that header (`trustProxies` in Laravel, `ProxyFix` in Flask). |
| A code change doesn't show up | Code is **copied** into the image. Rebuild: `docker compose up -d --build <service>`. |
| A proxy config change doesn't show up | The config is bind-mounted, so just restart nginx: `docker compose restart proxy`. |
| Course build fails with `Snippet ... could not be found` | A `--8<--` line points to a file that was moved or renamed. Fix the path in `course/docs/`. |
| GraphQL answers `200` but nothing happened | Look at the `errors` list in the response. GraphQL reports errors there, not in the status code. |
| `Failed to dial target host` (grpcurl) | Inside the network use `grpc:50051`; from your machine `LAB_ADDR`. Always add `-plaintext` (the lab has no TLS). |
| Swagger UI "Try it out" gives `401` | Click **Authorize** and enter the `API_KEY` from `.env`. Only reading works without it. |
| OpenAPI tools can't reach `http://flask:5000` | Run them with `--network lab-frontend`, and start the lab first (`docker compose up -d`). |
| SOAP page shows "Parsing WSDL" | The client couldn't download the WSDL. Is `api` running? Try `curl "http://LAB_ADDR/api/soap?wsdl"`. |
| MQTT page says "offline" / keeps reconnecting | Is `mqtt` running and healthy (`docker compose ps`)? Did you restart `proxy` after changing its config? |
| No MQTT events when books change | Check `docker compose exec api grep "MQTT publish failed" storage/logs/laravel.log`. The api keeps working without the broker, but it can't publish. |
| Listener keeps restarting | Run `docker compose logs listener`. Usually the broker isn't reachable yet; it recovers by itself. |
| `LAB_ADDR/k8s/` gives 502 | The cluster isn't running: `docker compose run --rm k8s up`. (It's not part of `docker compose up`.) If it **is** running, its load balancer may have lost its connection to `lab-frontend` (e.g. the network was deleted): `up` connects it again. |
| `No cluster yet` from the k8s toolbox | Run `docker compose run --rm k8s up` first. |
| k8s toolbox fails with `path ... not found`, or ignores your changes to `lab-k8s.sh` | The script is copied **into** the toolbox image at build time. Rebuild it: `docker compose build k8s`. (The swarm toolbox mounts its script, so it always runs the current version.) |
| Pods stuck in `ErrImageNeverPull` | The image isn't in the nodes. Run `up` again: it rebuilds and imports the image. |
| Page always shows the same pod | Make sure you're going through port 8090 or `/k8s/` on the proxy. Traefik balances per request; try `docker compose run --rm k8s test`. |
| `LAB_ADDR/swarm/` gives 502 | The swarm isn't running: `docker compose run --rm swarm up`. (It's not part of `docker compose up`.) If it **is** running, the nodes may have lost their connection to `lab-frontend`: `up` connects them again. Check with `docker network inspect lab-frontend`. |
| Proxy log: `swarm-manager could not be resolved` every few seconds | Expected while the swarm isn't running: nginx keeps looking the nodes up so it notices when the swarm comes back. |
| `/swarm/` is slow and gives `504` right after `swarm down` | nginx still tries the nodes' old addresses for a little while; it settles on a quick `502`. `docker compose restart proxy` clears it at once. |
| `docker compose down`: `Network lab-frontend Resource is still in use` (or `has active endpoints`) | Swarm nodes or the k3d load balancer are still attached to the network. Harmless: the network stays and the next `up` reuses it. To avoid it, run `docker compose run --rm swarm down` and `... k8s down` first. |
| `No swarm yet` from the swarm toolbox | Run `docker compose run --rm swarm up` first. |
| Swarm `up` fails at `docker swarm init` or the nodes won't start | The nodes run Docker-in-Docker and need `--privileged`. Rootless Docker and some Docker Desktop setups don't allow that; use a normal Docker engine. |
| Swarm `test` or `up` hangs on `wget` to `localhost` | The routing mesh doesn't answer on a node's loopback address. Use the node's name (`http://swarm-manager/`), as `lab-swarm.sh` does. |
| Laravel uses SQLite instead of MariaDB | `php artisan serve` needs `--no-reload` to pass environment variables through. See `api/entrypoint.sh`. |

## Start completely fresh

```bash
docker compose down -v --rmi local   # containers, networks, the db volume, and the images built here
docker compose up -d --build
```
