<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Book Client')</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 52rem; margin: 2rem auto; padding: 0 1rem; color: #222; }
        header { display: flex; justify-content: space-between; align-items: baseline; border-bottom: 1px solid #ddd; margin-bottom: 1.5rem; }
        nav a { margin: 0 0.2rem; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { text-align: left; padding: 0.5rem; border-bottom: 1px solid #eee; }
        form.inline { display: inline; }
        fieldset { border: 1px solid #ddd; border-radius: 6px; padding: 1rem; }
        label { display: block; margin: 0.5rem 0 0.2rem; }
        input { padding: 0.4rem; width: 100%; max-width: 24rem; box-sizing: border-box; }
        button { padding: 0.4rem 0.9rem; cursor: pointer; }
        .status { background: #e7f6ec; border: 1px solid #9fd8b0; padding: 0.6rem; border-radius: 6px; }
        .errors { background: #fdecec; border: 1px solid #f0a8a8; padding: 0.6rem; border-radius: 6px; }
        details { margin-top: 2rem; background: #f6f8fa; border-radius: 6px; padding: 0.6rem; }
        pre { overflow-x: auto; font-size: 0.85rem; }
    </style>
</head>
<body>
    <header>
        <h1><a href="{{ route('books.index') }}" style="color:inherit;text-decoration:none">📚 Book Client</a></h1>
        <nav>
            <a href="{{ route('books.index') }}">REST</a> ·
            <a href="{{ route('graphql.index') }}">GraphQL</a> ·
            <a href="{{ route('soap.index') }}">SOAP</a> ·
            <a href="{{ route('mqtt.index') }}">MQTT</a> ·
            <a href="{{ route('grpc.index') }}">gRPC</a>
        </nav>
    </header>

    @if (session('status'))
        <p class="status">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <div class="errors">
            <strong>The API rejected the request:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')

    @isset($raw)
        <details>
            <summary>What did the API say? <code>{{ $raw['request'] }}</code> → HTTP {{ $raw['status'] }}</summary>
            <pre>{{ $raw['body'] }}</pre>
        </details>
    @endisset
</body>
</html>
