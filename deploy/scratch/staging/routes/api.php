<?php

use App\Http\Controllers\Api\PublicApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Front-End API Routes
|--------------------------------------------------------------------------
*/

Route::get('/products', [PublicApiController::class, 'products']);
Route::get('/services', [PublicApiController::class, 'services']);
Route::get('/service-categories', [PublicApiController::class, 'serviceCategories']);
Route::post('/appointments', [PublicApiController::class, 'bookAppointment']);
Route::post('/contact', [PublicApiController::class, 'submitContact']);
