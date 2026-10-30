<?php

namespace App\Models;

use App\Observers\BookObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// ObservedBy: every create/update/delete also publishes an MQTT event (see BookObserver).
#[ObservedBy(BookObserver::class)]
class Book extends Model
{
    // Columns that may be filled with Book::create([...]) / $book->update([...]).
    protected $fillable = ['title', 'author', 'published_year'];

    // Form fields arrive as strings ("1999"). The cast makes the year an int in every
    // response and MQTT event, not only after the book is reloaded from the database.
    protected function casts(): array
    {
        return ['published_year' => 'integer'];
    }

    // Book::query()->search('dune'). Used by REST, GraphQL and SOAP alike.
    public function scopeSearch(Builder $query, ?string $search): void
    {
        $query->when($search, fn (Builder $query) => $query->where('title', 'like', "%{$search}%"));
    }
}
