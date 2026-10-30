# Lesson 5: Volumes, rebuilds, images

## Containers are disposable, volumes are not

Anything a container writes to its own filesystem disappears when the container is removed. The database writes to `/var/lib/mysql`, which is mounted from the named volume `dbdata`:

```yaml
  db:
    volumes:
      - dbdata:/var/lib/mysql
```

Try it:

1. Add a book in the [client](http://LAB_ADDR/client/).
2. Run `docker compose down`, then `docker compose up -d`. The book is still there.
3. Run `docker volume ls`. Docker manages a volume called `docker-laravel-api-lab_dbdata`.
4. Run `docker compose down -v` (**-v deletes volumes**), then `docker compose up -d`. Only the 3 seeded books are back.

| | Named volume (`dbdata:/var/lib/mysql`) | Bind mount (`./proxy/conf.d:/etc/nginx/conf.d`) |
|---|---|---|
| Where the data lives | managed by Docker | a folder you choose on your machine |
| Good for | database files, anything the app writes | config files and code you want to edit yourself |
| Removed by | `docker compose down -v` / `docker volume rm` | only by you |

## Layers and the build cache

Every instruction in a Dockerfile creates a **layer**. When you rebuild, Docker reuses each cached layer until it reaches the first instruction whose input changed. From there on, everything is rebuilt.

That's why the api Dockerfile is in this order:

1. install PHP extensions (rarely changes)
2. `composer create-project` (slow, never changes)
3. `COPY src/ ./` (**your code**, changes all the time)

Try it:

1. Change something in `api/src/`, for example the `max:255` for `title` in the controller.
2. Run `docker compose up -d --build api`. Most steps print `CACHED`, and the rebuild takes seconds.
3. Run `docker history docker-laravel-api-lab-api` to see each layer and its size.

??? question "Check yourself: what would happen if `COPY src/ ./` came before `composer create-project`?"
    Every code change would invalidate the `COPY` layer **and every layer after it**, so `composer create-project` would run again on every build: about a minute instead of a few seconds. Put what changes least first and what changes most last.

## Configuration through the environment

```bash
docker compose exec api env | grep -E 'DB_|API_KEY'
```

The same image can run in development or production with different settings. Only the environment variables change. That's why the database password isn't in the Dockerfile: it's passed in by `compose.yaml`.
