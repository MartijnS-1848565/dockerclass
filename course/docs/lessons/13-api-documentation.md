# Lesson 13: Documenting an API

In [Lesson 4](04-consume-an-api.md) the client consumed the books API, and you could find out how the API works by **reading its code**. Most consumers can't do that: another team, a company using your API, or you in a year's time. For them, the API is exactly what its **documentation** says it is.

This lesson is about documenting a REST API: what good documentation contains, the standard format for it (**OpenAPI**, formerly called Swagger), and a worked example. The books API once more, written in Flask this time and documented with **Flasgger**, so you can read and try it in the browser.

## What good REST documentation contains

| Part | What it answers | In the books API |
|---|---|---|
| **Base URL and versions** | Where is the API, and which version am I using? | `http://LAB_ADDR/flask/api`, version `1.0.0` |
| **Authentication** | How do I prove who I am, and which requests need it? | `X-API-KEY` header, only for writes |
| **Endpoints** | Which method and path, and what does it do? | `GET /api/books`: list all books |
| **Parameters** | What goes in the path, the query string, the headers and the body? Which type? Required? | `book_id` (integer, path), `search` (string, query, optional) |
| **Responses** | What comes back for **each** status code, including the errors? | `200` with the book, `404` when it doesn't exist |
| **Data models** | What do the objects look like, field by field? | `Book`: `id`, `title`, `author`, `published_year` (may be `null`), `created_at` |
| **Error format** | What does an error look like, so the client can handle all of them the same way? | `{"message": ...}`, plus `errors` per field for a `422` |
| **Conventions** | Dates, `null`, pagination, rate limits... | dates in ISO 8601, UTC |
| **Examples** | What does a real request and answer look like? | in every model, and with **Try it out** |

That's the **reference** part: precise and complete, but dry. Good API documentation usually also has a short **guide**, "your first request in 5 minutes". The `description` at the top of our docs is a small version of that.

## OpenAPI: documentation as a contract

You could write all of that in a Word document. The trouble is that only people can read it. The **OpenAPI Specification** describes a REST API in a JSON or YAML file, in a fixed format that **tools** can read as well:

- **Swagger UI** turns it into an interactive web page: the docs in this lesson.
- **Code generators** turn it into a client library in Python, Java, PHP, TypeScript and more, or into a skeleton for the server.
- **Test tools** send requests based on it and check that the answers match what it promises.
- **Postman**, Insomnia and API gateways can import it.

So OpenAPI is to REST what the WSDL is to SOAP ([Lesson 9](../api-styles/09-soap.md)), the schema to GraphQL ([Lesson 7](../api-styles/07-graphql.md)) and the `.proto` file to gRPC ([Lesson 8](../api-styles/08-grpc.md)). There's one big difference: those styles **need** their contract to work at all. A REST API works fine without one, so the contract is only there if someone writes it. That's why documenting a REST API takes discipline ([Comparing them](../api-styles/comparison.md)).

!!! info "Swagger or OpenAPI?"
    "Swagger" was the original name of both the format and its tools. Version 2.0 of the format was handed over to a foundation and renamed **OpenAPI**. Since then "OpenAPI" is the format (current versions 3.0 and 3.1) and "Swagger" is the name of the tools (Swagger UI, Swagger Editor). Flasgger writes **Swagger 2.0** (= OpenAPI 2.0) by default, which is what this lesson uses. Exercise 5 converts a route to OpenAPI 3.

### Code-first or design-first

- **Design-first:** you write the OpenAPI file **before** the code, agree on it with the people who'll use the API, and then build it. The front end can start right away, against a mock server generated from the file.
- **Code-first:** you write the code and put the documentation **in** it, as comments, docstrings or annotations. A tool collects them into an OpenAPI file. Flasgger works this way, and so do Laravel Scribe, swagger-php and FastAPI.

Code-first is easier to start with, and the docs sit right next to the code they describe, so they're more likely to be kept up to date. But nothing **forces** them to be right. You'll see that below.

## The example: the books API in Flask

The `flask` service is a fourth implementation of the books API, after REST (Laravel), GraphQL and gRPC. It uses the same `books` table, the same JSON shapes, the same `X-API-KEY` and the same error format as the Laravel REST API, so you can compare them. The difference is that this one is fully documented.

| URL | What |
|---|---|
| <http://LAB_ADDR/flask/apidocs/> | **Swagger UI**: the docs, with "Try it out" |
| <http://LAB_ADDR/flask/apispec_1.json> | the OpenAPI document itself (Swagger 2.0, JSON) |
| <http://LAB_ADDR/flask/api/books> | the API |

