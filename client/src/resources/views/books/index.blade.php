@extends('layout')

@section('content')
    <form method="GET" action="{{ route('books.index') }}">
        <input name="search" value="{{ $search }}" placeholder="Search titles…">
        <button>Search</button>
    </form>

    <table>
        <thead>
            <tr><th>#</th><th>Title</th><th>Author</th><th>Year</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($books as $book)
                <tr>
                    <td>{{ $book['id'] }}</td>
                    <td>{{ $book['title'] }}</td>
                    <td>{{ $book['author'] }}</td>
                    <td>{{ $book['published_year'] }}</td>
                    <td>
                        <a href="{{ route('books.edit', $book['id']) }}">edit</a>
                        <form class="inline" method="POST" action="{{ route('books.destroy', $book['id']) }}">
                            @csrf
                            @method('DELETE')
                            <button>delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">The API returned no books.</td></tr>
            @endforelse
        </tbody>
    </table>

    <form method="POST" action="{{ route('books.store') }}">
        @csrf
        <fieldset>
            <legend>Add a book (POST /api/books)</legend>
            <label for="title">Title</label>
            <input id="title" name="title" value="{{ old('title') }}">
            <label for="author">Author</label>
            <input id="author" name="author" value="{{ old('author') }}">
            <label for="published_year">Year</label>
            <input id="published_year" name="published_year" value="{{ old('published_year') }}">
            <p><button>Create</button></p>
        </fieldset>
    </form>
@endsection
