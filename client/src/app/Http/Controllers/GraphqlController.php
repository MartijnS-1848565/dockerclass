<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

// Consuming GraphQL needs no special library: it's a POST with JSON
// {"query": "...", "variables": {...}} to ONE url, and JSON comes back.
class GraphqlController extends Controller
{
    // The client asks for exactly the fields it shows: no created_at, because the table doesn't use it.
    private const BOOKS_QUERY = <<<'GRAPHQL'
query Books($search: String) {
  books(search: $search) {
    id
    title
    author
  }
}
GRAPHQL;

    public function index(Request $request)
    {
        $search = $request->query('search');
        $variables = ['search' => $search];

        $response = $this->send(self::BOOKS_QUERY, $variables);

        return view('graphql.index', [
            'books' => $response->json('data.books', []),
            'graphqlErrors' => $response->json('errors', []),
            'search' => $search,
            'query' => self::BOOKS_QUERY,
            'variables' => $variables,
            'raw' => $this->describe($response),
            'playground' => $request->old('playground', "{\n  book(id: 1) {\n    title\n    published_year\n  }\n}"),
        ]);
    }

    // The "try your own query" form: send whatever the user typed.
    public function run(Request $request)
    {
        $query = (string) $request->input('playground');
        $response = $this->send($query, [], $request->boolean('with_key'));

        return back()->withInput()->with('playground_result', $this->describe($response));
    }

    private function send(string $query, array $variables = [], bool $withKey = true)
    {
        return Http::acceptJson()
            ->withHeaders($withKey ? ['X-API-KEY' => config('bookapi.key')] : [])
            ->timeout(5)
            ->post(config('bookapi.graphql_url'), ['query' => $query, 'variables' => (object) $variables])
            ->throw(); // GraphQL errors still come back as HTTP 200, so this only fires on real HTTP failures
    }

    private function describe($response): array
    {
        return [
            'request' => 'POST '.config('bookapi.graphql_url'),
            'status' => $response->status(),
            'body' => json_encode($response->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ];
    }
}
