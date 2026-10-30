# Lesson 4: Consume an API

The `client` service is a normal Laravel website, except it has **no database**. Every book it shows comes from an HTTP call to the API.

## Where the API lives

```php title="client/src/config/bookapi.php"
--8<-- "client/src/config/bookapi.php"
```

!!! warning "`api`, not `localhost`"
    Inside a container, `localhost` means *that container itself*. The client reaches the API by its **service name**, `api`, which Docker's DNS resolves. Port `8000` is the port inside the api container, not the published `8080`.

    From your **browser** it's `http://LAB_ADDR` (through the proxy). From **another container** it's `http://api:8000` (straight across the Docker network).

## The controller

```php title="client/src/app/Http/Controllers/BookController.php"
--8<-- "client/src/app/Http/Controllers/BookController.php"
```

Every action follows the same pattern:

1. **Send** a request with `$this->api()->get(...)`, `->post(...)`, `->put(...)` or `->delete(...)`.
2. **Check the status code.** A 422 means "show the API's error messages on the form" and a 404 means "page not found". For anything else unexpected, `->throw()` turns the response into an exception.
3. **Use the JSON.** `$response->json('data')` picks a key out of the response body.

## When the API is down

```php title="client/src/bootstrap/app.php"
--8<-- "client/src/bootstrap/app.php"
```

Try it:

```bash
docker compose stop api      # now refresh http://LAB_ADDR/client/
docker compose start api
```

## The client can't reach the database

The client is only on the `frontend` network. Check it:

```bash
# client → api: works
docker compose exec client php -r 'echo file_get_contents("http://api:8000/api/books");'

# client → db: bool(false), there's no route to it
docker compose exec client php -r 'var_dump(@fsockopen("db", 3306));'
```

This is a common design: the API is the **only** way in to the data, so its rules (API key, validation) can't be bypassed.

??? question "Check yourself: the client sends the API key, so why can't a visitor's browser just read it?"
    The HTTP call to the API happens **server-side**, from the client container to the api container. The browser only receives the finished HTML. The key lives in an environment variable on the server and never reaches the browser. If you called the API from JavaScript in the browser instead, anyone could read the key in their dev tools.
