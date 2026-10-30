# Lesson 3: Host an API

The `api` service is a Laravel app whose only job is answering HTTP requests with JSON.

## How the image is built

```dockerfile title="api/Dockerfile"
--8<-- "api/Dockerfile"
```

The repo only contains the files we **add or change** (`api/src/`). Everything else is a standard Laravel skeleton, generated during the build.

Every time the container starts, it runs this script:

```sh title="api/entrypoint.sh"
--8<-- "api/entrypoint.sh"
```

## Follow a request through the code

### 1. Route

```php title="api/src/routes/api.php"
--8<-- "api/src/routes/api.php"
```

Laravel doesn't load `routes/api.php` by default. [`bootstrap/app.php`](../reference/source.md#bootstrapappphp) turns it on, registers the `api.key` middleware, and makes sure errors come back as JSON.

### 2. Middleware: who may write?

```php title="api/src/app/Http/Middleware/RequireApiKey.php"
--8<-- "api/src/app/Http/Middleware/RequireApiKey.php"
```

The expected key comes from `config('api.key')`, which reads the `API_KEY` environment variable set in `compose.yaml`.

### 3. Controller

```php title="api/src/app/Http/Controllers/BookController.php"
--8<-- "api/src/app/Http/Controllers/BookController.php"
```

### 4. Resource: what the JSON looks like

```php title="api/src/app/Http/Resources/BookResource.php"
--8<-- "api/src/app/Http/Resources/BookResource.php"
```

## Status codes this API uses

| Code | Meaning | When |
|---|---|---|
| **200** OK | here's what you asked for | GET, PUT |
| **201** Created | new resource made | POST |
| **204** No Content | done, nothing to return | DELETE |
| **401** Unauthorized | missing or wrong `X-API-KEY` | POST/PUT/DELETE without the key |
| **404** Not Found | no such book | `/api/books/999` |
| **422** Unprocessable Content | validation failed; see `errors` | POST without `author` |

## Call it yourself

```bash
API=http://LAB_ADDR/api
KEY="X-API-KEY: super-secret-lab-key"

curl $API/books
curl "$API/books?search=dune"
curl $API/books/1
curl -i $API/books/999                                          # -i shows the status line: 404

curl -i -X POST $API/books -d title=Test                        # 401: no key
curl -i -X POST $API/books -H "$KEY" -d title=Test              # 422: author missing
curl -i -X POST $API/books -H "$KEY" -H "Content-Type: application/json" \
     -d '{"title":"Foundation","author":"Isaac Asimov","published_year":1951}'   # 201
```

Note the `id` the API returns, then:

```bash
curl -i -X PUT    $API/books/<id> -H "$KEY" -H "Content-Type: application/json" -d '{"published_year":1942}'
curl -i -X DELETE $API/books/<id> -H "$KEY"                     # 204
```

Then open [Adminer](http://LAB_ADDR/adminer/) and look at the `books` table. The API is a layer on top of that table: it decides who may change it, checks the input, and formats the output.

The full list of endpoints is on the [API reference page](../reference/api.md).
