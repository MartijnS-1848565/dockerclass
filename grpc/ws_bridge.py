"""gRPC over WebSockets: a bridge for the browser.

A browser can't make real gRPC calls (it has no access to HTTP/2 trailers), and gRPC doesn't
survive every proxy either (Cloudflare Tunnel talks HTTP/1.1 to a plain-http origin). A WebSocket
gets through both. So the browser sends the call as JSON over a WebSocket, this bridge makes the
real gRPC call to the grpc service, and sends the answer back the same way.

One WebSocket = one call, like one HTTP/2 stream in real gRPC:
  browser -> bridge   {"method": "GetBook", "message": {"id": 15}}
  bridge  -> browser  {"message": {...}}                      once per response message
  bridge  -> browser  {"status": "OK", "code": 0, "details": ""}   last frame, like gRPC's trailers
"""

import asyncio
import json
import os

import grpc
from google.protobuf import json_format
from websockets.asyncio.server import serve

import books_pb2
import books_pb2_grpc

GRPC_TARGET = os.environ.get("GRPC_TARGET", "grpc:50051")

# method name -> (request message type, does the server stream its answer?)
METHODS = {
    "ListBooks": (books_pb2.ListBooksRequest, False),
    "GetBook": (books_pb2.GetBookRequest, False),
    "StreamBooks": (books_pb2.ListBooksRequest, True),
}


def to_json(message):
    # preserving_proto_field_name: published_year (as in the .proto), not publishedYear.
    return json_format.MessageToDict(message, preserving_proto_field_name=True)


async def send_status(ws, code, details=""):
    await ws.send(json.dumps({"status": code.name, "code": code.value[0], "details": details}))


async def handle_call(ws):
    try:
        call = json.loads(await ws.recv())
        request_type, streaming = METHODS[call["method"]]
        request = json_format.ParseDict(call.get("message", {}), request_type())
    except KeyError:
        return await send_status(ws, grpc.StatusCode.UNIMPLEMENTED, "Unknown method.")
    except (ValueError, json_format.ParseError) as e:
        return await send_status(ws, grpc.StatusCode.INVALID_ARGUMENT, str(e))

    async with grpc.aio.insecure_channel(GRPC_TARGET) as channel:
        method = getattr(books_pb2_grpc.BookServiceStub(channel), call["method"])
        try:
            if streaming:
                # Pass each book on as soon as the grpc service sends it.
                async for message in method(request):
                    await ws.send(json.dumps({"message": to_json(message)}))
            else:
                await ws.send(json.dumps({"message": to_json(await method(request))}))
        except grpc.aio.AioRpcError as e:
            return await send_status(ws, e.code(), e.details())

    await send_status(ws, grpc.StatusCode.OK)


async def main():
    async with serve(handle_call, "0.0.0.0", 8765) as server:
        print(f"gRPC WebSocket bridge on :8765, forwarding to {GRPC_TARGET}", flush=True)
        await server.serve_forever()


if __name__ == "__main__":
    asyncio.run(main())
