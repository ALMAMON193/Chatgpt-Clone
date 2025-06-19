<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Auth\V1\AuthApiController;
use App\Http\Controllers\API\ConversitionApiController;


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
Route::middleware('auth:sanctum')->group(function () {
    Route::post('v1/auth/logout', [AuthApiController::class, 'logoutApi']);
    //conversition routes
    Route::get('v1/conversitions', [ConversitionApiController::class, 'getConversationsByUserId']);
    Route::post('v1/conversition/store', [ConversitionApiController::class, 'storeConversation']);
    Route::get('v1/conversition/{conversation_id}', [ConversitionApiController::class, 'getConversationDetails']);

});
