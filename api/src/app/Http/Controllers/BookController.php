<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\Request;

class BookController extends Controller
{
    // GET /api/books?search=dune
    public function index(Request $request)
    {
        $books = Book::query()->search($request->query('search'))->orderBy('id')->get();

        return BookResource::collection($books);
    }

    // GET /api/books/1
    // {book} in the route is resolved to a Book model automatically (route model binding);
    // a missing id becomes a 404 JSON response.
    public function show(Book $book)
    {
        return new BookResource($book);
    }

    // POST /api/books
    public function store(Request $request)
    {
        // Invalid input stops here with a 422 response listing the errors.
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'published_year' => ['nullable', 'integer', 'between:0,2100'],
        ]);

        $book = Book::create($validated);

        return (new BookResource($book))->response()->setStatusCode(201);
    }

    // PUT /api/books/1
    public function update(Request $request, Book $book)
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'author' => ['sometimes', 'required', 'string', 'max:255'],
            'published_year' => ['sometimes', 'nullable', 'integer', 'between:0,2100'],
        ]);

        $book->update($validated);

        return new BookResource($book);
    }

    // DELETE /api/books/1
    public function destroy(Book $book)
    {
        $book->delete();

        return response()->noContent(); // 204
    }
}
