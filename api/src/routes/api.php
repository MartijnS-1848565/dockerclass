<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\SoapController;
use Illuminate\Support\Facades\Route;

// All routes here are prefixed with /api (see bootstrap/app.php).
// GraphQL needs no routes here: Lighthouse registers POST /graphql itself.

// Public: anyone may read.
Route::get('/books', [BookController::class, 'index']);
Route::get('/books/{book}', [BookController::class, 'show']);

// Protected: writing requires the X-API-KEY header.
Route::middleware('api.key')->group(function () {
    Route::post('/books', [BookController::class, 'store']);
    Route::put('/books/{book}', [BookController::class, 'update']);
    Route::delete('/books/{book}', [BookController::class, 'destroy']);
});

// SOAP: one URL for everything. GET returns the WSDL contract, POST receives SOAP envelopes.
// (The API key for AddBook travels inside the SOAP message, so no middleware here.)
Route::get('/soap', [SoapController::class, 'wsdl']);
Route::post('/soap', [SoapController::class, 'handle']);
