# Lesson 7: GraphQL

REST gives you many URLs, and each one returns a shape the **server** chose. GraphQL gives you **one** URL, and the **client** writes a query describing exactly the data it wants.

```graphql
{
  books(search: "dune") {
    title
    author
  }
}
```

```json
{ "data": { "books": [ { "title": "Dune", "author": "Frank Herbert" } ] } }
```

No `id`, no `published_year`, no `created_at`: you get what you asked for and nothing else.

## Where it lives in the lab

| | |
|---|---|
| Endpoint | `POST http://LAB_ADDR/graphql` (from containers: `http://api:8000/graphql`) |
| Explorer | <http://LAB_ADDR/graphiql> |
| Client page | <http://LAB_ADDR/client/graphql> |
| Server code | `api/src/graphql/schema.graphql` + one directive class |
| Library | [Lighthouse](https://lighthouse-php.com), installed in `api/Dockerfile` with `composer require` |

GraphQL runs **inside the same `api` container** as REST. No new service is needed: it's just another route in the same Laravel app, reading the same `Book` model.

## The schema is the contract

```graphql title="api/src/graphql/schema.graphql"
--8<-- "api/src/graphql/schema.graphql"
```

Things to notice:

`type Query` and `type Mutation`
:   Queries read, mutations write. This is the GraphQL equivalent of GET vs POST/PUT/DELETE.

`!`
:   Not null. `String!` is always present; `Book` (without `!`) can be `null`, which is how `book(id: 999)` says "not found".

`@all`, `@find`, `@create`, `@update`, `@delete`
:   Lighthouse directives that resolve a field with Eloquent. That's why there's no controller.

`@rules`
:   Laravel validation rules, the same ones you'd use in a controller.

`@requireApiKey`
:   Our own directive. See below.

## Protecting mutations

In REST we protected `POST /api/books` with route middleware. GraphQL sends everything to the same `POST /graphql`, so the check has to move into the schema, **per field**:

```php title="api/src/app/GraphQL/Directives/RequireApiKeyDirective.php"
--8<-- "api/src/app/GraphQL/Directives/RequireApiKeyDirective.php"
```

## Explore it with GraphiQL

Open <http://LAB_ADDR/graphiql>. It's an in-browser IDE for GraphQL:

1. Open the **Docs** panel (book icon). It lists every type and field, read live from the server. This is called **introspection**.
2. Type `{ books { ` and press `Ctrl+Space`: autocomplete knows which fields exist.
3. Run several queries in **one** request:
   ```graphql
   {
     dune: book(id: 1) { title published_year }
     hobbit: book(id: 2) { title author }
     everything: books { id }
   }
   ```
4. Try a mutation without a key: `mutation { deleteBook(id: 1) { id } }`. Then open the **Headers** tab, add `{"X-API-KEY": "super-secret-lab-key"}`, and run it again.

## Call it with curl

```bash
G=http://LAB_ADDR/graphql
J="Content-Type: application/json"
KEY="X-API-KEY: super-secret-lab-key"

curl -s $G -H "$J" -d '{"query": "{ books { id title } }"}'

# variables keep values out of the query text (like prepared statements in SQL)
curl -s $G -H "$J" -d '{"query": "query ($id: ID!) { book(id: $id) { title } }", "variables": {"id": 2}}'

curl -s $G -H "$J" -H "$KEY" \
     -d '{"query": "mutation { createBook(title: \"Foundation\", author: \"Isaac Asimov\") { id title } }"}'
```

!!! warning "Errors come back with HTTP 200"
    Send a query with a typo, a missing key, or `published_year: 5000`. The status is still **200**. GraphQL reports problems in an `errors` list next to (or instead of) `data`. A client must check `errors`, not just the status code. Compare that with REST, where the status code (401, 404, 422) tells you what went wrong.

## Consume it from Laravel

GraphQL needs no special client library. It's a JSON POST, so Laravel's `Http` client is enough:

```php title="client/src/app/Http/Controllers/GraphqlController.php"
--8<-- "client/src/app/Http/Controllers/GraphqlController.php"
```

Open <http://LAB_ADDR/client/graphql>. The page shows the query it sent, the books it got back, and a box for running your own queries through the client.

## Exercises

1. **Over-fetching.** Compare `curl http://LAB_ADDR/api/books` with a GraphQL query for only `title`. How many bytes does each return? (`curl -s ... | wc -c`)
2. **Add the field.** Add `created_at` to the client's `BOOKS_QUERY` and show it in the table. Which side had to change, the server or the client? Compare that with adding a field to a REST response.
3. **Count.** Add `bookCount: Int! @count(model: "Book")` to `type Query`, rebuild the api, and query it in GraphiQL.
4. **Full CRUD.** Use the client's playground to create a book, update its year, and delete it, all with mutations.
5. **One round-trip.** The REST client page needs two requests to show book 1 *and* the list of books. Write one GraphQL query that returns both.
