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
Route::get('/categories', [PublicApiController::class, 'serviceCategories']);
Route::get('/bank-accounts', [PublicApiController::class, 'bankAccounts']);
Route::get('/galleries', [PublicApiController::class, 'galleries']);
Route::post('/appointments', [PublicApiController::class, 'bookAppointment']);
Route::post('/contact', [PublicApiController::class, 'submitContact']);

// Customer Authentication APIs
Route::post('/customer/signup', [PublicApiController::class, 'customerSignup']);
Route::post('/customer/login', [PublicApiController::class, 'customerLogin']);

