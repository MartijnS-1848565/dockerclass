@extends('layout')

@section('title', 'API error')

@section('content')
    <div class="errors">
        <h2>{{ $title }}</h2>
        <pre>{{ $detail }}</pre>
    </div>
    <p>Things to check:</p>
    <ul>
        <li><code>docker compose ps</code>: is the <code>api</code> container running and healthy?</li>
        <li><code>docker compose logs api</code>: did it crash on start-up?</li>
        <li>Is <code>BOOK_API_URL</code> in <code>compose.yaml</code> pointing at <code>http://api:8000/api</code>?</li>
    </ul>
@endsection
