<?php

return [
    // The broker's service name and port inside the Docker network (see compose.yaml).
    'host' => env('MQTT_HOST', 'mqtt'),
    'port' => (int) env('MQTT_PORT', 1883),
];
