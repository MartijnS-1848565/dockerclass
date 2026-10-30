<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GrpcController extends Controller
{
    // The calls themselves run in the browser (see resources/views/grpc/index.blade.php):
    // the page sends each call over a WebSocket to the grpc-ws bridge, which makes the real
    // gRPC call. Laravel only serves the page.
    public function index(Request $request)
    {
        return view('grpc.index', [
            'bridgeUrl' => 'ws://'.$request->getHttpHost().config('grpc.browser_path'),
        ]);
    }
}
