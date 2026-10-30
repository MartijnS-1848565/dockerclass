"""
The Books API once more, now in Flask, and this time DOCUMENTED.

Every route's docstring contains its OpenAPI description (YAML, after the --- line).
Flasgger collects them and serves:
    /apispec_1.json   the OpenAPI document: the machine-readable contract
    /apidocs/         Swagger UI: a web page to read the docs and try every request

It works on the same books table as the Laravel api, with the same JSON shapes,
the same X-API-KEY and the same error format, so you can compare the two.

(The "# --8<--" comments mark the parts of this file that lesson 13 of the course shows.)
"""
import os
from datetime import timezone

import pymysql
from flasgger import Swagger
from flask import Flask, jsonify, request
from werkzeug.exceptions import HTTPException
from werkzeug.middleware.proxy_fix import ProxyFix

app = Flask(__name__)
# The proxy serves this app under /flask/ but strips that prefix before forwarding, and
# tells us in the X-Forwarded-Prefix header. ProxyFix reads it, so url_for() (and with it
# Swagger UI's scripts and the link to the OpenAPI document) puts /flask back in front.
app.wsgi_app = ProxyFix(app.wsgi_app, x_for=1, x_proto=1, x_prefix=1)
app.json.sort_keys = False  # keep the fields in the order we write them

API_KEY = os.environ["API_KEY"]

# --8<-- [start:template]
# Everything that's the same for the whole API: general info, how to authenticate,
# and the data shapes ("definitions") that the routes below refer to with $ref.
template = {
    "swagger": "2.0",
    # Where the routes below live as seen from the browser: behind the proxy's /flask/.
    # "Try it out" in Swagger UI sends its requests to basePath + the route's path.
    "basePath": "/flask",
    "info": {
        "title": "Books API (Flask)",
        "version": "1.0.0",
        "description": (
            "The lab's books as a REST API.\n\n"
            "Reading is open to everyone. Creating, updating and deleting need the API key "
            "in the `X-API-KEY` header: click **Authorize** and enter it once.\n\n"
            "Errors always come back as JSON with a `message`; validation errors also "
            "list the `errors` per field."
        ),
    },
    "consumes": ["application/json"],
    "produces": ["application/json"],
    "tags": [{"name": "books", "description": "Read and manage books"}],
    "securityDefinitions": {
        "ApiKey": {"type": "apiKey", "in": "header", "name": "X-API-KEY"},
    },
    "definitions": {
        "Book": {
            "type": "object",
            "properties": {
                "id": {"type": "integer", "example": 1},
                "title": {"type": "string", "example": "Dune"},
                "author": {"type": "string", "example": "Frank Herbert"},
                "published_year": {"type": "integer", "x-nullable": True, "example": 1965},
                "created_at": {"type": "string", "format": "date-time", "example": "2026-10-21T13:49:28+00:00"},
            },
        },
        "BookInput": {
            "type": "object",
            "required": ["title", "author"],
            "properties": {
                "title": {"type": "string", "maxLength": 255, "example": "Dune"},
                "author": {"type": "string", "maxLength": 255, "example": "Frank Herbert"},
                "published_year": {"type": "integer", "minimum": 0, "maximum": 2100,
                                   "x-nullable": True, "example": 1965},
            },
        },
        "BookChanges": {
            "type": "object",
            "description": "Only the fields you send are changed.",
            "properties": {
                "title": {"type": "string", "maxLength": 255, "example": "Dune Messiah"},
                "author": {"type": "string", "maxLength": 255},
                "published_year": {"type": "integer", "minimum": 0, "maximum": 2100,
                                   "x-nullable": True, "example": 1969},
            },
        },
        "BookResponse": {
            "type": "object",
            "properties": {"data": {"$ref": "#/definitions/Book"}},
        },
        "BookListResponse": {
            "type": "object",
            "properties": {"data": {"type": "array", "items": {"$ref": "#/definitions/Book"}}},
        },
        "Error": {
            "type": "object",
            "properties": {"message": {"type": "string", "example": "Book 99 not found."}},
        },
        "ValidationError": {
            "type": "object",
            "properties": {
                "message": {"type": "string", "example": "The given data was invalid."},
                "errors": {
                    "type": "object",
                    "additionalProperties": {"type": "array", "items": {"type": "string"}},
                    "example": {"title": ["The title field is required."]},
                },
            },
        },
    },
}

