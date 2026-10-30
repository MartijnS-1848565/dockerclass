# Exercises

After each code change, rebuild the service you changed, e.g. `docker compose up -d --build api`.

## 1. Add a field

Give books a `pages` column. You'll need:

- a new migration in `api/src/database/migrations/` (a new file; don't edit the existing one)
- `pages` in `$fillable`, the validation rules, and `BookResource`
- the client's form and table

Which of these files does the **client** need to change, and why doesn't it need a migration?

## 2. New endpoint

Add `GET /api/authors` to the API, returning the distinct author names. Show them on a new client page.

??? tip "Hint"
    `Book::query()->distinct()->orderBy('author')->pluck('author')` returns them as a list.

## 3. Wrong key

In `compose.yaml`, change `BOOK_API_KEY` for the **client only** to `wrong`, run `docker compose up -d`, and try to add a book. What status code comes back, and what does the page show? Improve the client so it shows a friendly message for a 401.

## 4. Production mode

Set `APP_DEBUG: "false"` for the api and request <http://LAB_ADDR/api/books/999> again. Compare the JSON before and after. Why should debug mode never be on in production?

## 5. Pagination

Change `index()` in the API to return `BookResource::collection(Book::paginate(5))`. Look at the new `links` and `meta` keys in the JSON, then add "next/previous" links to the client.

## 6. Consume a public API

Add a page to the client that searches <https://openlibrary.org/search.json?q=dune>. What changes compared to calling `http://api:8000`, and what stays the same?

## 7. Expose the API without the proxy

Add `ports: ["8001:8000"]` to the api service and open <http://LAB_HOST:8001/api/books>. Then remove it again. Why is a single entry point (the proxy) usually nicer?

## 8. Scale

Run `docker compose up -d --scale client=3` and check `docker compose ps`. Why does this work for `client`, but would it fail if `client` had a `ports:` mapping?

## 9. A new page in this course

Write a page for this course that explains what you built in exercise 2, embed your new controller with `--8<--`, add it to `nav:` in `course/mkdocs.yml`, and rebuild `course`.
