<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\GuestProfileController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\ChannelManagerController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ContentManagementController;
use App\Http\Controllers\AdvancedSearchController;
use App\Http\Controllers\AdminDashboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes (no authentication required)
Route::prefix('v1')->group(function () {
    
    // Advanced Search & Discovery
    Route::prefix('search')->group(function () {
        Route::post('/', [SearchController::class, 'search']);
        Route::get('/suggestions', [SearchController::class, 'suggestions']);
        Route::get('/popular-destinations', [SearchController::class, 'popularDestinations']);
        Route::get('/filters', [SearchController::class, 'getFilters']);
        
        // Advanced search endpoints
        Route::post('/resorts', [AdvancedSearchController::class, 'searchResorts']);
        Route::get('/autocomplete', [AdvancedSearchController::class, 'getSearchSuggestions']);
        Route::get('/available-filters', [AdvancedSearchController::class, 'getSearchFilters']);
        Route::get('/popular', [AdvancedSearchController::class, 'getPopularSearches']);
    });
    
    // Availability & Pricing
    Route::prefix('availability')->group(function () {
        Route::post('/check', [AvailabilityController::class, 'checkAvailability']);
        Route::post('/calendar', [AvailabilityController::class, 'getCalendarView']);
        Route::get('/rates/{ratePlanId}', [AvailabilityController::class, 'rates']);
    });
    
    // Booking Management (some public for guest bookings)
    Route::prefix('bookings')->group(function () {
        Route::post('/', [BookingController::class, 'create']);
        Route::get('/{bookingReference}', [BookingController::class, 'show']);
        Route::put('/{bookingReference}', [BookingController::class, 'update']);
        Route::delete('/{bookingReference}', [BookingController::class, 'cancel']);
        Route::get('/{bookingReference}/payment-status', [BookingController::class, 'paymentStatus']);
    });
    
    // Guest Profile Management
    Route::prefix('guests')->group(function () {
        Route::post('/profile', [GuestProfileController::class, 'createOrUpdate']);
        Route::get('/profile/{guestId?}', [GuestProfileController::class, 'show']);
        Route::get('/{guestId}/bookings', [GuestProfileController::class, 'bookingHistory']);
        Route::put('/{guestId}/preferences', [GuestProfileController::class, 'updatePreferences']);
        Route::post('/{guestId}/loyalty', [GuestProfileController::class, 'joinLoyaltyProgram']);
        Route::put('/{guestId}/contact', [GuestProfileController::class, 'updateContact']);
    });
    
    // Payment Webhooks (no auth - external services)
    Route::prefix('webhooks')->group(function () {
        Route::post('/payment/bml', [PaymentWebhookController::class, 'bmlWebhook']);
        Route::post('/payment/stripe', [PaymentWebhookController::class, 'stripeWebhook']);
        Route::post('/payment/generic', [PaymentWebhookController::class, 'genericWebhook']);
    });
    
    // Channel Manager Integration (API key authentication)
    Route::prefix('channel-manager')->middleware(['api.key'])->group(function () {
        Route::get('/inventory', [ChannelManagerController::class, 'getInventory']);
        Route::get('/rates', [ChannelManagerController::class, 'getRates']);
        Route::post('/inventory', [ChannelManagerController::class, 'updateInventory']);
        Route::post('/rates', [ChannelManagerController::class, 'updateRates']);
        Route::post('/sync/{resortId}', [ChannelManagerController::class, 'syncResort']);
    });
    
    // Content Management (Public endpoints)
    Route::prefix('content')->group(function () {
        Route::get('/settings', [ContentManagementController::class, 'getSettings']);
        Route::get('/page/{slug}', [ContentManagementController::class, 'getPageContent']);
        Route::get('/menu/{location?}', [ContentManagementController::class, 'getMenus']);
        Route::get('/resort/{resortId}', [ContentManagementController::class, 'getResortContent']);
    });
    
    // File Management (Public access for display)
    Route::prefix('files')->group(function () {
        Route::get('/{filename}', [FileUploadController::class, 'getFile']);
        Route::get('/list/{type?}', [FileUploadController::class, 'listFiles']);
        Route::get('/stats/storage', [FileUploadController::class, 'getStorageStats']);
    });
});

