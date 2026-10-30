<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

// php artisan books:listen
//
// A long-running subscriber. The "listener" service in compose.yaml runs this command
// using the client image, so you can follow the events with: docker compose logs -f listener
class ListenForBookEvents extends Command
{
    protected $signature = 'books:listen {topic=books/# : topic filter to subscribe to}';

    protected $description = 'Subscribe to book events on the MQTT broker and print them';

    public function handle(): void
    {
        // The client ID must be unique: if a second client connects with the same ID, the broker
        // disconnects the first one. The hostname (= container ID) makes every listener different.
        $mqtt = new MqttClient(config('mqtt.host'), config('mqtt.port'), 'book-listener-'.gethostname());

        // Keep-alive: the broker drops clients it hasn't heard from in this many seconds.
        $mqtt->connect((new ConnectionSettings)->setKeepAliveInterval(30), true);

        $topic = $this->argument('topic');
        $this->info("Connected to ".config('mqtt.host').':'.config('mqtt.port').", subscribed to {$topic}");

        // "#" matches everything below books/: books/created, books/updated, books/count, ...
        // "+" would match exactly one level: books/+ matches books/created but not books/a/b.
        $mqtt->subscribe($topic, function (string $topic, string $message, bool $retained) {
            $label = $retained ? ' (retained)' : '';
            $this->line(now()->format('H:i:s')." <comment>{$topic}</comment>{$label} {$message}");
        }, MqttClient::QOS_AT_LEAST_ONCE);

        // Wait for messages forever (until the container is stopped).
        $mqtt->loop(true);
    }
}
