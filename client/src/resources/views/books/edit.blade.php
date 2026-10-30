@extends('layout')

@section('title', 'Edit '.$book['title'])

@section('content')
    <form method="POST" action="{{ route('books.update', $book['id']) }}">
        @csrf
        @method('PUT')
        <fieldset>
            <legend>Edit book #{{ $book['id'] }} (PUT /api/books/{{ $book['id'] }})</legend>
            <label for="title">Title</label>
            <input id="title" name="title" value="{{ old('title', $book['title']) }}">
            <label for="author">Author</label>
            <input id="author" name="author" value="{{ old('author', $book['author']) }}">
            <label for="published_year">Year</label>
            <input id="published_year" name="published_year" value="{{ old('published_year', $book['published_year']) }}">
            <p><button>Save</button> <a href="{{ route('books.index') }}">cancel</a></p>
        </fieldset>
    </form>
@endsection
