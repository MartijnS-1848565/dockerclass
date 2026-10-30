<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

class MqttController extends Controller
{
    // The live feed itself runs in the browser (see resources/views/mqtt/index.blade.php):
    // the page connects straight to the broker over WebSockets. Laravel only serves the page.
    public function index(Request $request)
    {
        return view('mqtt.index', [
            'brokerUrl' => 'ws://'.$request->getHttpHost().config('mqtt.browser_path'),
        ]);
    }

    // Publishing from Laravel: connect, publish, disconnect.
    public function publish(Request $request)
    {
        $validated = $request->validate([
            'topic' => ['required', 'string', 'max:100', 'regex:/^lab\/[^#+]+$/'],
            'message' => ['required', 'string', 'max:500'],
        ], [
            'topic.regex' => 'Topics on this form must start with lab/ and contain no wildcards (# or +).',
        ]);

        $mqtt = new MqttClient(config('mqtt.host'), config('mqtt.port'), 'book-client-'.uniqid());
        $mqtt->connect((new ConnectionSettings)->setConnectTimeout(2), true);
        $mqtt->publish($validated['topic'], $validated['message'], MqttClient::QOS_AT_LEAST_ONCE);
        $mqtt->loop(true, true); // wait for the broker's confirmation (QoS 1)
        $mqtt->disconnect();

        return back()->withInput()->with('status', "Published to {$validated['topic']}");
    }
}
