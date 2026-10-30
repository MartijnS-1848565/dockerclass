<?php

return [
    // For PHP code in containers (the listener, the publish form): plain MQTT inside the Docker network.
    'host' => env('MQTT_HOST', 'mqtt'),
    'port' => (int) env('MQTT_PORT', 1883),

    // For the browser: MQTT over WebSockets through the proxy. The page fills in the
    // host and port it was loaded from, so this is only the path.
    'browser_path' => env('MQTT_BROWSER_PATH', '/mqtt'),
];
