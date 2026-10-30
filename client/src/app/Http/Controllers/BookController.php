<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

// Every action here does the same three things:
//   1. send an HTTP request to the Book API
//   2. look at the status code
//   3. turn the JSON into a page (or a redirect)
class BookController extends Controller
{
    // One place to configure every call: base URL, headers, timeout.
    private function api(): PendingRequest
    {
        return Http::baseUrl(config('bookapi.url'))
            ->acceptJson()
            ->withHeaders(['X-API-KEY' => config('bookapi.key')])
            ->timeout(5);
    }

    public function index(Request $request)
    {
        $search = $request->query('search');

        // GET http://api:8000/api/books?search=...
        $response = $this->api()->get('/books', array_filter(['search' => $search]))->throw();

        return view('books.index', [
            'books' => $response->json('data'),
            'search' => $search,
            'raw' => $this->describe('GET', '/books', $response),
        ]);
    }

    public function store(Request $request)
    {
        $response = $this->api()->post('/books', $request->only('title', 'author', 'published_year'));

        // 422 = the API rejected our input. Show its error messages on our form.
        if ($response->status() === 422) {
            return back()->withErrors($response->json('errors'))->withInput();
        }

        $response->throw();

        return redirect()->route('books.index')
            ->with('status', "Created \"{$response->json('data.title')}\" (HTTP {$response->status()})");
    }

    public function edit(int $id)
    {
        $response = $this->api()->get("/books/{$id}");

        if ($response->notFound()) {
            abort(404, 'That book does not exist in the API.');
        }

        return view('books.edit', [
            'book' => $response->throw()->json('data'),
            'raw' => $this->describe('GET', "/books/{$id}", $response),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $response = $this->api()->put("/books/{$id}", $request->only('title', 'author', 'published_year'));

        if ($response->status() === 422) {
            return back()->withErrors($response->json('errors'))->withInput();
        }

        $response->throw();

        return redirect()->route('books.index')
            ->with('status', "Updated book #{$id} (HTTP {$response->status()})");
    }

    public function destroy(int $id)
    {
        $response = $this->api()->delete("/books/{$id}")->throw();

        return redirect()->route('books.index')
            ->with('status', "Deleted book #{$id} (HTTP {$response->status()})");
    }

    // Shown in a "what did the API say?" panel on each page, for learning purposes.
    private function describe(string $method, string $path, Response $response): array
    {
        return [
            'request' => $method.' '.config('bookapi.url').$path,
            'status' => $response->status(),
            'body' => json_encode($response->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ];
    }
}
