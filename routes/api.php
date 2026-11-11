<?php

use App\Http\Controllers\Api\Admin\BookingAdminController;
use App\Http\Controllers\Api\Admin\CategoryAdminController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\ListingModerationController;
use App\Http\Controllers\Api\Admin\PaymentAdminController;
use App\Http\Controllers\Api\Admin\UserAdminController;
use App\Http\Controllers\Api\Admin\SupportAdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\EarningsController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\ListingController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SupportCaseController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Neighbours Rental API  –  base URL: /api/v1
|--------------------------------------------------------------------------
| Auth: "Authorization: Bearer {token}" (Laravel Sanctum). Always send "Accept: application/json".
*/

Route::prefix('v1')->group(function () {

    // ---- Public ----------------------------------------------------------
    Route::get('meta', [MetaController::class, 'meta']);
    Route::get('categories', [MetaController::class, 'categories']);

    // ---- Database Seeder (for Render environments without Web Shell) ----
    Route::match(['get', 'post'], 'dev/seed', function (\Illuminate\Http\Request $request) {
        $key = $request->input('key') ?? $request->header('X-Seed-Key');
        if ($key !== 'neighbours-rental-seed' && $key !== config('app.key')) {
            abort(403, 'Unauthorized');
        }

        $loc = strtolower((string) ($request->input('location') ?? $request->input('city') ?? 'edmonton'));
        if ($loc === 'indore' || $loc === 'mp' || $loc === 'india') {
            putenv('SEED_CITY=Indore');
            putenv('SEED_PROVINCE=MP');
            putenv('SEED_LAT=22.7179');
            putenv('SEED_LNG=75.8334');
            $_ENV['SEED_CITY'] = 'Indore';
            $_ENV['SEED_PROVINCE'] = 'MP';
            $_ENV['SEED_LAT'] = '22.7179';
            $_ENV['SEED_LNG'] = '75.8334';
            config(['neighbours.default_location' => ['lat' => 22.7179, 'lng' => 75.8334, 'label' => 'Indore, MP']]);
        } else {
            putenv('SEED_CITY=Edmonton');
            putenv('SEED_PROVINCE=AB');
            putenv('SEED_LAT=53.5444');
            putenv('SEED_LNG=-113.4909');
            $_ENV['SEED_CITY'] = 'Edmonton';
            $_ENV['SEED_PROVINCE'] = 'AB';
            $_ENV['SEED_LAT'] = '53.5444';
            $_ENV['SEED_LNG'] = '-113.4909';
            config(['neighbours.default_location' => ['lat' => 53.5461, 'lng' => -113.4938, 'label' => 'Edmonton, AB']]);
        }

        try {
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
            $city = env('SEED_CITY', 'Edmonton');
            $prov = env('SEED_PROVINCE', 'AB');
            return response()->json([
                'success' => true,
                'message' => "Database seeded successfully for {$city}, {$prov}!",
                'location' => [
                    'city' => $city,
                    'province' => $prov,
                    'default' => config('neighbours.default_location'),
                ],
                'output' => \Illuminate\Support\Facades\Artisan::output(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ], 500);
        }
    });

    Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('google', [AuthController::class, 'google']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });

    // ---- Authenticated ---------------------------------------------------
    Route::middleware('auth:sanctum')->group(function () {

        // Account
        Route::prefix('auth')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('verify-email', [AuthController::class, 'verifyEmail'])->middleware('throttle:10,1');
            Route::post('resend-verification', [AuthController::class, 'resendVerification'])->middleware('throttle:3,1');
            Route::post('change-email', [AuthController::class, 'changeEmail'])->middleware('throttle:5,1');
        });

        Route::prefix('profile')->group(function () {
            Route::put('/', [ProfileController::class, 'update']);
            Route::post('avatar', [ProfileController::class, 'uploadAvatar']);
            Route::put('location', [ProfileController::class, 'updateLocation']);
            Route::put('notification-settings', [ProfileController::class, 'updateNotificationSettings']);
            Route::put('password', [ProfileController::class, 'changePassword']);
            Route::delete('/', [ProfileController::class, 'destroy']);
        });

        Route::get('users/{user}', [UserController::class, 'show']);

        // Explore / search / item details
        Route::get('items', [ItemController::class, 'index']);
        Route::get('items/popular', [ItemController::class, 'popular']);
        Route::get('items/{item}', [ItemController::class, 'show']);
        Route::get('items/{item}/availability', [ItemController::class, 'availability']);
        Route::get('items/{item}/quote', [ItemController::class, 'quote']);

        // Favorites
        Route::get('favorites', [FavoriteController::class, 'index']);
        Route::post('items/{item}/favorite', [FavoriteController::class, 'store']);
        Route::delete('items/{item}/favorite', [FavoriteController::class, 'destroy']);

        // My listings (owner)
        Route::get('listings/summary', [ListingController::class, 'summary']);
        Route::get('listings', [ListingController::class, 'index']);
        Route::post('listings', [ListingController::class, 'store']);
        Route::get('listings/{item}', [ListingController::class, 'show']);
        Route::put('listings/{item}', [ListingController::class, 'update']);
        Route::delete('listings/{item}', [ListingController::class, 'destroy']);
        Route::post('listings/{item}/photos', [ListingController::class, 'uploadPhotos']);
        Route::put('listings/{item}/photos/order', [ListingController::class, 'reorderPhotos']);
        Route::delete('listings/{item}/photos/{photo}', [ListingController::class, 'deletePhoto']);
        Route::put('listings/{item}/availability', [ListingController::class, 'updateAvailability']);
        Route::post('listings/{item}/submit', [ListingController::class, 'submit']);
        Route::post('listings/{item}/pause', [ListingController::class, 'pause']);
        Route::post('listings/{item}/resume', [ListingController::class, 'resume']);

        // Bookings
        Route::get('bookings', [BookingController::class, 'index']);
        Route::post('bookings', [BookingController::class, 'store']);
        Route::get('bookings/{booking}', [BookingController::class, 'show']);
        Route::post('bookings/{booking}/accept', [BookingController::class, 'accept']);
        Route::post('bookings/{booking}/decline', [BookingController::class, 'decline']);
        Route::post('bookings/{booking}/pay', [BookingController::class, 'pay']);
        Route::get('bookings/{booking}/refund-estimate', [BookingController::class, 'refundEstimate']);
        Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel']);
        Route::post('bookings/{booking}/pickup', [BookingController::class, 'pickup']);
        Route::post('bookings/{booking}/return', [BookingController::class, 'returnItem']);
        Route::post('bookings/{booking}/confirm-return', [BookingController::class, 'confirmReturn']);
        Route::post('bookings/{booking}/review', [BookingController::class, 'review']);
        Route::get('bookings/{booking}/timeline', [BookingController::class, 'timeline']);
        Route::get('bookings/{booking}/receipt', [BookingController::class, 'receipt']);
        Route::get('bookings/{booking}/extension-quote', [BookingController::class, 'extensionQuote']);
        Route::post('bookings/{booking}/extensions', [BookingController::class, 'requestExtension']);
        Route::post('extensions/{extension}/approve', [BookingController::class, 'approveExtension']);
        Route::post('extensions/{extension}/decline', [BookingController::class, 'declineExtension']);

        // Messages
        Route::get('conversations', [ConversationController::class, 'index']);
        Route::post('conversations', [ConversationController::class, 'store']);
        Route::get('conversations/{conversation}', [ConversationController::class, 'show']);
        Route::get('conversations/{conversation}/messages', [ConversationController::class, 'messages']);
        Route::post('conversations/{conversation}/messages', [ConversationController::class, 'send'])->middleware('throttle:60,1');
        Route::post('conversations/{conversation}/read', [ConversationController::class, 'read']);

        // Notifications
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::delete('notifications/{notification}', [NotificationController::class, 'destroy']);

        // Payments (renter)
        Route::get('payment-methods', [PaymentController::class, 'methods']);
        Route::post('payment-methods', [PaymentController::class, 'addMethod']);
        Route::post('payment-methods/{paymentMethod}/default', [PaymentController::class, 'makeDefault']);
        Route::delete('payment-methods/{paymentMethod}', [PaymentController::class, 'removeMethod']);
        Route::get('billing-history', [PaymentController::class, 'history']);

        // Earnings (owner)
        Route::get('earnings', [EarningsController::class, 'index']);
        Route::get('earnings/payouts', [EarningsController::class, 'payouts']);
        Route::put('earnings/payout-account', [EarningsController::class, 'savePayoutAccount']);
        Route::post('earnings/withdraw', [EarningsController::class, 'withdraw']);

        // Support
        Route::get('support-cases', [SupportCaseController::class, 'index']);
        Route::post('support-cases', [SupportCaseController::class, 'store']);
        Route::get('support-cases/{supportCase}', [SupportCaseController::class, 'show']);

        // Admin
        Route::prefix('admin')->middleware('admin')->group(function () {
            Route::get('dashboard', DashboardController::class);

            Route::get('listings', [ListingModerationController::class, 'index']);
            Route::post('listings/{item}/pause', [ListingModerationController::class, 'pause']);
            Route::post('listings/{item}/approve', [ListingModerationController::class, 'approve']);
            Route::post('listings/{item}/reject', [ListingModerationController::class, 'reject']);
            Route::get('support-cases', [SupportAdminController::class, 'index']);
            Route::put('support-cases/{supportCase}', [SupportAdminController::class, 'update']);

            Route::get('users', [UserAdminController::class, 'index']);
            Route::get('users/{user}', [UserAdminController::class, 'show'])->withTrashed();
            Route::post('users/{user}/suspend', [UserAdminController::class, 'suspend']);
            Route::post('users/{user}/unsuspend', [UserAdminController::class, 'unsuspend']);
            Route::put('users/{user}/admin', [UserAdminController::class, 'setAdmin']);

            Route::get('bookings', [BookingAdminController::class, 'index']);
            Route::get('bookings/{booking}', [BookingAdminController::class, 'show']);

            Route::get('payments', [PaymentAdminController::class, 'index']);

            Route::get('categories', [CategoryAdminController::class, 'index']);
            Route::post('categories', [CategoryAdminController::class, 'store']);
            Route::put('categories/{category}', [CategoryAdminController::class, 'update']);
            Route::delete('categories/{category}', [CategoryAdminController::class, 'destroy']);
        });
    });
});
