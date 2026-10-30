@extends('layout')

@section('title', 'Books via GraphQL')

@section('content')
    <h2>Books via GraphQL</h2>

    <form method="GET" action="{{ route('graphql.index') }}">
        <input name="search" value="{{ $search }}" placeholder="Search titles…">
        <button>Search</button>
    </form>

    <p>This page sent this query, with these variables, to <code>{{ config('bookapi.graphql_url') }}</code>:</p>
    <pre>{{ $query }}</pre>
    <pre>{{ json_encode($variables) }}</pre>

    @foreach ($graphqlErrors as $error)
        <p class="errors">{{ $error['message'] }}</p>
    @endforeach

    <table>
        <thead><tr><th>#</th><th>Title</th><th>Author</th></tr></thead>
        <tbody>
            @forelse ($books as $book)
                <tr><td>{{ $book['id'] }}</td><td>{{ $book['title'] }}</td><td>{{ $book['author'] }}</td></tr>
            @empty
                <tr><td colspan="3">No books.</td></tr>
            @endforelse
        </tbody>
    </table>

    <form method="POST" action="{{ route('graphql.run') }}">
        @csrf
        <fieldset>
            <legend>Try your own query or mutation</legend>
            <textarea name="playground" rows="8" style="width:100%;font-family:monospace">{{ $playground }}</textarea>
            <label><input type="checkbox" name="with_key" value="1" style="width:auto" @checked(old('with_key', true))> send the X-API-KEY header (needed for mutations)</label>
            <p><button>Run</button> · or explore the schema in <a href="/graphiql">GraphiQL</a></p>
        </fieldset>
    </form>

    @if (session('playground_result'))
        <h3>Result: HTTP {{ session('playground_result')['status'] }}</h3>
        <pre>{{ session('playground_result')['body'] }}</pre>
    @endif
@endsection
