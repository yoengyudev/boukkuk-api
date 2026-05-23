<?php

use App\Http\Controllers\Api\BoukKukApiController;
use Illuminate\Support\Facades\Route;

Route::options('/{any}', fn () => response('', 204))->where('any', '.*');

Route::post('/login', [BoukKukApiController::class, 'login']);
Route::post('/register', [BoukKukApiController::class, 'register']);
Route::delete('/logout', [BoukKukApiController::class, 'logout']);

Route::post('/forgot/pass', [BoukKukApiController::class, 'forgotPassword']);
Route::post('/forgot/verify-otp', [BoukKukApiController::class, 'verifyOtp']);
Route::post('/reset/pass', [BoukKukApiController::class, 'resetPassword']);

Route::get('/me', [BoukKukApiController::class, 'me']);
Route::put('/profile/info', [BoukKukApiController::class, 'updateProfile']);
Route::post('/profile/avatar', [BoukKukApiController::class, 'updateAvatar']);
Route::delete('/profile/avatar', [BoukKukApiController::class, 'deleteAvatar']);
Route::put('/profile/change-pass', [BoukKukApiController::class, 'changePassword']);

Route::get('/users/providers', [BoukKukApiController::class, 'providers']);
Route::get('/users/providers/{id}', [BoukKukApiController::class, 'showProvider']);
Route::get('/users', [BoukKukApiController::class, 'users']);
Route::post('/users', [BoukKukApiController::class, 'storeUser']);
Route::get('/users/{id}', [BoukKukApiController::class, 'showUser']);
Route::post('/users/{id}', [BoukKukApiController::class, 'updateUser']);
Route::delete('/users/{id}', [BoukKukApiController::class, 'deleteUser']);
Route::put('/users/enable/{id}', [BoukKukApiController::class, 'enableUser']);
Route::put('/users/disable/{id}', [BoukKukApiController::class, 'disableUser']);
Route::put('/users/set-role/{id}', [BoukKukApiController::class, 'setRole']);

Route::get('/categories', [BoukKukApiController::class, 'categories']);
Route::post('/categories', [BoukKukApiController::class, 'storeCategory']);
Route::put('/categories/{id}', [BoukKukApiController::class, 'updateCategory']);
Route::delete('/categories/{id}', [BoukKukApiController::class, 'deleteCategory']);

Route::get('/services', [BoukKukApiController::class, 'services']);
Route::post('/services', [BoukKukApiController::class, 'storeService']);
Route::get('/services/{id}', [BoukKukApiController::class, 'showService']);
Route::post('/services/{id}', [BoukKukApiController::class, 'updateService']);
Route::delete('/services/{id}', [BoukKukApiController::class, 'deleteService']);

Route::get('/profile/carts', [BoukKukApiController::class, 'profileCarts']);
Route::post('/carts', [BoukKukApiController::class, 'storeCart']);
Route::delete('/carts/{id}', [BoukKukApiController::class, 'deleteCart']);
Route::post('/carts/checkout', [BoukKukApiController::class, 'checkout']);

Route::post('/wishlists', [BoukKukApiController::class, 'storeWishlist']);
Route::get('/profile/wishlists', [BoukKukApiController::class, 'profileWishlists']);
Route::delete('/wishlists/{id}', [BoukKukApiController::class, 'deleteWishlist']);

Route::get('/profile/payment-check', [BoukKukApiController::class, 'paymentCheck']);
Route::get('/profile/purchased', [BoukKukApiController::class, 'purchased']);
Route::get('/payments/{id}', [BoukKukApiController::class, 'payment']);
Route::put('/payments/approve/{id}', [BoukKukApiController::class, 'approvePayment']);
Route::put('/payments/reject/{id}', [BoukKukApiController::class, 'rejectPayment']);
Route::put('/payments/set-status/{id}', [BoukKukApiController::class, 'setPaymentStatus']);
Route::put('/payments/set-pickup-schedule/{id}', [BoukKukApiController::class, 'setPickupSchedule']);
