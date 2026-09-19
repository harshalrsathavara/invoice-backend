<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupImportController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Invoice API, v1
|--------------------------------------------------------------------------
|
| Every route below is addressed by UUID, never by the server's
| auto-increment id — the handset creates rows offline and has never seen a
| server id. Nested routes use scoped bindings, so a bill belonging to another
| business is a 404 rather than a leak.
|
*/

Route::prefix('v1')->group(function () {

    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1');

    // Signing in with a phone number and a one-time code. Requesting is the
    // cheaper call to abuse, so it is held to three a minute; verifying gets
    // the same allowance as a password, and a wrong code is also counted
    // against the code itself.
    Route::post('auth/otp/request', [OtpController::class, 'request'])
        ->middleware('throttle:3,1');

    Route::post('auth/otp/verify', [OtpController::class, 'verify'])
        ->middleware('throttle:6,1');

    Route::middleware('auth:sanctum')->group(function () {

        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // ---- sync: the handset's main conversation with the server ----
        Route::post('sync/push', [SyncController::class, 'push']);
        Route::get('sync/pull', [SyncController::class, 'pull']);

        // ---- one-time seed from the app's own backup file ----
        Route::post('backup/inspect', [BackupImportController::class, 'inspect']);
        Route::post('backup/import', [BackupImportController::class, 'store']);

        // ---- direct REST, for anything that would rather not sync ----
        Route::apiResource('businesses', BusinessController::class)
            ->only(['index', 'store', 'show', 'update']);

        Route::get('businesses/{business}/image/{field}', [BusinessController::class, 'image'])
            ->whereIn('field', ['logo', 'signature']);

        Route::prefix('businesses/{business}')->scopeBindings()->group(function () {

            Route::apiResource('customers', CustomerController::class)
                ->only(['index', 'store', 'update', 'destroy']);

            Route::apiResource('items', ItemController::class)
                ->only(['index', 'store', 'update', 'destroy']);

            Route::apiResource('invoices', InvoiceController::class)
                ->only(['index', 'store', 'show', 'update', 'destroy']);

            Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void']);
            Route::post('invoices/{invoice}/unvoid', [InvoiceController::class, 'unvoid']);

            Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store']);
            Route::delete('invoices/{invoice}/payments/{payment}', [PaymentController::class, 'destroy']);

            Route::get('reports', ReportController::class);
        });
    });
});
