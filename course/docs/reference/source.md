# Source code

Every file this lab adds to the two Laravel skeletons, plus the gRPC service, the MQTT broker config and the Kubernetes and Swarm files, embedded from the repo at build time.

## api

### bootstrap/app.php

```php title="api/src/bootstrap/app.php"
--8<-- "api/src/bootstrap/app.php"
```

### config/api.php

```php title="api/src/config/api.php"
--8<-- "api/src/config/api.php"
```

### routes/api.php

```php title="api/src/routes/api.php"
--8<-- "api/src/routes/api.php"
```

### Middleware/RequireApiKey.php

```php title="api/src/app/Http/Middleware/RequireApiKey.php"
--8<-- "api/src/app/Http/Middleware/RequireApiKey.php"
```

### Controllers/BookController.php

```php title="api/src/app/Http/Controllers/BookController.php"
--8<-- "api/src/app/Http/Controllers/BookController.php"
```

### Resources/BookResource.php

```php title="api/src/app/Http/Resources/BookResource.php"
--8<-- "api/src/app/Http/Resources/BookResource.php"
```

### Models/Book.php

```php title="api/src/app/Models/Book.php"
--8<-- "api/src/app/Models/Book.php"
```

### Migration

```php title="api/src/database/migrations/2026_01_01_000000_create_books_table.php"
--8<-- "api/src/database/migrations/2026_01_01_000000_create_books_table.php"
```

### Seeder

```php title="api/src/database/seeders/DatabaseSeeder.php"
--8<-- "api/src/database/seeders/DatabaseSeeder.php"
```

### graphql/schema.graphql

```graphql title="api/src/graphql/schema.graphql"
--8<-- "api/src/graphql/schema.graphql"
```

### GraphQL/Directives/RequireApiKeyDirective.php

```php title="api/src/app/GraphQL/Directives/RequireApiKeyDirective.php"
--8<-- "api/src/app/GraphQL/Directives/RequireApiKeyDirective.php"
```

### Controllers/SoapController.php

```php title="api/src/app/Http/Controllers/SoapController.php"
--8<-- "api/src/app/Http/Controllers/SoapController.php"
```

### Soap/BookSoapService.php

```php title="api/src/app/Soap/BookSoapService.php"
--8<-- "api/src/app/Soap/BookSoapService.php"
```

### resources/soap/books.wsdl

```xml title="api/src/resources/soap/books.wsdl"
--8<-- "api/src/resources/soap/books.wsdl"
```

### Observers/BookObserver.php

```php title="api/src/app/Observers/BookObserver.php"
--8<-- "api/src/app/Observers/BookObserver.php"
```

### Mqtt/BookEvents.php

```php title="api/src/app/Mqtt/BookEvents.php"
--8<-- "api/src/app/Mqtt/BookEvents.php"
```

## client

### bootstrap/app.php

```php title="client/src/bootstrap/app.php"
--8<-- "client/src/bootstrap/app.php"
```

### config/bookapi.php

```php title="client/src/config/bookapi.php"
--8<-- "client/src/config/bookapi.php"
```

### routes/web.php

```php title="client/src/routes/web.php"
--8<-- "client/src/routes/web.php"
```

### Controllers/BookController.php

```php title="client/src/app/Http/Controllers/BookController.php"
--8<-- "client/src/app/Http/Controllers/BookController.php"
```

### views/books/index.blade.php

```blade title="client/src/resources/views/books/index.blade.php"
--8<-- "client/src/resources/views/books/index.blade.php"
```

### views/books/edit.blade.php

```blade title="client/src/resources/views/books/edit.blade.php"
--8<-- "client/src/resources/views/books/edit.blade.php"
```

### Controllers/GraphqlController.php

```php title="client/src/app/Http/Controllers/GraphqlController.php"
--8<-- "client/src/app/Http/Controllers/GraphqlController.php"
```

### Controllers/SoapController.php

```php title="client/src/app/Http/Controllers/SoapController.php"
--8<-- "client/src/app/Http/Controllers/SoapController.php"
```

### Controllers/MqttController.php

```php title="client/src/app/Http/Controllers/MqttController.php"
--8<-- "client/src/app/Http/Controllers/MqttController.php"
```

### Console/Commands/ListenForBookEvents.php

```php title="client/src/app/Console/Commands/ListenForBookEvents.php"
--8<-- "client/src/app/Console/Commands/ListenForBookEvents.php"
```

### views/mqtt/index.blade.php

```blade title="client/src/resources/views/mqtt/index.blade.php"
--8<-- "client/src/resources/views/mqtt/index.blade.php"
```

## mqtt

### mosquitto.conf

```text title="mqtt/mosquitto.conf"
--8<-- "mqtt/mosquitto.conf"
```

## grpc

### protos/books.proto

```protobuf title="grpc/protos/books.proto"
--8<-- "grpc/protos/books.proto"
```

### server.py

```python title="grpc/server.py"
--8<-- "grpc/server.py"
```

## flask

### app.py (the documented REST API)

```python title="flask/app.py"
--8<-- "flask/app.py"
```

## k8s

### whoami/index.php (the page, used by Kubernetes and Swarm)

```php title="whoami/index.php"
--8<-- "whoami/index.php"
```

### cluster.yaml

```yaml title="k8s/cluster.yaml"
--8<-- "k8s/cluster.yaml"
```

### manifests/

=== "deployment.yaml"

    ```yaml
    --8<-- "k8s/manifests/deployment.yaml"
    ```

=== "service.yaml"

    ```yaml
    --8<-- "k8s/manifests/service.yaml"
    ```

=== "ingress.yaml"

    ```yaml
    --8<-- "k8s/manifests/ingress.yaml"
    ```

### tools/lab-k8s.sh

```sh title="k8s/tools/lab-k8s.sh"
--8<-- "k8s/tools/lab-k8s.sh"
```

## swarm

### stack.yaml

```yaml title="swarm/stack.yaml"
--8<-- "swarm/stack.yaml"
```

### lab-swarm.sh

```sh title="swarm/lab-swarm.sh"
--8<-- "swarm/lab-swarm.sh"
```

## Dockerfiles

=== "api"

    ```dockerfile
    --8<-- "api/Dockerfile"
    ```

=== "client"

    ```dockerfile
    --8<-- "client/Dockerfile"
    ```

=== "grpc"

    ```dockerfile
    --8<-- "grpc/Dockerfile"
    ```

=== "flask"

    ```dockerfile
    --8<-- "flask/Dockerfile"
    ```

=== "k8s toolbox"

    ```dockerfile
    --8<-- "k8s/tools/Dockerfile"
    ```

=== "whoami (k8s + swarm)"

    ```dockerfile
    --8<-- "whoami/Dockerfile"
    ```

=== "course"

    ```dockerfile
    --8<-- "course/Dockerfile"
    ```

=== "lesson-01-hello"

    ```dockerfile
    --8<-- "lesson-01-hello/Dockerfile"
    ```
