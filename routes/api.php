<?php

use App\Http\Controllers\Api\V1\AuditApiController;
use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Public auth routes
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);

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

        // Admin-only approval routes
        Route::prefix('admin')->group(function () {
            Route::get('/audits/pending', [AuditApiController::class, 'pending']);
            Route::post('/audits/{audit}/approve', [AuditApiController::class, 'approve']);
            Route::post('/audits/{audit}/reject', [AuditApiController::class, 'reject']);
        });
    });
});