swagger = Swagger(app, template=template)
# --8<-- [end:template]


def db():
    return pymysql.connect(
        host=os.environ.get("DB_HOST", "db"),
        user=os.environ["DB_USERNAME"],
        password=os.environ["DB_PASSWORD"],
        database=os.environ["DB_DATABASE"],
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True,
    )


def to_json(row):
    """A database row as the API shows it (the same fields as Laravel's BookResource)."""
    return {
        "id": row["id"],
        "title": row["title"],
        "author": row["author"],
        "published_year": row["published_year"],
        "created_at": row["created_at"].replace(tzinfo=timezone.utc).isoformat() if row["created_at"] else None,
    }


def find(cursor, book_id):
    cursor.execute("SELECT * FROM books WHERE id = %s", (book_id,))
    return cursor.fetchone()


def not_found(book_id):
    return jsonify(message=f"Book {book_id} not found."), 404


def wrong_key():
    """None if the X-API-KEY header is right, otherwise the 401 response."""
    if request.headers.get("X-API-KEY") != API_KEY:
        return jsonify(message="Missing or invalid X-API-KEY header."), 401
    return None


def validate(data, partial):
    """The same rules as the Laravel api. partial=True: only check the fields that were sent."""
    errors = {}
    for field in ("title", "author"):
        if field not in data:
            if not partial:
                errors[field] = [f"The {field} field is required."]
        elif not isinstance(data[field], str) or not data[field].strip():
            errors[field] = [f"The {field} field is required."]
        elif len(data[field]) > 255:
            errors[field] = [f"The {field} field must not be greater than 255 characters."]

    year = data.get("published_year")
    if year is not None and (not isinstance(year, int) or isinstance(year, bool) or not 0 <= year <= 2100):
        errors["published_year"] = ["The published year field must be an integer between 0 and 2100."]
    return errors


def invalid(errors):
    return jsonify(message="The given data was invalid.", errors=errors), 422


@app.errorhandler(HTTPException)
def error_as_json(error):
    """Flask's own errors (no matching route, wrong method, server error) are HTML pages.
    The docs promise JSON for every error, so under /api answer in JSON too.
    (Schemathesis found this one: /api/books/abc gave an HTML 404.)"""
    if request.path.startswith("/api/"):
        # Keep the headers Flask adds (e.g. "Allow: GET, POST" on a 405), but not its HTML content type.
        headers = [(name, value) for name, value in error.get_headers() if name.lower() != "content-type"]
        return jsonify(message=error.description), error.code, headers
    return error


@app.get("/api/books")
def list_books():
    """List all books
    Optionally only the books whose title contains a search term.
    ---
    operationId: listBooks
    tags: [books]
    parameters:
      - name: search
        in: query
        type: string
        required: false
        description: Only books whose title contains this text (case-insensitive).
        x-example: dune   # Swagger 2.0 has no "example" for parameters; OpenAPI 3 does
    responses:
      200:
        description: The books, ordered by id. An empty list if nothing matches.
        schema:
          $ref: '#/definitions/BookListResponse'
    """
    search = request.args.get("search", "")
    with db() as conn, conn.cursor() as cursor:
        cursor.execute("SELECT * FROM books WHERE title LIKE %s ORDER BY id", (f"%{search}%",))
        return jsonify(data=[to_json(row) for row in cursor.fetchall()])


