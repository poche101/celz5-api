<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\StreamController;
use App\Http\Controllers\Api\ChurchController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TestimonyController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\VideoController;
use App\Http\Controllers\Api\Admin\AdminStreamController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\PaymentSettingController;
use App\Http\Controllers\Api\Admin\AdminTestimonyController;

Route::prefix('v1')->group(function () {

    // --- 1. Public Routes ---
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // --- 2. External Webhooks (No Auth Required) ---
    Route::post('/payments/webhook', [PaymentController::class, 'handleWebhook'])->name('api.payments.webhook');

    // --- 3. Protected Routes (Requires Sanctum Token) ---
    Route::middleware('auth:sanctum')->group(function () {

        // User Testimony Submission
        Route::post('/submit-testimony', [TestimonyController::class, 'submitForm']);

        // Profile Management
        Route::get('/profile', [ProfileController::class, 'getProfile']);
        Route::post('/profile/update', [ProfileController::class, 'updateProfile']);

        // Church Locator
        Route::get('/churches/locate', [ChurchController::class, 'locateBySearch']);

        // Streaming & Engagement
        Route::prefix('stream')->group(function () {
            Route::post('/{programId}/enter', [StreamController::class, 'enterStream']);
            Route::post('/{programId}/comment', [StreamController::class, 'postComment']);
            Route::get('/{programId}/comments', [StreamController::class, 'getComments']);
        });

        // --- Restricted Payment Routes ---
        // These require a complete profile (Church, Group, Cell)
        Route::middleware('profile_complete')->prefix('payments')->group(function () {
            Route::post('/pay', [PaymentController::class, 'initiatePayment']);
            Route::post('/save-card', [PaymentController::class, 'saveCard']);
            Route::get('/saved-cards', function() {
                return auth()->user()->cards;
            });
        });

        // --- 4. Admin Dashboard Routes (Admin Only) ---
        Route::middleware('is_admin')->prefix('admin')->group(function () {

            // User Management
            Route::apiResource('users', UserController::class);

            // Video Management
            Route::post('/videos', [VideoController::class, 'store']);
            Route::post('/videos/{video}', [VideoController::class, 'update']);
            Route::delete('/videos/{video}', [VideoController::class, 'destroy']);

            // Stream Management
            Route::post('/programs', [AdminStreamController::class, 'storeProgram']);
            Route::get('/programs/{programId}/attendance', [AdminStreamController::class, 'getAttendance']);
            Route::delete('/comments/{id}', [AdminStreamController::class, 'deleteComment']);

            // Payment & Settings Management
            Route::get('/payments', [AdminPaymentController::class, 'index']);
            Route::get('/payments/stats', [AdminPaymentController::class, 'stats']);
            Route::patch('/payments/{id}/status', [AdminPaymentController::class, 'updateStatus']);
            Route::get('/payment-settings', [PaymentSettingController::class, 'index']);
            Route::post('/payment-settings', [PaymentSettingController::class, 'update']);

            // Testimony Management
            Route::prefix('testimonies')->group(function () {
                Route::get('/', [AdminTestimonyController::class, 'index']);
                Route::post('/', [AdminTestimonyController::class, 'store']);
                Route::put('/{id}', [AdminTestimonyController::class, 'update']);
                Route::delete('/{id}', [AdminTestimonyController::class, 'destroy']);
                Route::get('/export/excel', [AdminTestimonyController::class, 'downloadExcel']);
                Route::get('/export/word', [AdminTestimonyController::class, 'downloadWord']);
            });
        });
    });
});
