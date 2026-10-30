# Comparing the API styles

The lab serves the **same books** in four request/response styles. Here is the same request, "give me book 2", in each one:

=== "REST"

    ```bash
    curl http://LAB_ADDR/api/books/2
    ```
    ```json
    {"data":{"id":2,"title":"The Hobbit","author":"J.R.R. Tolkien","published_year":1937,"created_at":"..."}}
    ```

=== "GraphQL"

    ```bash
    curl http://LAB_ADDR/graphql -H "Content-Type: application/json" \
         -d '{"query": "{ book(id: 2) { title } }"}'
    ```
    ```json
    {"data":{"book":{"title":"The Hobbit"}}}
    ```

=== "gRPC"

    ```bash
    docker compose run --rm grpcurl -plaintext -d '{"id": 2}' grpc:50051 books.v1.BookService/GetBook
    ```
    ```json
    {"id":"2","title":"The Hobbit","author":"J.R.R. Tolkien","published_year":1937}
    ```
    (grpcurl shows JSON for readability; on the wire it's binary protobuf.)

=== "SOAP"

    ```bash
    curl http://LAB_ADDR/api/soap -H "Content-Type: text/xml" -d '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://lab.localhost/books"><SOAP-ENV:Body><ns1:GetBook><ns1:id>2</ns1:id></ns1:GetBook></SOAP-ENV:Body></SOAP-ENV:Envelope>'
    ```
    ```xml
    <SOAP-ENV:Envelope ...><SOAP-ENV:Body><ns1:GetBookResponse><ns1:book><ns1:id>2</ns1:id><ns1:title>The Hobbit</ns1:title>...</ns1:book></ns1:GetBookResponse></SOAP-ENV:Body></SOAP-ENV:Envelope>
    ```

## And MQTT?

MQTT doesn't fit "give me book 2": nobody asks for anything. Instead, the api **announces** what happened, and whoever is interested gets told:

```bash
docker compose exec mqtt mosquitto_sub -t 'books/#' -v
```
```text
books/count 4
books/updated {"event":"updated","book":{"id":2,"title":"The Hobbit",...},"at":"..."}
```

Request/response answers "what is the state now?". Publish/subscribe answers "tell me when something changes". Real systems often use both: a REST API to read and change data, plus MQTT (or another message broker) to push changes to everyone who cares.

## Side by side

| | REST | GraphQL | gRPC | SOAP | MQTT |
|---|---|---|---|---|---|
| **Pattern** | request/response | request/response | request/response (+ streams) | request/response | **publish/subscribe** |
| **You call** | resources (URLs) + HTTP verbs | one URL, with a query | methods on a service | operations, via one URL | nothing: you publish to / subscribe on topics |
| **Format** | usually JSON | JSON | Protocol Buffers (binary) | XML | anything (bytes); here JSON |
| **Transport** | HTTP/1.1 or 2 | HTTP | HTTP/2 only | HTTP (or others) | TCP, or WebSockets for browsers |
| **Contract** | optional ([OpenAPI](../lessons/13-api-documentation.md)) | required: schema | required: `.proto` | required: WSDL | none (topic names by agreement; AsyncAPI optional) |
| **Who picks the fields** | server | **client** | server (contract) | server (contract) | publisher |
| **Errors** | HTTP status codes | `errors` list, usually HTTP 200 | gRPC status codes | `<soap:Fault>` | none back to the publisher, only delivery acknowledgements (QoS) |
| **Server can push** | no (needs SSE / WebSockets) | subscriptions (extra setup) | streaming, built in | no | **that's all it does** |
| **Works in a browser** | yes | yes | no (needs gRPC-Web) | technically, painfully | yes, over WebSockets |
| **Readable by humans** | yes | yes | no (binary) | yes, verbose | depends on the payload |
| **In this lab** | `api`, `/api/books` | `api`, `/graphql` | `grpc` service | `api`, `/api/soap` | `mqtt` broker, `listener` |
| **Used from Laravel with** | `Http` facade | `Http` facade (JSON POST) | `grpc` PHP extension | built-in `SoapClient` | `php-mqtt/client` |

## When to use what

**REST**
:   The default for public web APIs. It's simple, cacheable, works everywhere, and every developer knows it. Start here unless you have a reason not to.

**GraphQL**
:   When many different clients (web, mobile, partners) need different slices of richly connected data, and you want to avoid both over-fetching and dozens of special-purpose endpoints. The cost is more complex caching, authorization per field, and the risk of expensive queries.

**gRPC**
:   For internal service-to-service traffic where speed, strict contracts and streaming matter: microservices, high-throughput backends, polyglot systems. It's not suited to calling directly from a browser.

**SOAP**
:   When you have to integrate with a system that offers it. Its strengths (formal contracts, WS-Security, transactions) still matter in finance and government, but new APIs rarely choose it.

**MQTT**
:   When things need to be **told** about changes instead of asking: IoT devices and sensors, live dashboards, notifications, and decoupling services so a publisher doesn't need to know its consumers. It isn't a way to *ask* a question; pair it with one of the others for that.

## Final exercise

Pick one feature and add it in **all four** request/response styles: for example a `pages` field on books, or a "count books" call. Then check whether the MQTT events include it automatically. Keep notes:

1. How many files did you change per style?
2. Where did you have to change the **contract** (schema, `.proto`, WSDL), and where was there none?
3. Which client (REST page, GraphQL page, SOAP page, grpcurl, MQTT page) needed changes, and which got the new data for free?
