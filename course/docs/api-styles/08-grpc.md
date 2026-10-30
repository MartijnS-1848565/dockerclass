# Lesson 8: gRPC

gRPC is made for **programs talking to programs**. Instead of calling a URL, you call a **method** on another service, almost as if it were a function in your own code:

```
BookService.GetBook({ id: 2 })  →  Book { id: 2, title: "The Hobbit", ... }
```

Three things make gRPC different from REST:

- **A strict contract.** Both sides share one file (`books.proto`) that lists every method and every field. If it's not in the contract, you can't send it.
- **Binary messages.** Messages travel as **Protocol Buffers**: compact bytes instead of JSON text. Fast, but not something you can read in a browser tab.
- **Streaming.** One request can get many answers, sent one by one over the same connection.

## Where it lives in the lab

| | |
|---|---|
| Service | `grpc` container, port `50051` (inside the Docker network) |
| Contract | `grpc/protos/books.proto` |
| Server code | `grpc/server.py` (Python) |
| CLI client | `grpcurl`, a compose service you run when you need it |
| From your machine | Postman or grpcurl on `localhost:8080`, through the proxy |
| Example client | the client's gRPC page, <http://LAB_ADDR/client/grpc> |

!!! info "Why Python and not Laravel?"
    PHP can *call* gRPC services, but *serving* gRPC needs a server that keeps HTTP/2 connections open, and PHP doesn't have one out of the box. That's fine: the `.proto` contract works in every language, so each service can use whatever language suits it. This one reads the same `books` table as the Laravel API.

## The contract: books.proto

```protobuf title="grpc/protos/books.proto"
--8<-- "grpc/protos/books.proto"
```

Three things to notice:

- **Every field has a number** (`= 1`, `= 2`). Only the number travels over the network, not the name. That keeps messages small, and it's why you must never renumber a field.
- **Numbers can't be null.** An unknown year arrives as `0`. The client has to know that `0` means "unknown".
- **`stream Book`** means the server answers with many books, one at a time.

## Code is generated from the contract

```dockerfile title="grpc/Dockerfile"
--8<-- "grpc/Dockerfile"
```

`grpc_tools.protoc` reads `books.proto` and writes the Python code for you: the message classes (`books_pb2.py`) and a skeleton of the service (`books_pb2_grpc.py`). The server only fills in what each method does:

```python title="grpc/server.py"
--8<-- "grpc/server.py"
```

## Try it with grpcurl

`grpcurl` is "curl for gRPC". It has a **profile** in `compose.yaml`, so `docker compose up` skips it, and you start it only when you need it:

```bash
G="docker compose run --rm grpcurl -plaintext"

$G grpc:50051 list                                  # which services exist?
$G grpc:50051 describe books.v1.BookService         # which methods?
$G grpc:50051 describe books.v1.Book                # what does a Book look like?

$G -d '{}' grpc:50051 books.v1.BookService/ListBooks
$G -d '{"search": "dune"}' grpc:50051 books.v1.BookService/ListBooks
$G -d '{"id": 2}' grpc:50051 books.v1.BookService/GetBook
$G -d '{"id": 999}' grpc:50051 books.v1.BookService/GetBook   # Code: NotFound
```

How does grpcurl know the methods without the `.proto` file? The server supports **reflection**: tools can ask it "what can you do?". Postman uses the same trick.

Now watch **streaming**: the books arrive one by one, half a second apart:

```bash
$G -d '{}' grpc:50051 books.v1.BookService/StreamBooks
```

!!! tip "`profiles:` in compose"
    A service with `profiles: ["tools"]` only starts when you ask for it: `docker compose run grpcurl ...` or `docker compose --profile tools up`. Handy for one-off tools such as CLIs, database migrators and test runners.

## Try it with Postman

1. New → **gRPC**.
2. URL: `localhost:8080`, and leave TLS **off** (the lock icon stays open).
3. Service definition: **Using server reflection**. Postman asks the server for its methods.
4. Pick a method, click **Use Example Message** (or type `{"id": 2}` for `GetBook`), and click **Invoke**.

`StreamBooks` shows the books arriving one by one, just like in grpcurl.

