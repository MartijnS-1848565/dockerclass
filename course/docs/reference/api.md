# API endpoints

This page covers the REST API. The other styles are documented in their own chapters:
[GraphQL](../api-styles/07-graphql.md) (`/graphql`), [gRPC](../api-styles/08-grpc.md) (`grpc:50051`) and [SOAP](../api-styles/09-soap.md) (`/api/soap`).

Base URL from your machine: `http://LAB_ADDR/api`.
Base URL from another container: `http://api:8000/api`.

Writing requires the header `X-API-KEY: super-secret-lab-key` (the `API_KEY` value in `.env`).

| Method | Path | Auth | Body | Success | Errors |
|---|---|---|---|---|---|
| GET | `/books` | – | – | 200, list | – |
| GET | `/books?search=dune` | – | – | 200, filtered list | – |
| GET | `/books/{id}` | – | – | 200, one book | 404 |
| POST | `/books` | key | `title`*, `author`*, `published_year` | 201, the new book | 401, 422 |
| PUT | `/books/{id}` | key | any of the fields above | 200, the updated book | 401, 404, 422 |
| DELETE | `/books/{id}` | key | – | 204, empty | 401, 404 |

\* required

## Book object

```json
{
  "id": 1,
  "title": "Dune",
  "author": "Frank Herbert",
  "published_year": 1965,
  "created_at": "2026-10-21T13:11:38+00:00"
}
```

Single books are wrapped in `{"data": {...}}`, lists in `{"data": [...]}`.

## Error responses

=== "401"

    ```json
    { "message": "Missing or invalid X-API-KEY header." }
    ```

=== "404"

    ```json
    { "message": "No query results for model [App\\Models\\Book] 999" }
    ```

    With `APP_DEBUG=true` you'll also see `exception`, `file` and `trace`.

=== "422"

    ```json
    {
      "message": "The author field is required.",
      "errors": { "author": ["The author field is required."] }
    }
    ```

## Health

`GET http://api:8000/up` returns 200 when Laravel is running. Docker's healthcheck for the api uses it.
