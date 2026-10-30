<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The proxy serves this site under /client/ but strips that prefix before forwarding,
        // and tells us in the X-Forwarded-Prefix header. Trusting the proxy's headers makes
        // route(), url() and redirects put /client back in front of every link.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_PREFIX);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The API container is down, or the name/port is wrong.
        // Try it: docker compose stop api
        $exceptions->render(function (ConnectionException $e) {
            return response()->view('api-error', [
                'title' => 'Cannot reach the Book API',
                'detail' => $e->getMessage(),
            ], 503);
        });

        // SoapClient couldn't load the WSDL or reach the SOAP endpoint.
        $exceptions->render(function (SoapFault $e) {
            return response()->view('api-error', [
                'title' => 'SOAP call failed',
                'detail' => $e->getMessage(),
            ], 502);
        });

        // The API answered, but with a 4xx/5xx we didn't handle ourselves.
        $exceptions->render(function (RequestException $e) {
            return response()->view('api-error', [
                'title' => "The Book API answered with HTTP {$e->response->status()}",
                'detail' => $e->response->body(),
            ], 502);
        });
    })->create();
