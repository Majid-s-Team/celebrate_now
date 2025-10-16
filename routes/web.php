<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
use App\Http\Controllers\WEB\PolicyWebController;

Route::get('/policies/{type}', [PolicyWebController::class, 'show'])
    ->where('type', 'terms|privacy|faq')
    ->name('policies.show');

Route::get('/', function () {
    return view('welcome');
});
