# compose.yaml

The whole stack in one file. Every block has a comment explaining it.

```yaml title="compose.yaml"
--8<-- "compose.yaml"
```

## The .env file

`docker compose` reads `.env` from the same folder and substitutes `${NAME}` in `compose.yaml`:

| Variable | Value in this lab | Used by |
|---|---|---|
| `PROXY_PORT` | `8080` | proxy (published port) |
| `PROXY_PORT_2` | `80` | proxy (second published port; leave it out for just `PROXY_PORT`) |
| `API_KEY` | `super-secret-lab-key` | api (checks it), client (sends it) |
| `DB_DATABASE` | `books` | db, api |
| `DB_USERNAME` | `books_user` | db, api |
| `DB_PASSWORD` | `books_pwd` | db, api |
| `DB_ROOT_PASSWORD` | `root` | db |

Run `docker compose config` to see `compose.yaml` with all values filled in.

!!! note
    This lab commits `.env` so it works out of the box. In a real project, keep `.env` out of git and out of your images.
