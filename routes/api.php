<?php

use App\Http\Controllers\Admin\AdminComplaintController;
use App\Http\Controllers\Admin\PharmacyVerificationController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\StatisticsController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BroadcastRequestController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MedLink API Routes — v1
|--------------------------------------------------------------------------
| Base URL: /api/v1
*/

Route::prefix('v1')->group(function () {

    // ──────────────────────────────────────────────────────────────────────
    // Public — no auth required
    // ──────────────────────────────────────────────────────────────────────

    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login',    [AuthController::class, 'login']);
    });

    // Public medicine/pharmacy browsing (optional auth for isFavorite flag)
    // Public routes — no auth required
    Route::get('medicines/categories',          [MedicineController::class, 'categories']);
    Route::get('medicines',                     [MedicineController::class, 'index']);
    Route::get('medicines/{id}',                [MedicineController::class, 'show']);
    Route::get('pharmacies/areas',              [PharmacyController::class, 'areas']);
    Route::get('pharmacies',                    [PharmacyController::class, 'index']);
    Route::get('pharmacies/{id}',               [PharmacyController::class, 'show']);
    Route::get('reviews/pharmacy/{pharmacyId}', [ReviewController::class, 'forPharmacy']);

    // ──────────────────────────────────────────────────────────────────────
    // Authenticated (any role)
    // ──────────────────────────────────────────────────────────────────────

    Route::middleware('auth:api')->group(function () {
        // Auth
        Route::post('auth/logout',  [AuthController::class, 'logout']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);

        // Profile
        Route::get('users/me',                [UserController::class, 'me']);
        Route::put('users/me',                [UserController::class, 'update']);
        Route::post('users/change-password',  [UserController::class, 'changePassword']);
        Route::post('users/upload-avatar',    [UserController::class, 'uploadAvatar']);

        // Orders — read for all roles, restricted writes below
        Route::get('orders',        [OrderController::class, 'index']);
        Route::get('orders/{id}',   [OrderController::class, 'show']);
    });

    // ──────────────────────────────────────────────────────────────────────
    // Citizen-only
    // ──────────────────────────────────────────────────────────────────────

    Route::middleware(['auth:api', 'role:citizen'])->group(function () {
        // Orders
        Route::post('orders',           [OrderController::class, 'store']);
        Route::delete('orders/{id}',    [OrderController::class, 'destroy']);

        // Broadcast requests
        Route::get('requests',                              [BroadcastRequestController::class, 'index']);
        Route::post('requests',                             [BroadcastRequestController::class, 'store']);
        Route::delete('requests/{id}',                      [BroadcastRequestController::class, 'destroy']);
        Route::post('requests/{id}/accept/{pharmacyId}',    [BroadcastRequestController::class, 'accept']);

        // Favorites
        Route::get('favorites',          [FavoriteController::class, 'index']);
        Route::post('favorites',         [FavoriteController::class, 'store']);
        Route::post('favorites/toggle',  [FavoriteController::class, 'toggle']);
        Route::delete('favorites/{id}',  [FavoriteController::class, 'destroy']);

        // Reviews
        Route::post('reviews',           [ReviewController::class, 'store']);
        Route::delete('reviews/{id}',    [ReviewController::class, 'destroy']);

        // Complaints
        Route::get('complaints',         [ComplaintController::class, 'index']);
        Route::post('complaints',        [ComplaintController::class, 'store']);
    });

    // ──────────────────────────────────────────────────────────────────────
    // Pharmacy-only
    // ──────────────────────────────────────────────────────────────────────

    Route::middleware(['auth:api', 'role:pharmacy', 'pharmacy.verified'])->group(function () {
        // Orders
        Route::put('orders/{id}/status', [OrderController::class, 'updateStatus']);

        // Inventory
        Route::get('inventory',          [InventoryController::class, 'index']);
        Route::post('inventory',         [InventoryController::class, 'store']);
        Route::put('inventory/{id}',     [InventoryController::class, 'update']);
        Route::delete('inventory/{id}',  [InventoryController::class, 'destroy']);

        // Network broadcast requests
        Route::get('requests/network',              [BroadcastRequestController::class, 'network']);
        Route::post('requests/{id}/respond',        [BroadcastRequestController::class, 'respond']);
    });

    // ──────────────────────────────────────────────────────────────────────
    // Admin-only
    // ──────────────────────────────────────────────────────────────────────

    Route::middleware(['auth:api', 'role:admin'])->prefix('admin')->group(function () {
        // Users
        Route::get('users',                     [UserManagementController::class, 'index']);
        Route::get('users/{id}',                [UserManagementController::class, 'show']);
        Route::patch('users/{id}/toggle-active',[UserManagementController::class, 'toggleActive']);
        Route::delete('users/{id}',             [UserManagementController::class, 'destroy']);

        // Pharmacy verification
        Route::get('pharmacies/verification',   [PharmacyVerificationController::class, 'pending']);
        Route::put('pharmacies/{id}/verify',    [PharmacyVerificationController::class, 'verify']);

        // Medicines (admin CRUD)
        Route::post('medicines',                [MedicineController::class, 'store']);
        Route::put('medicines/{id}',            [MedicineController::class, 'update']);
        Route::delete('medicines/{id}',         [MedicineController::class, 'destroy']);

        // Complaints
        Route::get('complaints',                [AdminComplaintController::class, 'index']);
        Route::put('complaints/{id}',           [AdminComplaintController::class, 'update']);
        Route::post('complaints/{id}/assign',   [AdminComplaintController::class, 'assign']);

        // Statistics & reports
        Route::get('statistics',                [StatisticsController::class, 'index']);
        Route::get('reports',                   [ReportController::class, 'index']);

        // Admin can also update order status
        Route::put('orders/{id}/status',        [OrderController::class, 'updateStatus']);
    });
});
