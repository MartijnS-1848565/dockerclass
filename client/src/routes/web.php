<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\GraphqlController;
use App\Http\Controllers\GrpcController;
use App\Http\Controllers\MqttController;
use App\Http\Controllers\SoapController;
use Illuminate\Support\Facades\Route;

// REST
Route::get('/', [BookController::class, 'index'])->name('books.index');
Route::post('/books', [BookController::class, 'store'])->name('books.store');
Route::get('/books/{id}/edit', [BookController::class, 'edit'])->name('books.edit');
Route::put('/books/{id}', [BookController::class, 'update'])->name('books.update');
Route::delete('/books/{id}', [BookController::class, 'destroy'])->name('books.destroy');

// GraphQL
Route::get('/graphql', [GraphqlController::class, 'index'])->name('graphql.index');
Route::post('/graphql', [GraphqlController::class, 'run'])->name('graphql.run');

// SOAP
Route::get('/soap', [SoapController::class, 'index'])->name('soap.index');
Route::post('/soap', [SoapController::class, 'store'])->name('soap.store');

// MQTT
Route::get('/mqtt', [MqttController::class, 'index'])->name('mqtt.index');
Route::post('/mqtt', [MqttController::class, 'publish'])->name('mqtt.publish');

// gRPC (wrapped in WebSockets)
Route::get('/grpc', [GrpcController::class, 'index'])->name('grpc.index');
