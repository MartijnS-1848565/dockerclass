@extends('layout')

@section('title', 'Books via SOAP')

@section('content')
    <h2>Books via SOAP</h2>

    <form method="GET" action="{{ route('soap.index') }}">
        <input name="search" value="{{ $search }}" placeholder="Search titles…">
        <button>Search</button>
    </form>

    <table>
        <thead><tr><th>#</th><th>Title</th><th>Author</th><th>Year</th></tr></thead>
        <tbody>
            @forelse ($books as $book)
                <tr><td>{{ $book->id }}</td><td>{{ $book->title }}</td><td>{{ $book->author }}</td><td>{{ $book->published_year }}</td></tr>
            @empty
                <tr><td colspan="4">No books.</td></tr>
            @endforelse
        </tbody>
    </table>

    <form method="POST" action="{{ route('soap.store') }}">
        @csrf
        <fieldset>
            <legend>Add a book (AddBook operation)</legend>
            <label for="title">Title</label>
            <input id="title" name="title" value="{{ old('title') }}">
            <label for="author">Author</label>
            <input id="author" name="author" value="{{ old('author') }}">
            <label for="published_year">Year</label>
            <input id="published_year" name="published_year" value="{{ old('published_year') }}">
            <p><button>AddBook</button></p>
        </fieldset>
    </form>

    <p>Operations this client learned from the <a href="/api/soap?wsdl">WSDL</a>:</p>
    <pre>{{ implode("\n", $operations) }}</pre>

    @php($last = session('xml', $xml))
    <details open>
        <summary>Last SOAP request (XML envelope sent by SoapClient)</summary>
        <pre>{{ $last['request'] }}</pre>
    </details>
    <details>
        <summary>Last SOAP response</summary>
        <pre>{{ $last['response'] }}</pre>
    </details>
@endsection
