<?php

use App\Http\Controllers\Api\V1\AccessCodeController;
use App\Http\Controllers\Api\V1\AuditApiController;
use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Internal auditor login
    Route::post('/login', [AuthController::class, 'login']);

    // External auditor: redeem access code (rate-limited)
    Route::post('/external/redeem', [AuthController::class, 'redeemCode'])
        ->middleware('throttle:5,1');

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // Audit resources
        Route::get('/criteria', [AuditApiController::class, 'criteria']);
        Route::get('/spaces/search', [AuditApiController::class, 'searchSpace']);
        Route::get('/spaces/{space}', [AuditApiController::class, 'showSpace']);

        // Audits CRUD
        Route::get('/audits', [AuditApiController::class, 'index']);
        Route::post('/audits', [AuditApiController::class, 'store']);
        Route::get('/audits/{audit}', [AuditApiController::class, 'show']);
        Route::post('/audits/{audit}/photos', [AuditApiController::class, 'uploadPhotos']);

        // Admin-only routes
        Route::prefix('admin')->group(function () {
            // Approval
            Route::get('/audits/pending', [AuditApiController::class, 'pending']);
            Route::post('/audits/{audit}/approve', [AuditApiController::class, 'approve']);
            Route::post('/audits/{audit}/reject', [AuditApiController::class, 'reject']);

            // Access code management
            Route::get('/access-codes', [AccessCodeController::class, 'index']);
            Route::post('/access-codes', [AccessCodeController::class, 'store']);
            Route::get('/access-codes/{code}', [AccessCodeController::class, 'show']);
            Route::post('/access-codes/{code}/revoke', [AccessCodeController::class, 'revoke']);
        });
    });
});
