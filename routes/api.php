<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\StreamController;
use App\Http\Controllers\Api\ChurchController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TestimonyController; // Added
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\VideoController;
use App\Http\Controllers\Api\Admin\AdminStreamController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\PaymentSettingController;
use App\Http\Controllers\Api\Admin\AdminTestimonyController; // Added
use App\Http\Controllers\Api\Calendar\CalendarEventController;
use App\Http\Controllers\Api\Calendar\CalendarSubscriptionController;
use App\Http\Controllers\Api\Calendar\CalendarStatsController;
use App\Models\CalendarEvent;
use App\Models\CalendarSubscription;
use App\Services\CalendarSubscriptionService;

Route::prefix('v1')->group(function () {

    // --- User Testimony Submission (Floating Chat) ---
    Route::post('/submit-testimony', [TestimonyController::class, 'submitForm'])
        ->middleware('auth:sanctum');

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
            // Fixed naming inside the group
            Route::post('/webhook', [PaymentController::class, 'handleWebhook'])->name('api.payments.webhook');
        });

        // Calendar Events
        Route::prefix('calendar')->group(function () {
            
            // Calendar Events
            Route::get('/events', [CalendarEventController::class, 'index']);
            Route::get('/events/upcoming', [CalendarEventController::class, 'upcoming']);
            Route::post('/events', [CalendarEventController::class, 'store']);
            Route::get('/events/{event}', [CalendarEventController::class, 'show']);
            Route::put('/events/{event}', [CalendarEventController::class, 'update']);
            Route::delete('/events/{event}', [CalendarEventController::class, 'destroy']);
            
            // Event Export
            Route::get('/events/{event}/export', [CalendarEventController::class, 'export']);
            
            // Event Images
            Route::post('/events/{event}/images', [CalendarEventController::class, 'uploadImage']);
            Route::patch('/events/{event}/images/{image}/primary', [CalendarEventController::class, 'setPrimaryImage']);
            
            // Event Subscriptions (shared calendars)
            Route::prefix('events/{event}')->group(function () {
                Route::get('/subscriptions', [CalendarSubscriptionController::class, 'index']);
                Route::post('/subscriptions', [CalendarSubscriptionController::class, 'store']);
                Route::put('/subscriptions/{subscription}', [CalendarSubscriptionController::class, 'update']);
                Route::delete('/subscriptions/{subscription}', [CalendarSubscriptionController::class, 'destroy']);
                Route::post('/subscriptions/invite', [CalendarSubscriptionController::class, 'invite']);
            });
            
            // Calendar Statistics
            Route::get('/stats', [CalendarStatsController::class, 'index']);
            Route::get('/stats/upcoming', [CalendarStatsController::class, 'upcoming']);
            Route::get('/stats/busy-days', [CalendarStatsController::class, 'busyDays']);
            
            // Calendar Statistics Routes
            Route::prefix('stats')->group(function () {
                Route::get('/', [CalendarStatsController::class, 'index']);
                Route::get('/upcoming', [CalendarStatsController::class, 'upcoming']);
                Route::get('/busy-days', [CalendarStatsController::class, 'busyDays']);
                Route::get('/type-distribution', [CalendarStatsController::class, 'typeDistribution']);
                Route::get('/platform-usage', [CalendarStatsController::class, 'platformUsage']);
                Route::get('/duration', [CalendarStatsController::class, 'durationStats']);
                Route::get('/attendance', [CalendarStatsController::class, 'attendanceStats']);
                Route::get('/time-patterns', [CalendarStatsController::class, 'timePatterns']);
                Route::get('/productivity', [CalendarStatsController::class, 'productivity']);
                Route::get('/comparison', [CalendarStatsController::class, 'comparison']);
                Route::get('/top-collaborators', [CalendarStatsController::class, 'topCollaborators']);
                Route::get('/locations', [CalendarStatsController::class, 'locationStats']);
                Route::get('/recurring', [CalendarStatsController::class, 'recurringStats']);
                Route::get('/media', [CalendarStatsController::class, 'mediaStats']);
                Route::get('/status', [CalendarStatsController::class, 'statusStats']);
                Route::get('/custom', [CalendarStatsController::class, 'custom']);
                Route::get('/export', [CalendarStatsController::class, 'export']);
                
                // Admin only stats
                Route::middleware('is_admin')->group(function () {
                    Route::get('/admin', [CalendarStatsController::class, 'adminStats']);
                });
            });
            
            // Subscription routes
            Route::prefix('events/{event}/subscriptions')->group(function () {
                Route::get('/', [CalendarSubscriptionController::class, 'index']);
                Route::post('/', [CalendarSubscriptionController::class, 'store']);
                Route::put('/bulk', [CalendarSubscriptionController::class, 'bulkUpdate']);
                Route::delete('/bulk', [CalendarSubscriptionController::class, 'bulkDestroy']);
                Route::post('/invite', [CalendarSubscriptionController::class, 'invite']);
                
                Route::prefix('{subscription}')->group(function () {
                    Route::get('/', function (CalendarEvent $event, CalendarSubscription $subscription) {
                        return app(CalendarSubscriptionController::class)->show($event, $subscription);
                    });
                    Route::put('/', [CalendarSubscriptionController::class, 'update']);
                    Route::delete('/', [CalendarSubscriptionController::class, 'destroy']);
                    Route::post('/accept', [CalendarSubscriptionController::class, 'accept']);
                    Route::post('/decline', [CalendarSubscriptionController::class, 'decline']);
                });
            });
            
            // User's subscription routes
            Route::prefix('my-subscriptions')->group(function () {
                Route::get('/', [CalendarSubscriptionController::class, 'userSubscriptions']);
                Route::get('/pending', function () {
                    $service = app(CalendarSubscriptionService::class);
                    return response()->json([
                        'success' => true,
                        'data' => $service->getPendingInvitations(auth()->id())
                    ]);
                });
                Route::get('/pending-count', function () {
                    $service = app(CalendarSubscriptionService::class);
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'count' => $service->countPendingInvitations(auth()->id())
                        ]
                    ]);
                });
            });
            
            // Check subscription status
            Route::get('/events/{event}/check-subscription', [CalendarSubscriptionController::class, 'checkSubscription']);
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
            Route::get('/payments', [AdminPaymentController::class, 'index']); // Used Alias
            Route::get('/payments/stats', [AdminPaymentController::class, 'stats']);
            Route::patch('/payments/{id}/status', [AdminPaymentController::class, 'updateStatus']);

            Route::get('/payment-settings', [PaymentSettingController::class, 'index']);
            Route::post('/payment-settings', [PaymentSettingController::class, 'update']);

            // --- Admin Testimony Management ---
            Route::prefix('testimonies')->group(function () {
                Route::get('/', [AdminTestimonyController::class, 'index']);
                Route::post('/', [AdminTestimonyController::class, 'store']);
                Route::put('/{id}', [AdminTestimonyController::class, 'update']);
                Route::delete('/{id}', [AdminTestimonyController::class, 'destroy']);

                // Export Routes
                Route::get('/export/excel', [AdminTestimonyController::class, 'downloadExcel']);
                Route::get('/export/word', [AdminTestimonyController::class, 'downloadWord']);
            });
        });
    });
});
