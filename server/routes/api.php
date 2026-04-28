<?php

use App\Http\Controllers\Api\BasketController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/products', [ProductController::class, 'index']);

Route::get('/basket', [BasketController::class, 'show']);
Route::post('/basket/items', [BasketController::class, 'add']);
Route::patch('/basket/items/{code}', [BasketController::class, 'update']);
Route::delete('/basket/items/{code}', [BasketController::class, 'remove']);
Route::delete('/basket', [BasketController::class, 'clear']);
