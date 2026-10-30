<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// A Resource decides what the JSON for one Book looks like.
// Your database columns and your public API don't have to be identical.
class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'published_year' => $this->published_year,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
