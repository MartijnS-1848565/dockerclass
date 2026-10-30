<?php

namespace App\Mqtt;

use App\Models\Book;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use Throwable;

// Publishes "something happened to a book" messages to the MQTT broker.
//
// Topics:
//   books/created, books/updated, books/deleted   one message per change (QoS 1)
//   books/count                                    the current number of books (retained)
class BookEvents
{
    public static function publish(string $event, Book $book): void
    {
        $payload = json_encode([
            'event' => $event,
            'book' => $book->only('id', 'title', 'author', 'published_year'),
            'at' => now()->toIso8601String(),
        ]);

        try {
            $mqtt = new MqttClient(config('mqtt.host'), config('mqtt.port'), 'book-api-'.uniqid());
            $mqtt->connect((new ConnectionSettings)->setConnectTimeout(2), true);

            // QoS 1 = "at least once": the broker confirms it received the message.
            $mqtt->publish("books/{$event}", $payload, MqttClient::QOS_AT_LEAST_ONCE);

            // Retained: the broker keeps the LAST message on this topic and hands it to every
            // new subscriber straight away, so they know the count without waiting for a change.
            $mqtt->publish('books/count', (string) Book::count(), MqttClient::QOS_AT_LEAST_ONCE, true);

            // Wait until the broker has confirmed both messages, then hang up.
            $mqtt->loop(true, true);
            $mqtt->disconnect();
        } catch (Throwable $e) {
            // Fire-and-forget: if the broker is down, saving the book must still succeed.
            Log::warning("MQTT publish failed: {$e->getMessage()}");
        }
    }
}
