<?php

return [
    // For the browser: gRPC wrapped in WebSockets, through the proxy to the grpc-ws bridge.
    // The page fills in the host and port it was loaded from, so this is only the path.
    'browser_path' => env('GRPC_BROWSER_PATH', '/grpc-ws'),
];
