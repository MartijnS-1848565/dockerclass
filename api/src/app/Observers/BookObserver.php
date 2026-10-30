<?php

namespace App\Observers;

use App\Models\Book;
use App\Mqtt\BookEvents;

// Eloquent calls these methods after every change to a Book, no matter where the change
// came from: REST, GraphQL, SOAP, tinker, or the seeder. So every API style publishes events
// without any of them knowing about MQTT.
class BookObserver
{
    public function created(Book $book): void
    {
        BookEvents::publish('created', $book);
    }

    public function updated(Book $book): void
    {
        BookEvents::publish('updated', $book);
    }

    public function deleted(Book $book): void
    {
        BookEvents::publish('deleted', $book);
    }
}
