<?php

use App\Http\Controllers\CinemaController;
use App\Http\Controllers\MovieController;
use Illuminate\Support\Facades\Route;

Route::get('/movies', [MovieController::class, 'index']);

Route::post('/movies', [MovieController::class, 'store']);

Route::post('/movies/load-mcu', [MovieController::class, 'loadMcu']);

Route::patch('/movies/{movie}', [MovieController::class, 'update']);

Route::get('/movies/{movie}/rewatch-order', [MovieController::class, 'rewatchOrder']);

Route::get('/cinemas', [CinemaController::class, 'index']);

Route::post('/cinemas', [CinemaController::class, 'store']);

Route::get('/cinemas/{cinema}/booking', [CinemaController::class, 'booking']);


