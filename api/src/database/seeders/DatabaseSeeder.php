<?php

namespace Database\Seeders;

use App\Models\Book;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Runs on every container start, so it must be safe to run twice:
    // firstOrCreate only inserts a book if that title doesn't exist yet.
    public function run(): void
    {
        $books = [
            ['title' => 'Dune', 'author' => 'Frank Herbert', 'published_year' => 1965],
            ['title' => 'The Hobbit', 'author' => 'J.R.R. Tolkien', 'published_year' => 1937],
            ['title' => 'Neuromancer', 'author' => 'William Gibson', 'published_year' => 1984],
        ];

        foreach ($books as $book) {
            Book::firstOrCreate(['title' => $book['title']], $book);
        }
    }
}
