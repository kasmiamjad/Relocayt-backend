<?php

use App\Http\Controllers\API\v1\Dashboard\Payment\StripeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dashboard Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::any('payment-success', [StripeController::class, 'resultTransaction']);
Route::any('mtn-process',     [StripeController::class, 'mtnProcess']);

Route::get('/', function () {
    return view('welcome');
});

