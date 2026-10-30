"""gRPC server for books, reading the same MariaDB table as the Laravel API.

books_pb2 / books_pb2_grpc are generated from protos/books.proto when the image is built
(see the Dockerfile), so they don't exist in the repo.
"""

import os
import time
from concurrent import futures

import grpc
import pymysql
from grpc_reflection.v1alpha import reflection

import books_pb2
import books_pb2_grpc


def db():
    # Same environment variables as the api service in compose.yaml.
    return pymysql.connect(
        host=os.environ["DB_HOST"],
        user=os.environ["DB_USERNAME"],
        password=os.environ["DB_PASSWORD"],
        database=os.environ["DB_DATABASE"],
        cursorclass=pymysql.cursors.DictCursor,
    )


def to_message(row):
    return books_pb2.Book(
        id=row["id"],
        title=row["title"],
        author=row["author"],
        published_year=row["published_year"] or 0,
    )


def find_books(search):
    with db() as conn, conn.cursor() as cur:
        cur.execute(
            "SELECT id, title, author, published_year FROM books WHERE title LIKE %s ORDER BY id",
            (f"%{search}%",),
        )
        return cur.fetchall()


class BookService(books_pb2_grpc.BookServiceServicer):
    def ListBooks(self, request, context):
        return books_pb2.ListBooksResponse(books=[to_message(r) for r in find_books(request.search)])

    def GetBook(self, request, context):
        with db() as conn, conn.cursor() as cur:
            cur.execute("SELECT id, title, author, published_year FROM books WHERE id = %s", (request.id,))
            row = cur.fetchone()
        if row is None:
            # gRPC's version of HTTP 404: a status code plus a message.
            context.abort(grpc.StatusCode.NOT_FOUND, f"Book {request.id} not found.")
        return to_message(row)

    def StreamBooks(self, request, context):
        # "yield" sends each book as soon as it's ready. The 0.5 s pause only
        # makes the streaming visible when you try it with grpcurl.
        for row in find_books(request.search):
            yield to_message(row)
            time.sleep(0.5)


def main():
    server = grpc.server(futures.ThreadPoolExecutor(max_workers=10))
    books_pb2_grpc.add_BookServiceServicer_to_server(BookService(), server)

    # Reflection lets tools like grpcurl and Postman ask the server which services
    # and messages it has, so you can explore it without a copy of books.proto.
    service_names = (
        books_pb2.DESCRIPTOR.services_by_name["BookService"].full_name,
        reflection.SERVICE_NAME,
    )
    reflection.enable_server_reflection(service_names, server)

    server.add_insecure_port("[::]:50051")
    server.start()
    print("gRPC BookService listening on :50051", flush=True)
    server.wait_for_termination()


if __name__ == "__main__":
    main()
