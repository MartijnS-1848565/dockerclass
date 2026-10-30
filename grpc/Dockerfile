# The gRPC BookService (Python).
# Why not Laravel? PHP can call gRPC services (with a compiled extension), but it
# can't easily *serve* gRPC: that needs a long-running HTTP/2 server, which PHP
# doesn't have out of the box. gRPC is language-neutral anyway, which is the point.
FROM python:3.12-slim

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Generate books_pb2.py (messages) and books_pb2_grpc.py (service stubs) from the contract.
COPY protos/ protos/
RUN python -m grpc_tools.protoc -I protos --python_out=. --grpc_python_out=. protos/books.proto

COPY server.py ws_bridge.py ./

EXPOSE 50051
CMD ["python", "-u", "server.py"]
