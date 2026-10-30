@extends('layout')

@section('title', 'gRPC over WebSockets')

@section('content')
    <h2>gRPC over WebSockets</h2>

    <p>
        A browser can't make real gRPC calls, so this page wraps each call in a WebSocket to a small
        bridge (<code id="bridge">{{ $bridgeUrl }}</code>). The bridge makes the real gRPC call to
        <code>books.v1.BookService</code> and sends every response message back, then the final
        status: the part real gRPC sends as HTTP/2 trailers.
    </p>

    <form id="call-form">
        <fieldset>
            <legend>Call a method</legend>
            <label for="method">Method</label>
            <select id="method">
                <option>ListBooks</option>
                <option>GetBook</option>
                <option>StreamBooks</option>
            </select>
            <label for="message">Request message (JSON)</label>
            <textarea id="message" rows="3" style="width: 100%; max-width: 24rem; font-family: monospace;"></textarea>
            <p><button>Invoke</button></p>
        </fieldset>
    </form>

    <table>
        <thead><tr><th>Time</th><th>Frame</th><th>Content</th></tr></thead>
        <tbody id="frames">
            <tr id="empty"><td colspan="3">No calls yet.</td></tr>
        </tbody>
    </table>

    <script>
        // A page loaded over https (e.g. via Cloudflare) may only open encrypted wss:// connections.
        // Laravel can't see that (the request reaches it as plain http), so the browser picks the scheme.
        const bridgeUrl = @json($bridgeUrl).replace(/^ws:/, location.protocol === 'https:' ? 'wss:' : 'ws:');
        document.getElementById('bridge').textContent = bridgeUrl;

        const examples = {
            ListBooks: { search: '' },
            GetBook: { id: 1 },
            StreamBooks: { search: '' },
        };
        const method = document.getElementById('method');
        const message = document.getElementById('message');
        const frames = document.getElementById('frames');
        const showExample = () => message.value = JSON.stringify(examples[method.value]);
        method.addEventListener('change', showExample);
        showExample();

        function show(frame, content) {
            document.getElementById('empty')?.remove();
            const row = frames.insertRow(-1);
            row.insertCell().textContent = new Date().toLocaleTimeString();
            row.insertCell().textContent = frame;
            row.insertCell().innerHTML = '<pre></pre>';
            row.querySelector('pre').textContent = content;
        }

        document.getElementById('call-form').addEventListener('submit', (event) => {
            event.preventDefault();
            let request;
            try {
                request = JSON.parse(message.value || '{}');
            } catch (e) {
                return alert('The request message is not valid JSON: ' + e.message);
            }
            frames.innerHTML = '';

            // One WebSocket per call, like one HTTP/2 stream per call in real gRPC.
            const ws = new WebSocket(bridgeUrl);
            const call = { method: method.value, message: request };
            ws.onopen = () => {
                ws.send(JSON.stringify(call));
                show('→ call', JSON.stringify(call, null, 2));
            };
            ws.onmessage = (event) => {
                const frame = JSON.parse(event.data);
                if ('message' in frame) {
                    show('← message', JSON.stringify(frame.message, null, 2));
                } else {
                    show('← status', `${frame.status} (${frame.code})` + (frame.details ? `: ${frame.details}` : ''));
                }
            };
            ws.onerror = () => show('✕ error', 'Could not reach the bridge (is the grpc-ws container running?)');
        });
    </script>
@endsection
