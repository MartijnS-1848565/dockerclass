# Lesson 10: MQTT

All the APIs so far work the same way: a client **asks**, the server **answers**. Something happens only when the client asks, so to find out whether a book was added, a client would have to keep asking ("polling").

MQTT turns that around. It's a **publish/subscribe** protocol:

- A **publisher** sends a message to a **topic**, such as `books/created`.
- Anyone **subscribed** to that topic receives the message, instantly.
- In between sits a **broker** that routes messages. Publishers and subscribers never talk to each other directly, and don't even know each other exist.

```
                   publish books/created                    deliver
   api  ─────────────────────────────────►  mqtt  ─────────────────────► listener   (subscribed to books/#)
(publisher)                               (broker) ────────────────────► browser    (subscribed to books/#)
                                                    ───── nothing ─────► someone subscribed only to lab/#
```

MQTT was designed for unreliable networks and tiny devices: sensors, smart meters, cars, factory machines. It's the most common protocol in IoT, and it's just as useful for pushing live updates to a web page.

## Where it lives in the lab

| | |
|---|---|
| Broker | `mqtt` container ([Eclipse Mosquitto](https://mosquitto.org)): `mqtt:1883` inside the network |
| From the browser | `ws://LAB_ADDR/mqtt` (MQTT over WebSockets, through the proxy) |
| Publisher | the `api`: every book change publishes an event |
| Subscriber | the `listener` container, and the client's live page <http://LAB_ADDR/client/mqtt> |
| Library | [php-mqtt/client](https://github.com/php-mqtt/client) in PHP, [mqtt.js](https://github.com/mqttjs/MQTT.js) in the browser |

### Topics used

| Topic | Message | Published |
|---|---|---|
| `books/created`, `books/updated`, `books/deleted` | JSON: `{"event", "book", "at"}` | on every change, QoS 1 |
| `books/count` | the number of books, e.g. `4` | on every change, **retained** |
| `lab/...` | any text | from the client's publish form |

## Try it first

1. Open <http://LAB_ADDR/client/mqtt>. The status says **connected**, and "Books right now" already shows a number.
2. In a second tab, add a book on the [REST page](http://LAB_ADDR/client/). The event appears on the MQTT page immediately, without a refresh.
3. Edit a book through [GraphQL](http://LAB_ADDR/client/graphql) and add one through [SOAP](http://LAB_ADDR/client/soap). Those produce events too.
4. In a terminal, follow the listener container:
   ```bash
   docker compose logs -f listener
   ```
5. Publish something yourself with the form at the bottom of the MQTT page, or from the command line. The broker image includes `mosquitto_pub` and `mosquitto_sub`:
   ```bash
   docker compose exec mqtt mosquitto_pub -t lab/sensor/temperature -m 21.5
   docker compose exec mqtt mosquitto_sub -t 'books/#' -v      # Ctrl+C to stop
   ```

## The broker

```text title="mqtt/mosquitto.conf"
--8<-- "mqtt/mosquitto.conf"
```

Two listeners: **1883** is plain MQTT over TCP for services, and **9001** is MQTT wrapped in WebSockets, because browsers can't open raw TCP connections. The proxy forwards the path `/mqtt` to port 9001:

```nginx
location = /mqtt {
    proxy_pass http://mqtt:9001;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_read_timeout 1h;
}
```

A WebSocket starts life as an ordinary HTTP request that asks to **upgrade** the connection. Without the `Upgrade`/`Connection` headers nginx wouldn't pass that request on, and with the default 60-second `proxy_read_timeout` it would cut quiet connections.

## Publishing: one place for every API style

The api doesn't publish from the REST controller, the GraphQL schema or the SOAP service. It publishes from an Eloquent **observer**, which runs after *any* change to a `Book`:

```php title="api/src/app/Observers/BookObserver.php"
--8<-- "api/src/app/Observers/BookObserver.php"
```

The model is linked to it with one attribute: `#[ObservedBy(BookObserver::class)]` in `app/Models/Book.php`. The publishing itself:

```php title="api/src/app/Mqtt/BookEvents.php"
--8<-- "api/src/app/Mqtt/BookEvents.php"
```

!!! tip "Decoupling"
    The api has no idea who's listening: one listener, ten, or none. You can add a new subscriber (say, a service that emails new books to a librarian) without changing a single line in the api. That's the main architectural reason to use messaging.

## Subscribing: a long-running container

A subscriber has to stay connected to receive messages, so it's a process that **never ends**. That's different from a web request, which ends as soon as the response is sent. Here it's an artisan command:

```php title="client/src/app/Console/Commands/ListenForBookEvents.php"
--8<-- "client/src/app/Console/Commands/ListenForBookEvents.php"
```

It runs in its own container, built from the **client image** but with a different command:

```yaml
  listener:
    build: ./client
    entrypoint: ["php", "artisan", "books:listen"]
    init: true
    restart: unless-stopped
```

`entrypoint:`
:   Overrides the image's `ENTRYPOINT`. The same image now runs a worker instead of a web server. One image, many roles, is a common Docker pattern (web, worker, scheduler).

`init: true`
:   The first process in a container (PID 1) gets special treatment from Linux. Without a signal handler it **ignores** the stop signal, so `docker compose stop` waits 10 seconds and then kills it. `init: true` puts a tiny init process in front that forwards signals properly. Try `time docker compose stop listener` with and without it.

`restart: unless-stopped`
:   If the broker restarts, the connection drops and the command exits. Docker starts it again automatically. Try `docker compose restart mqtt` and watch `docker compose ps`.

## Subscribing from the browser

The live page connects to the broker **directly** from your browser. The Laravel server isn't involved after it has served the page:

```html title="client/src/resources/views/mqtt/index.blade.php (script part)"
<script src="https://unpkg.com/mqtt@5.10.1/dist/mqtt.min.js"></script>
<script>
    // $brokerUrl is ws:// + the host and port this page came in on (LAB_ADDR, ...) + /mqtt,
    // filled in by MqttController.
    const client = mqtt.connect(@json($brokerUrl));

    client.on('connect', () => client.subscribe(['books/#', 'lab/#'], { qos: 1 }));

    client.on('message', (topic, payload, packet) => {
        // add a row to the table...
    });
</script>
```

Compare that with the other client pages: those make the request **from the client container** and send HTML. Here the browser itself holds an open connection to the broker.

## Key concepts

**Topics and wildcards**

Topics are paths separated by `/`. Subscribers can use wildcards (publishers can't):

| Filter | Matches | Doesn't match |
|---|---|---|
| `books/created` | `books/created` | `books/deleted` |
| `books/+` | `books/created`, `books/count` | `books/a/b` |
| `books/#` | `books/created`, `books/a/b`, ... | `lab/messages` |
| `+/temperature` | `kitchen/temperature`, `garage/temperature` | `house/kitchen/temperature` |

**Quality of Service (QoS)**

| QoS | Guarantee | Cost |
|---|---|---|
| 0 | at most once: fire and forget, may be lost | 1 message |
| 1 | at least once: confirmed, may arrive twice | 2 messages |
| 2 | exactly once | 4 messages |

The lab uses QoS 1 for book events: losing one is worse than receiving it twice.

**Retained messages**

A normal message goes to whoever is subscribed *right now*, and new subscribers miss it. A **retained** message is stored by the broker, which gives the last one to every new subscriber immediately. That's why the MQTT page knows the book count before anything changes. It's perfect for "current state" topics: the latest temperature, whether a device is online, the current count.

**Client IDs**

Every connection has a client ID, and the broker allows only **one** connection per ID. If a second client connects with an ID that's already in use, the first one is kicked off. That's why the listener includes its container's hostname in its ID.

**Persistence**

Mosquitto writes retained messages to `/mosquitto/data`, which is the `mqttdata` volume. Restart the broker (`docker compose restart mqtt`) and reload the MQTT page: the count is still there.

## Exercises

1. **Wildcards.** Run `docker compose exec mqtt mosquitto_sub -t 'books/+' -v` and `... -t '+/count' -v` in two terminals. Change some books. Which messages does each receive?
2. **A fake sensor.** Publish a temperature every 2 seconds:
   ```bash
   docker compose exec mqtt sh -c 'while true; do mosquitto_pub -t lab/sensor/temperature -m "$((18 + $(date +%s) % 8))"; sleep 2; done'
   ```
   Watch it on the MQTT page. Then publish it with `-r` (retained) and reload the page: what's different?
3. **A second subscriber.** Add another service to `compose.yaml` that runs `books:listen books/deleted` (hint: `entrypoint: ["php", "artisan", "books:listen", "books/deleted"]`). Delete a book: who gets the message? Did you have to change the api?
4. **Last will.** Make the listener announce itself on `lab/listener/status` with a retained `online` when it connects, and set a *last will* of `offline` (see `setLastWillTopic` / `setLastWillMessage` in `ConnectionSettings`). Kill the container with `docker compose kill listener` and watch the MQTT page.
5. **Passwords.** Set `allow_anonymous false` and `password_file /mosquitto/config/passwords` in `mosquitto.conf`, create the file with `mosquitto_passwd`, and give the api, listener and browser page credentials. Which messages stop arriving while you're halfway through?
6. **Think about it.** A mobile app wants to show "new book added" notifications. Should it poll `GET /api/books` every 10 seconds, or subscribe to `books/created`? What does each cost the server with 10,000 users?