// Protected routes with Sanctum authentication
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    
    // User information
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    // Protected booking operations (for logged-in users)
    Route::prefix('my')->group(function () {
        Route::get('/bookings', function (Request $request) {
            $user = $request->user();
            $bookings = \App\Models\Booking::where('guest_id', $user->id)
                ->with(['resort', 'ratePlan.roomType'])
                ->orderBy('created_at', 'desc')
                ->paginate(10);
            
            return response()->json([
                'success' => true,
                'data' => $bookings
            ]);
        });
        
        Route::get('/profile', function (Request $request) {
            return response()->json([
                'success' => true,
                'data' => new \App\Http\Resources\Api\GuestProfileResource($request->user())
            ]);
        });
    });
    
    // Admin routes (additional role check needed)
    Route::prefix('admin')->middleware(['role:admin'])->group(function () {
        // Dashboard & Analytics
        Route::get('/dashboard', [AdminDashboardController::class, 'overview']);
        Route::get('/dashboard/revenue', [AdminDashboardController::class, 'revenueReport']);
        Route::get('/dashboard/occupancy', [AdminDashboardController::class, 'occupancyReport']);
        Route::get('/dashboard/notifications', [AdminDashboardController::class, 'notificationStats']);
        Route::get('/dashboard/export', [AdminDashboardController::class, 'exportData']);
        
        // Reports & Analytics
        Route::prefix('reports')->group(function () {
            Route::get('/revenue', [ReportsController::class, 'revenueReport']);
            Route::get('/occupancy', [ReportsController::class, 'occupancyReport']);
            Route::get('/guests', [ReportsController::class, 'guestAnalytics']);
            Route::get('/performance', [ReportsController::class, 'performanceMetrics']);
            Route::post('/export', [ReportsController::class, 'exportReport']);
        });
        
        // File Management
        Route::prefix('files')->group(function () {
            Route::post('/upload', [FileUploadController::class, 'upload']);
            Route::post('/upload-multiple', [FileUploadController::class, 'uploadMultiple']);
            Route::delete('/{filename}', [FileUploadController::class, 'deleteFile']);
        });
        
        // Content Management (Admin operations)
        Route::prefix('content')->group(function () {
            Route::put('/settings', [ContentManagementController::class, 'updateSetting']);
            Route::post('/settings/bulk', [ContentManagementController::class, 'bulkUpdateSettings']);
            Route::delete('/settings/{key}', [ContentManagementController::class, 'deleteSetting']);
            Route::put('/page/{slug}', [ContentManagementController::class, 'updatePageContent']);
            Route::put('/menu/{location}', [ContentManagementController::class, 'updateMenu']);
        });
        
        // Advanced Search (Admin features)
        Route::prefix('search')->group(function () {
            Route::post('/guests', [AdvancedSearchController::class, 'searchGuests']);
        });
        
        // Existing admin routes
        Route::get('/bookings', function () {
            $bookings = \App\Models\Booking::with(['guest', 'resort', 'ratePlan.roomType'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);
            
            return response()->json([
                'success' => true,
                'data' => $bookings
            ]);
        });
    });
});

// API Documentation routes
Route::get('/docs', function () {
    return response()->json([
        'message' => 'Resort Booking API v1.0',
        'documentation' => url('/docs/api'),
        'endpoints' => [
            'search' => '/api/v1/search',
            'availability' => '/api/v1/availability',
            'bookings' => '/api/v1/bookings',
            'guests' => '/api/v1/guests',
            'webhooks' => '/api/v1/webhooks',
            'channel-manager' => '/api/v1/channel-manager'
        ]
    ]);
});

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0'
    ]);
});
