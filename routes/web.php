<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Payment;


Route::get('/', function () {
    return view('welcome');
});

Route::controller(Payment::class)
->prefix('payments')
->as('payments')
->group(function(){
    Route::get('/token','token') -> name('token');
    Route::get('/initiatepush','initiateStkPush') -> name('initiatepush ');
    Route::post('/stkcallback','stkcallback') -> name('stkcallback');
    Route::get('/admin', 'AdminController@index')->name('admin.dashboard');
});