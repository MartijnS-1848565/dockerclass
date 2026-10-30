@extends('layout')

@section('title', 'Live events via MQTT')

@section('content')
    <h2>Live events via MQTT</h2>

    <p>
        This page is subscribed to <code>books/#</code> and <code>lab/#</code> on the broker
        (<code id="broker">{{ $brokerUrl }}</code>): <strong id="state">connecting…</strong>
    </p>
    <p>Books right now: <strong id="count">?</strong> <small>(from the retained <code>books/count</code> message)</small></p>
    <p>Add, edit or delete a book on the <a href="{{ route('books.index') }}" target="_blank">REST</a>,
       <a href="{{ route('graphql.index') }}" target="_blank">GraphQL</a> or
       <a href="{{ route('soap.index') }}" target="_blank">SOAP</a> page in another tab and watch the events arrive here.</p>

    <table>
        <thead><tr><th>Time</th><th>Topic</th><th>Message</th></tr></thead>
        <tbody id="feed">
            <tr id="empty"><td colspan="3">No messages yet.</td></tr>
        </tbody>
    </table>

    <form id="publish-form" method="POST" action="{{ route('mqtt.publish') }}">
        @csrf
        <fieldset>
            <legend>Publish a message from Laravel</legend>
            <label for="topic">Topic</label>
            <input id="topic" name="topic" value="{{ old('topic', 'lab/messages') }}">
            <label for="message">Message</label>
            <input id="message" name="message" value="{{ old('message', 'Hello from the client container') }}">
            <p><button>Publish</button></p>
        </fieldset>
    </form>

    {{-- mqtt.js: an MQTT client for the browser. It speaks MQTT over WebSockets. --}}
    <script src="https://unpkg.com/mqtt@5.10.1/dist/mqtt.min.js"></script>
    <script>
        const form = document.getElementById('publish-form');
        form.addEventListener('submit',async (event)=>{
            event.preventDefault();
            const response= await fetch(form.action,{
                method: 'POST',
                body: new FormData(form),
                headers:{ 'Accept':'application/json' },
            });
            if(!response.ok){
                alert((await response.json()).message);
            }
        });
        const state = document.getElementById('state');
        const feed = document.getElementById('feed');

        // A page loaded over https (e.g. via Cloudflare) may only open encrypted wss:// connections.
        // Laravel can't see that (the request reaches it as plain http), so the browser picks the scheme.
        const brokerUrl = @json($brokerUrl).replace(/^ws:/, location.protocol === 'https:' ? 'wss:' : 'ws:');
        document.getElementById('broker').textContent = brokerUrl;

        const client = mqtt.connect(brokerUrl);
        
        client.on('connect', () => {
            state.textContent = 'connected';
            client.subscribe(['books/#', 'lab/#'], { qos: 1 });
        });
        client.on('reconnect', () => state.textContent = 'reconnecting…');
        client.on('offline', () => state.textContent = 'offline (is the mqtt container running?)');

        client.on('message', (topic, payload, packet) => {
            const text = payload.toString();

            if (topic === 'books/count') {
                document.getElementById('count').textContent = text;
            }

            document.getElementById('empty')?.remove();
            const row = feed.insertRow(0);
            row.insertCell().textContent = new Date().toLocaleTimeString();
            row.insertCell().textContent = topic + (packet.retain ? ' (retained)' : '');
            row.insertCell().textContent = text;
        });
    </script>
@endsection
