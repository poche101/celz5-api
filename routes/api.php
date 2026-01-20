<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\VideoController;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // --- 1. Public Authentication Routes ---
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/login/kingschat', [AuthController::class, 'handleKingsChatCallback']);

    // --- 2. Password Reset ---
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // --- 3. Protected Member Routes (Any Logged In User) ---
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/profile', [ProfileController::class, 'getProfile']);
        Route::post('/profile/update', [ProfileController::class, 'updateProfile']);
    });

    // --- 4. Admin Dashboard Routes (Admin Only) ---
    Route::middleware(['auth:sanctum', 'is_admin'])->prefix('admin')->group(function () {

        // User Management
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);

        // Video Management
        Route::post('/videos', [VideoController::class, 'store']);
        Route::post('/videos/{video}', [VideoController::class, 'update']);
        Route::delete('/videos/{video}', [VideoController::class, 'destroy']);
    });

});
