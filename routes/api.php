<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\SubscriptionController;
use App\Http\Controllers\API\Auth\V1\AuthApiController;
use App\Http\Controllers\API\ConversationApiController;


/** authentication all Route
 * @apiVersion v1
 * @apiGroup Auth
 */
Route::prefix('v1/auth')->group(function () {
    Route::post('login', [AuthApiController::class, 'loginApi']);
    Route::post('register', [AuthApiController::class, 'registerApi']);
    Route::post('verify-email', [AuthApiController::class, 'verifyEmailApi']);
    Route::post('forgot-password', [AuthApiController::class, 'forgotPasswordApi']);
    Route::post('reset-password', [AuthApiController::class, 'resetPasswordApi']);
    Route::post('resend-otp', [AuthApiController::class, 'resendOtpApi']);
    Route::post('verify-otp', [AuthApiController::class, 'verifyOtpApi']);
});
Route::prefix('v1/')->group(function () {
    //conversation routes
    Route::post('generate-visitor-id', [ConversationApiController::class, 'generateVisitorId']);
    Route::post('conversation/store', [ConversationApiController::class, 'storeConversation']);
});
Route::middleware('auth:sanctum')->group(function () {
    Route::post('v1/auth/logout', [AuthApiController::class, 'logoutApi']);
});
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::post('auth/logout', [AuthApiController::class, 'logoutApi']);
    Route::get('conversations', [ConversationApiController::class, 'getConversationsByUserId']);
    Route::get('conversation/{conversation_id}', [ConversationApiController::class, 'getConversationDetails']);
});

//subscription routes
Route::middleware('auth:sanctum')->group(function () {
   Route::post('v1/get-subscription', [SubscriptionController::class, 'getSubscription']);
});
Route::get('v1/plans', [SubscriptionController::class, 'getPlans']);

Route::post('/subscribe', [SubscriptionController::class, 'subscribe']);
Route::get('/payment/success', [SubscriptionController::class, 'success'])->name('payment.success');
Route::get('/payment/cancel', [SubscriptionController::class, 'cancel'])->name('payment.cancel');
