<?php

use FriendsOfBotble\PayU\Http\Controllers\PayUController;
use Illuminate\Support\Facades\Route;

Route::middleware(['core'])->prefix('payment/payu')->name('payment.payu.')->group(function () {
    Route::post('callback', [PayUController::class, 'callback'])->name('callback');
    Route::post('webhook', [PayUController::class, 'webhook'])->name('webhook');
});
