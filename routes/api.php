<?php

use App\Http\Controllers\Api\AddressesController;
use App\Http\Controllers\Api\CategoriesController;
use App\Http\Controllers\Api\ChangePasswordController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\OrderCancelController;
use App\Http\Controllers\Api\OrderConfirmController;
use App\Http\Controllers\Api\OrdersController;
use App\Http\Controllers\Api\PaymentsController;
use App\Http\Controllers\Api\ProductFavouriteController;
use App\Http\Controllers\Api\ProductsController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\ResetPasswordController;
use Illuminate\Support\Facades\Route;

Route::prefix('cstmr')->group(function () {
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('register', [RegisterController::class, 'register']);
        Route::post('login', [LoginController::class, 'login']);
        Route::post('password/email', ForgotPasswordController::class);
        Route::post('password/reset', ResetPasswordController::class);
    });

    Route::get('categories', CategoriesController::class);
    Route::get('products', [ProductsController::class, 'index']);
    Route::get('products/{product}', [ProductsController::class, 'show']);

    Route::middleware('auth:api')->group(function () {
        Route::get('/', CustomerController::class);
        Route::post('/', [CustomerController::class, 'update']);
        Route::post('fcm_token', [CustomerController::class, 'updateFCMToken']);
        Route::post('change_password', ChangePasswordController::class)->middleware('throttle:10,1');
        Route::get('favourites', [CustomerController::class, 'favorites']);
        foreach (['favourite', 'favourites'] as $suffix) {
            Route::post("products/{product}/$suffix", [ProductFavouriteController::class, 'store']);
            Route::delete("products/{product}/$suffix", [ProductFavouriteController::class, 'destroy']);
        }
        Route::get('orders', [OrdersController::class, 'index']);
        Route::post('orders', [OrdersController::class, 'store']);
        Route::get('orders/{order}', [OrdersController::class, 'show']);
        Route::post('orders/{order}/cancel', OrderCancelController::class);
        Route::post('orders/{order}/confirm', OrderConfirmController::class);
        Route::match(['get', 'post'], 'orders/{order}/payments', PaymentsController::class);
    });
});

Route::prefix('v1')->middleware('auth:api')->group(function () {
    Route::get('regions', [AddressesController::class, 'getRegions']);
    Route::get('districts/{id}', [AddressesController::class, 'getDistricts']);
    Route::get('councils/{id}', [AddressesController::class, 'getCouncils']);
    Route::get('streets/{id}', [AddressesController::class, 'getStreet']);
    Route::get('street_road/{id}', [AddressesController::class, 'getStreetRoad']);
    Route::get('address/{id}', [AddressesController::class, 'getAddress']);
    Route::post('create_shipping', [AddressesController::class, 'createShipping']);
    Route::get('customer_addresses', [AddressesController::class, 'getCustomerAddresses']);
});

Route::get('payments/pesapal/callback', [PaymentsController::class, 'callback'])->name('payments.pesapal.callback');
Route::match(['get', 'post'], 'payments/pesapal/ipn', [PaymentsController::class, 'ipn'])->name('payments.pesapal.ipn');
