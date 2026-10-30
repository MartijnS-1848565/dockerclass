<?php

use App\Http\Middleware\RequireApiKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // Laravel doesn't load routes/api.php by default; this line enables it.
        // Every route in that file automatically gets the /api prefix.
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.key' => RequireApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // An API should always answer in JSON, even for 404s and validation errors,
        // also when the client forgot to send "Accept: application/json".
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));
    })->create();
