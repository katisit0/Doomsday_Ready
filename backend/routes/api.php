<?php

use App\Http\Controllers\MovieController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/movies', [MovieController::class, 'index']);

Route::post('/movies', [MovieController::class, 'store']);

Route::patch('/movies/{movie}', [MovieController::class, 'update']);

Route::get('/movies/{movie}/rewatch-order', [MovieController::class, 'rewatchOrder']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