!!! note "Lab behind Cloudflare?"
    This works for a lab you reach directly, such as one on your own laptop. A lab on a domain behind **Cloudflare** needs an extra step: see [Postman through Cloudflare](#postman-through-cloudflare-websocat) at the bottom of this page.

## Consume it from a web page: the client's gRPC page

The client has a page that calls the same `BookService`: <http://LAB_ADDR/client/grpc>.

1. Pick a method: `ListBooks`, `GetBook` or `StreamBooks`.
2. Edit the request message (JSON), for example `{"id": 2}`.
3. Click **Invoke** and watch the answer arrive:
    - **← message**: a response message. For `StreamBooks` you get one per book, half a second apart.
    - **← status**: the result of the call, for example `OK (0)` or `NOT_FOUND (5): Book 999 not found.`

A browser can't make real gRPC calls by itself, so the page sends each call through a small helper on the server. How that works is in [the extra info](#the-browser-bridge-grpc-ws) at the bottom.

## gRPC status codes

| gRPC | Roughly the HTTP equivalent |
|---|---|
| `OK` | 200 |
| `INVALID_ARGUMENT` | 400 / 422 |
| `UNAUTHENTICATED` | 401 |
| `PERMISSION_DENIED` | 403 |
| `NOT_FOUND` | 404 |
| `UNIMPLEMENTED` | 501 (the method doesn't exist) |
| `UNAVAILABLE` | 503 (the client should retry) |
| `DEADLINE_EXCEEDED` | 504 (the call took longer than the client allowed) |

## Exercises

1. **Look at the wire.** Run `ListBooks` through grpcurl with `-v`. Which headers do you see? What's the `content-type`? Can you spot the trailers?
2. **Use the client page.** On <http://LAB_ADDR/client/grpc>, call `StreamBooks`, then `GetBook` with an id that doesn't exist. What arrives in each case? Open the browser's developer tools (Network → WS) and find the same messages there.
3. **Add an RPC.** Add `rpc CountBooks (ListBooksRequest) returns (CountBooksResponse);` with `message CountBooksResponse { int32 count = 1; }` to the proto, implement it in `server.py`, rebuild `grpc`, and call it with grpcurl and Postman. `list` and the Postman method list show it straight away, thanks to reflection.
4. **Break the contract.** Change `string title = 2;` to `string title = 5;` in the proto and rebuild only the server. A client built with the old proto now gets an empty title. Why? Change it back.
5. **Deadlines.** Run `StreamBooks` with `-max-time 1`. What happens, and which status code do you get?
6. **gRPC from Laravel (advanced).** Add `install-php-extensions grpc protobuf` (from [mlocati/docker-php-extension-installer](https://github.com/mlocati/docker-php-extension-installer)) to `client/Dockerfile`, generate PHP classes from `books.proto`, and call `GetBook` from a new client page. Expect a long first build: the extension is compiled from source.

---

## Extra info: how the setup works

Everything below is background: how the lab gets gRPC to your tools and your browser. You don't need it to use gRPC, but it explains what happens behind the scenes.

### What a gRPC call looks like on the wire

A gRPC call is an **HTTP/2 POST**, with a few rules on top:

```
POST /books.v1.BookService/GetBook        ← the path is always /<package>.<Service>/<Method>
content-type: application/grpc
(body: the request, as protobuf bytes)

        ↓ the server answers ↓

200 OK                                    ← headers
(body: the response, as protobuf bytes)   ← for StreamBooks: many messages
grpc-status: 0                            ← trailers: the real result, sent AFTER the body
```

The last part matters. The real result of the call (`OK`, `NOT_FOUND`, ...) comes in **trailers**: headers that arrive *after* the body. Think of the "P.S." at the bottom of a letter. Trailers exist in HTTP/2, but in practice not in HTTP/1.1. That's why gRPC needs HTTP/2 **all the way**, and that explains everything below.

### Through the reverse proxy

nginx forwards gRPC with `grpc_pass` instead of `proxy_pass`. Because the client always sends the exact path `/books.v1.BookService/...`, you can't put gRPC under a prefix like `/grpc/`. The proxy matches on the service names instead (plus the reflection service):

```nginx
location ~ ^/(books\.v1\.|grpc\.reflection\.) {
    grpc_pass grpc://grpc:50051;
}
```

`proxy/conf.d/default.conf` also has `http2 on;`. On a plain-HTTP port nginx has to choose HTTP/2 for the **whole port**, before it has read the request, so it can't be switched on for the gRPC location only. The other sites keep working over HTTP/1.1.

### The browser bridge: grpc-ws

A web page **can't** make real gRPC calls. JavaScript has no way to read trailers, and it can't open a raw connection to port `50051` either. MQTT had the same problem ([lesson 10](10-mqtt.md)), and the fix is the same: a **WebSocket**.

The `grpc-ws` container is a small **bridge**. The browser talks JSON over a WebSocket to the bridge, and the bridge makes the real gRPC call:

```
browser ──WebSocket (JSON)──► proxy /grpc-ws ──► grpc-ws (bridge) ──real gRPC (HTTP/2)──► grpc
```

**One WebSocket = one call**, just like one HTTP/2 stream is one call in real gRPC. The frames:

```
browser → bridge   {"method": "GetBook", "message": {"id": 15}}
bridge  → browser  {"message": {"id": "15", "title": "Dune", ...}}     once per response message
bridge  → browser  {"status": "OK", "code": 0, "details": ""}          last frame: the "trailers"
```

The last frame does the job of the trailers. The bridge is ~70 lines of Python. It uses the **same image** as the `grpc` service (so it gets the same generated code), but runs a different script:

```python title="grpc/ws_bridge.py"
--8<-- "grpc/ws_bridge.py"
```

In `compose.yaml` that's just `build: ./grpc` with a different `command:`. The proxy forwards `/grpc-ws` like it forwards `/mqtt`, with the WebSocket `Upgrade` headers. Adding a method to the proto (like `CountBooks`)? Then add it to `METHODS` in `ws_bridge.py` and to the page's method list too.

!!! info "This is how real projects do it too"
    Browsers can't speak plain gRPC anywhere, so real projects also put something in between: **gRPC-Web** (a variant of gRPC that works over HTTP/1.1, with a proxy like Envoy), or a JSON/REST gateway. Our bridge is a simple, home-made version of the same idea.

### gRPC behind Cloudflare Tunnel

If you put the lab online with a **Cloudflare Tunnel** (`cloudflared`), websites, the REST API and the WebSockets all work. Plain gRPC **doesn't**: Postman can't connect to such a domain directly.

```
Postman ──HTTP/2──► Cloudflare ──tunnel──► cloudflared ──HTTP/1.1──► nginx ──► grpc
                                                         ↑ trailers get lost here
```

`cloudflared` passes requests on to an `http://` address over **HTTP/1.1**, so the trailers disappear. Errors like `NOT_FOUND` still work (an error can be sent without a body, in the normal headers), but every *successful* call fails with:

```
server closed the stream without sending trailers
```

The way around it: hide gRPC from everything in between, by putting it inside a **WebSocket**. A WebSocket starts as a normal HTTP request, so Cloudflare and the tunnel pass it on, and once it's open it carries any bytes you like without looking at them. The lab does that in two ways:

| | **Translate**: the bridge, `/grpc-ws` | **Wrap**: the tunnel, `/grpc-tunnel` |
|---|---|---|
| Inside the WebSocket | JSON messages | the genuine gRPC bytes (HTTP/2 + protobuf) |
| At the server | `grpc-ws` reads the JSON and makes a **new** gRPC call | `grpc-tunnel` ([websockify](https://github.com/novnc/websockify)) unwraps the bytes and passes them on **unchanged** |
| Client | a browser page | Postman, grpcurl, any real gRPC client, plus a small wrapper on its machine |
| Reflection, streaming, status codes | in the bridge's own JSON format | real gRPC, exactly as without the tunnel |

In both cases the `grpc` service itself never sees a WebSocket: something in front of it always takes the WebSocket off first. That's why `server.py` didn't change at all.

### Postman through Cloudflare: websocat

Postman can't open a WebSocket for a gRPC request itself, so you run a small wrapper on your own machine: [websocat](https://github.com/vi/websocat). It opens a local port and sends everything that arrives there into the WebSocket:

```
Postman ──gRPC──► websocat (localhost:9090) ══WebSocket: the same gRPC bytes══► Cloudflare ══► cloudflared
        ──► nginx /grpc-tunnel ──► grpc-tunnel (websockify) ──gRPC──► grpc
```

**Install websocat** (once). It's a single program, no installer:

=== "Linux"

    ```bash
    mkdir -p ~/.local/bin
    curl -fL -o ~/.local/bin/websocat \
      "https://github.com/vi/websocat/releases/download/v1.13.0/websocat.$(uname -m)-unknown-linux-musl"
    chmod +x ~/.local/bin/websocat
    websocat --version          # "command not found"? Open a new terminal, or use ~/.local/bin/websocat
    ```

    `$(uname -m)` picks the right build: `x86_64` for most laptops, `aarch64` for ARM (e.g. a Raspberry Pi).

=== "macOS"

    ```bash
    brew install websocat
    websocat --version
    ```

    No Homebrew? Download it directly (Apple Silicon: `aarch64`, Intel: `x86_64`):

    ```bash
    mkdir -p ~/.local/bin
    curl -fL -o ~/.local/bin/websocat \
      "https://github.com/vi/websocat/releases/download/v1.13.0/websocat.$(uname -m | sed 's/arm64/aarch64/')-apple-darwin"
    chmod +x ~/.local/bin/websocat
    ~/.local/bin/websocat --version
    ```

=== "Windows (PowerShell)"

    ```powershell
    Invoke-WebRequest -OutFile "$HOME\websocat.exe" `
      "https://github.com/vi/websocat/releases/download/v1.13.0/websocat.x86_64-pc-windows-gnu.exe"
    & "$HOME\websocat.exe" --version
    ```

    Then run it as `& "$HOME\websocat.exe" ...` instead of `websocat ...`. Windows may ask whether you want to allow it on the network: private networks are enough, since it only listens on `127.0.0.1`.

**Start it** and leave the terminal open:

```bash
websocat -E --binary tcp-l:127.0.0.1:9090 wss://LAB_ADDR/grpc-tunnel
```

- `--binary`: send the bytes as **binary** WebSocket messages. The default is text, and gRPC's bytes aren't valid text.
- `-E`: when Postman closes a connection, close the matching WebSocket too, so connections don't pile up.
- Behind Cloudflare it **must** be `wss://`: plain `ws://` doesn't get through. For a lab on your own laptop you can try it with `ws://localhost:8080/grpc-tunnel`.

Then in Postman: New → gRPC, URL `localhost:9090`, TLS **off** (websocat already uses `wss://` to Cloudflare), **Using server reflection**. Everything works, including streaming and the status codes, because the trailers never leave HTTP/2: the WebSocket just carries them along.

`grpc-tunnel` is one line in `compose.yaml`: the same image as `grpc`, running `websockify 0.0.0.0:8766 grpc:50051`. The proxy forwards `/grpc-tunnel` to it with the WebSocket `Upgrade` headers.

| | Runs on | How |
|---|---|---|
| `grpc-tunnel` (unwraps) | the machine that hosts the lab, next to `grpc` | a container, started by `docker compose up` |
| websocat (wraps) | the machine where Postman runs | natively: one downloaded binary, no Docker, so Postman can reach it on `localhost` |

??? info "Why is one URL enough? (click to open)"
    Cloudflare decides per **hostname** whether a route is HTTP or TCP. A TCP route therefore needs a hostname of its own, because the website needs HTTP. A WebSocket is just HTTP to Cloudflare, so `/grpc-tunnel` lives on the same hostname as everything else, and nginx sorts the traffic by **path**: `/course/`, `/mqtt`, `/grpc-ws`, `/grpc-tunnel`. websocat takes over the wrapping that `cloudflared access tcp` would do, and `grpc-tunnel` the unwrapping that `cloudflared` itself would do on a TCP route. Cloudflare only carries the WebSocket.

??? question "Why not one service for both? (click to open)"
    `grpc-ws` and `grpc-tunnel` both receive a WebSocket, but they expect different things inside. Send genuine gRPC bytes to `grpc-ws` and it fails on the first byte: it only knows its own JSON. Send the page's JSON to `grpc-tunnel` and it passes it straight on to `grpc`, which only understands HTTP/2. One service could check what arrives and pick the right job, but keeping them apart keeps each one simple.

??? info "Other ways around it (click to open)"
    - **A TCP route in the tunnel.** Route a separate hostname as **TCP** instead of HTTP, and run `cloudflared access tcp --hostname <grpc-host> --url localhost:9090` on the client. Same idea (cloudflared does the wrapping and unwrapping) but it needs its own hostname, and every client needs `cloudflared`.
    - **HTTPS to the origin.** Give nginx a TLS port and turn on **HTTP2 connection** for the route. Then `cloudflared` uses HTTP/2 and keeps the trailers, and Postman can connect directly. The catch: a certificate on your own machine.

??? tip "`ws://` or `wss://`? (click to open)"
    Through Cloudflare the page is loaded over **https**, and a browser blocks unencrypted `ws://` connections from an https page. The gRPC and MQTT pages pick `wss://` automatically when the page itself is https. You don't need your own certificate for that: Cloudflare handles the encryption, and the tunnel still delivers a plain `ws://` connection to nginx.

### Extra exercise: translate or wrap?

Start websocat towards `ws://localhost:8080/grpc-tunnel` (or `wss://<domain>/grpc-tunnel` for a lab behind Cloudflare) and call `StreamBooks` from Postman through `localhost:9090`. Then try the same through `/grpc-ws` with a Postman **WebSocket** request (send `{"method": "StreamBooks", "message": {}}`). What's the difference in what you send and what you get back? Which one shows the methods via reflection, and why?