```yaml title="compose.yaml (excerpt)"
  flask:
    build: ./flask
    environment:
      API_KEY: ${API_KEY}
      DB_HOST: db
      ...
    depends_on:
      api:
        condition: service_healthy   # the api's migrations create the books table
    networks:
      - frontend
      - backend
```

```python title="flask/requirements.txt"
--8<-- "flask/requirements.txt"
```

Writing a book through this API doesn't publish an MQTT event, because only the Laravel api does that. In a real system each service would own its own data. The shared table is a shortcut, the same one the gRPC service takes.

### What's the same for the whole API

Flasgger starts from a **template**: the parts of the OpenAPI document that aren't tied to one route. That's the general info, how to authenticate, and the **definitions**, the data models that routes refer to.

```python title="flask/app.py (the template)"
--8<-- "flask/app.py:template"
```

- `securityDefinitions` declares **how** to authenticate: an API key in the `X-API-KEY` header. Each route then says **whether** it needs it, with `security:`.
- `definitions` are the reusable models. A route doesn't repeat what a book looks like; it refers to `'#/definitions/Book'`. When the model changes, you change it in one place.
- `"x-nullable": True`: Swagger 2.0 has no way to say "may be `null`" (OpenAPI 3 has `nullable`), so Swagger UI reads this extension instead. Names that start with `x-` are extensions, which tools may ignore.

### A documented route

Everything after the `---` in a docstring is the route's OpenAPI description, in YAML. The first line above it is the **summary**, and the lines after that the **description**.

```python title="flask/app.py (adding a book)"
--8<-- "flask/app.py:create"
```

- `operationId` is the operation's name. Code generators turn it into a method name: `create_book()` in Python, `createBook()` in Java.
- `parameters` lists every input, with **where** it goes: `in: path`, `query`, `header` or, for a JSON body in Swagger 2.0, `in: body` with a `schema`.
- `responses` has one entry for **every** status code the route can return, **including the errors**, each with the shape of its body. Documentation that only describes the happy path leaves the client guessing exactly when things go wrong.
- Flasgger only **reads** the docstring. It doesn't check it against the code below it. If you change the code and forget the docstring, the docs are wrong, and nothing tells you. Unless you test for it: see [Do the docs tell the truth?](#do-the-docs-tell-the-truth).

## Try it out

Open <http://LAB_ADDR/flask/apidocs/>.

1. Open **GET /api/books**, click **Try it out**, type `dune` in `search` and click **Execute**. You see the request as a `curl` command, the URL, and the response with its status code and headers.
2. Open **POST /api/books** and try to add a book. You get a `401`: you haven't sent the key yet.
3. Click **Authorize** (top right, or the lock on a route), enter `super-secret-lab-key` (the `API_KEY` from `.env`) and click **Authorize**. Try again: `201`, with the new book and its id.
4. Send `{"author": ""}` as the body: `422`, with an error for `title` and one for `author`. It's the documented `ValidationError` shape.
5. Scroll down to **Models** to see every data model, field by field.

Swagger UI isn't a separate tool you have to install: it's a page, served by the API itself, that reads `/apispec_1.json`. Look at that file directly too:

```bash
curl -s http://LAB_ADDR/flask/apispec_1.json | head -c 600; echo
```

That file is what the next section works with.

## What you can do with a contract

Every command below runs in a container on the lab's network, so you install nothing. The tools reach the API as `http://flask:5000`.

### Check the contract itself

```bash
docker run --rm --network lab-frontend openapitools/openapi-generator-cli:v7.25.0 \
  validate -i http://flask:5000/apispec_1.json
```
```text
Validating spec (http://flask:5000/apispec_1.json)
No validation issues detected.
```

That wasn't the case the first time. The `search` parameter had `example: dune`, and the validator answered `attribute paths.'/api/books'(get).[search].example is unexpected`: Swagger 2.0 has no `example` for parameters, only OpenAPI 3 does. Swagger UI hadn't complained. Validate your spec; the UI is forgiving.

### Generate a client

```bash
mkdir -p out
docker run --rm --network lab-frontend -u "$(id -u):$(id -g)" -v "$PWD/out:/out" \
  openapitools/openapi-generator-cli:v7.25.0 generate \
  -i http://flask:5000/apispec_1.json -g python -o /out/books-client --package-name books_client
```

`out/books-client/` is now a complete Python package: a `BooksApi` class with a method for every `operationId`, a class for every model, and docs and tests. Using it:

```python title="out/try_client.py"
import books_client
from books_client.exceptions import ApiException

config = books_client.Configuration(host="http://flask:5000")
config.api_key["ApiKey"] = "super-secret-lab-key"     # the name from securityDefinitions

with books_client.ApiClient(config) as client:
    books = books_client.BooksApi(client)
    for book in books.list_books(search="dune").data:
        print("found:", book.id, book.title, book.published_year)
    new = books.create_book(books_client.BookInput(title="Generated", author="OpenAPI Generator")).data
    print("created:", new.id, new.title)
    books.delete_book(new.id)
    try:
        books.get_book(new.id)
    except ApiException as e:
        print("after delete:", e.status)
```

```bash
docker run --rm --network lab-frontend -v "$PWD/out:/out" -w /out python:3.12-slim \
  sh -c 'pip install -q ./books-client && python try_client.py'
```
```text
found: 15 Dune 1965
created: 18 Generated
after delete: 404
```

Not a single line of HTTP code: no URLs, no headers, no JSON parsing. Replace `-g python` with `-g php`, `-g java` or `-g typescript-fetch` for a client in another language. (`docker run --rm openapitools/openapi-generator-cli:v7.25.0 list` shows them all.)

### Do the docs tell the truth?

[Schemathesis](https://schemathesis.io) reads the contract, generates hundreds of requests from it, including strange ones (empty strings, huge numbers, ids that aren't numbers), and checks that every answer matches what the docs promise:

```bash
docker run --rm --network lab-frontend schemathesis/schemathesis:4.32.3 \
  run http://flask:5000/apispec_1.json --url http://flask:5000 --max-examples 50
```

The first time it found **3 failures**:

```text
- ST105 Undocumented Content-Type
    Received: text/html; charset=utf-8
    Documented: application/json
[404] Not Found:
    `<!doctype html> ... <title>404 Not Found</title>`
Reproduce with:
    curl -X GET http://flask:5000/api/books/null%2Cnull
```

The docs promise JSON for every error. But for an id that isn't a number, Flask's route `/api/books/<int:book_id>` doesn't match, and Flask answers with its **own** HTML error page. Nobody would have found that by clicking around in Swagger UI. The fix is the `error_as_json` handler in `app.py`. The second run found a smaller issue: that handler dropped the `Allow` header HTTP requires on a `405`. After both fixes:

```text
Test cases:
  696 generated, 696 passed
```

The run without `-H "X-API-KEY: ..."` is deliberate. Without the key, every write gets the documented `401`, so Schemathesis can't fill your database with random books. To test the writes too, run it against a test database, never against real data.

## And the Laravel API?

The Laravel REST API in this lab has no OpenAPI document. A consumer has to read [the reference page](../reference/api.md) or the code. To fix that the code-first way:

- [**Scribe**](https://scribe.knuckles.wtf/laravel) reads the routes, the validation rules and docblocks, even calls the endpoints to record real responses, and generates an HTML page plus an OpenAPI file. You can see it in action in the earlier application lesson `applicatiecolleges/6_consume_api_laravel` (Demo 3).
- **swagger-php** / **L5-Swagger** let you write the OpenAPI description as PHP attributes above each method, much like Flasgger's docstrings.

## Exercises

1. **Document a new endpoint.** Add `GET /api/books/count` to the Flask API, returning `{"count": 3}`. Document it with a docstring (operationId, response schema), rebuild with `docker compose up -d --build flask`, and check that it shows up in Swagger UI and passes `validate`.
2. **Let the docs lie.** Add a field `"isbn": None` to `to_json()` without documenting it, and remove the `404` response from `show_book`'s docstring. Does Swagger UI notice? Does `validate`? Run Schemathesis: what does it report, and why does it find one problem and not the other? (Hint: look up `additionalProperties`.)
3. **Generate a client in another language.** Generate a PHP client (`-g php`) and use it from a small script in the `client` container, or a TypeScript client (`-g typescript-fetch`) for the browser. Which parts of the contract became method names, and which became classes?
4. **Design first.** Write the documentation for `GET /api/authors` (the distinct author names) **before** the code: add the route with only a docstring and `return jsonify(data=[])`. Look at it in Swagger UI and generate the Python client: a consumer can already start building against it. Then implement it. Did writing the docs first change your mind about the response shape?
5. **OpenAPI 3.** Add `"openapi": "3.0.3"` to Flasgger's config (`Swagger(app, template=template, config={...})`, see the Flasgger README) and convert one route. What happens to `in: body` (now `requestBody`), to `definitions` (now `components/schemas`) and to `x-nullable`?
6. **Compare the contracts.** Put the WSDL from [Lesson 9](../api-styles/09-soap.md), `books.proto` from [Lesson 8](../api-styles/08-grpc.md) and `apispec_1.json` side by side. Which one tells you the most about the errors? Which one can the server **not** ignore?