@app.get("/api/books/<int:book_id>")
def show_book(book_id):
    """Get one book
    ---
    operationId: getBook
    tags: [books]
    parameters:
      - name: book_id
        in: path
        type: integer
        required: true
        description: The book's id (see the list for existing ids).
    responses:
      200:
        description: The book.
        schema:
          $ref: '#/definitions/BookResponse'
      404:
        description: There's no book with this id.
        schema:
          $ref: '#/definitions/Error'
    """
    with db() as conn, conn.cursor() as cursor:
        row = find(cursor, book_id)
        return jsonify(data=to_json(row)) if row else not_found(book_id)


# --8<-- [start:create]
@app.post("/api/books")
def create_book():
    """Add a book
    Needs the API key. The answer is the new book, including the id it got.
    ---
    operationId: createBook
    tags: [books]
    security:
      - ApiKey: []
    parameters:
      - name: book
        in: body
        required: true
        schema:
          $ref: '#/definitions/BookInput'
    responses:
      201:
        description: The book was added.
        schema:
          $ref: '#/definitions/BookResponse'
      401:
        description: The X-API-KEY header is missing or wrong.
        schema:
          $ref: '#/definitions/Error'
      422:
        description: The input is invalid. `errors` says what's wrong, per field.
        schema:
          $ref: '#/definitions/ValidationError'
    """
    if response := wrong_key():
        return response
    data = request.get_json(silent=True) or {}
    if errors := validate(data, partial=False):
        return invalid(errors)

    with db() as conn, conn.cursor() as cursor:
        cursor.execute(
            "INSERT INTO books (title, author, published_year, created_at, updated_at)"
            " VALUES (%s, %s, %s, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            (data["title"], data["author"], data.get("published_year")),
        )
        return jsonify(data=to_json(find(cursor, cursor.lastrowid))), 201
# --8<-- [end:create]


@app.put("/api/books/<int:book_id>")
def update_book(book_id):
    """Change a book
    Needs the API key. Send only the fields you want to change.
    ---
    operationId: updateBook
    tags: [books]
    security:
      - ApiKey: []
    parameters:
      - name: book_id
        in: path
        type: integer
        required: true
      - name: changes
        in: body
        required: true
        schema:
          $ref: '#/definitions/BookChanges'
    responses:
      200:
        description: The book, after the change.
        schema:
          $ref: '#/definitions/BookResponse'
      401:
        description: The X-API-KEY header is missing or wrong.
        schema:
          $ref: '#/definitions/Error'
      404:
        description: There's no book with this id.
        schema:
          $ref: '#/definitions/Error'
      422:
        description: The input is invalid.
        schema:
          $ref: '#/definitions/ValidationError'
    """
    if response := wrong_key():
        return response
    data = request.get_json(silent=True) or {}
    if errors := validate(data, partial=True):
        return invalid(errors)

    changes = {field: data[field] for field in ("title", "author", "published_year") if field in data}
    with db() as conn, conn.cursor() as cursor:
        if not find(cursor, book_id):
            return not_found(book_id)
        if changes:
            assignments = ", ".join(f"{field} = %s" for field in changes)  # field names from our own list
            cursor.execute(
                f"UPDATE books SET {assignments}, updated_at = UTC_TIMESTAMP() WHERE id = %s",
                (*changes.values(), book_id),
            )
        return jsonify(data=to_json(find(cursor, book_id)))


@app.delete("/api/books/<int:book_id>")
def delete_book(book_id):
    """Delete a book
    Needs the API key.
    ---
    operationId: deleteBook
    tags: [books]
    security:
      - ApiKey: []
    parameters:
      - name: book_id
        in: path
        type: integer
        required: true
    responses:
      204:
        description: The book was deleted. The answer has no body.
      401:
        description: The X-API-KEY header is missing or wrong.
        schema:
          $ref: '#/definitions/Error'
      404:
        description: There's no book with this id.
        schema:
          $ref: '#/definitions/Error'
    """
    if response := wrong_key():
        return response
    with db() as conn, conn.cursor() as cursor:
        cursor.execute("DELETE FROM books WHERE id = %s", (book_id,))
        return ("", 204) if cursor.rowcount else not_found(book_id)
