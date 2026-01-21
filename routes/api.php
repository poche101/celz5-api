<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\StreamController;
use App\Http\Controllers\Api\ChurchController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\VideoController;
use App\Http\Controllers\Api\Admin\AdminStreamController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\PaymentSettingController;



Route::prefix('v1')->group(function () {

    // --- 1. Public Routes ---
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // --- 2. Protected Routes (Requires Sanctum Token) ---
    Route::middleware('auth:sanctum')->group(function () {

        // Profile Management
        Route::get('/profile', [ProfileController::class, 'getProfile']);
        Route::post('/profile/update', [ProfileController::class, 'updateProfile']);

        // Church Locator (Street/Area Search)
        Route::get('/churches/locate', [ChurchController::class, 'locateBySearch']);

        // Streaming & Engagement
        Route::prefix('stream')->group(function () {
            Route::post('/{programId}/enter', [StreamController::class, 'enterStream']);
            Route::post('/{programId}/comment', [StreamController::class, 'postComment']);
            Route::get('/{programId}/comments', [StreamController::class, 'getComments']);
        });

        // Payments (ExpressPay Integration)
        Route::prefix('payments')->group(function () {
            Route::post('/pay', [PaymentController::class, 'initiatePayment']);
            Route::post('/save-card', [PaymentController::class, 'saveCard']);
            Route::get('/saved-cards', function() {
                return auth()->user()->cards;
            });

        Route::post('/payments/webhook', [PaymentController::class, 'handleWebhook'])->name('api.payments.webhook');
        });

        // --- 3. Admin Dashboard Routes (Admin Only) ---
        Route::middleware('is_admin')->prefix('admin')->group(function () {

            // User Management
            Route::apiResource('users', UserController::class);

            // Video Management
            Route::post('/videos', [VideoController::class, 'store']);
            Route::post('/videos/{video}', [VideoController::class, 'update']);
            Route::delete('/videos/{video}', [VideoController::class, 'destroy']);

            // Stream Management (Admin Control)
            Route::post('/programs', [AdminStreamController::class, 'storeProgram']);
            Route::get('/programs/{programId}/attendance', [AdminStreamController::class, 'getAttendance']);
            Route::delete('/comments/{id}', [AdminStreamController::class, 'deleteComment']);

            // Payment Management
            Route::get('/payments', [PaymentController::class, 'index']);
            Route::get('/payments/stats', [PaymentController::class, 'stats']);
            Route::patch('/payments/{id}/status', [PaymentController::class, 'updateStatus']);

            Route::get('/payment-settings', [PaymentSettingController::class, 'index']);
            Route::post('/payment-settings', [PaymentSettingController::class, 'update']);
        });
    });
});
