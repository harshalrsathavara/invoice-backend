<?php

use App\Http\Controllers\Admin\BusinessController;
use App\Http\Controllers\Admin\ConflictController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeviceController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\ItemController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DemoSeedController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// Fills an empty server with something to look at, and says what is already
// there when it is opened again. No login: the point is to hand somebody a
// link. It answers 404 unless SEED_DEMO is on, and it only ever creates what
// is missing — see config/demo.php.
Route::get('demo-seed', DemoSeedController::class)->name('demo.seed');

Route::prefix('admin')->name('admin.')->group(function () {

    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->middleware('throttle:6,1');
    });

    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware(['auth', 'admin', 'business.scope'])->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        // ---- businesses ----
        // "create" is declared before "{business}", or it would be read as a uuid.
        Route::get('businesses', [BusinessController::class, 'index'])->name('businesses.index');
        // The header switcher. A POST because it changes what every other
        // page will show for the rest of the session.
        Route::post('businesses/switch', [BusinessController::class, 'switchTo'])->name('businesses.switch');
        Route::get('businesses/create', [BusinessController::class, 'create'])->name('businesses.create');
        Route::post('businesses', [BusinessController::class, 'store'])->name('businesses.store');
        Route::get('businesses/{business}/edit', [BusinessController::class, 'edit'])->name('businesses.edit');
        Route::put('businesses/{business}', [BusinessController::class, 'update'])->name('businesses.update');
        Route::delete('businesses/{business}', [BusinessController::class, 'destroy'])->name('businesses.destroy');
        Route::get('businesses/{business}/image/{field}', [BusinessController::class, 'image'])
            ->whereIn('field', ['logo', 'signature'])->name('businesses.image');
        Route::get('businesses/{business}', [BusinessController::class, 'show'])->name('businesses.show');

        // ---- documents ----
        // "create" is declared before "{invoice}", or it would be read as a uuid.
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
        Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
        Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
        Route::post('invoices/{invoice}/unvoid', [InvoiceController::class, 'unvoid'])->name('invoices.unvoid');
        // A bill deleted on a handset is soft-deleted here; these two reach it.
        Route::post('invoices/{invoice}/restore', [InvoiceController::class, 'restore'])->name('invoices.restore')->withTrashed();
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show')->withTrashed();

        Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::post('invoices/{invoice}/settle', [PaymentController::class, 'settle'])->name('payments.settle');
        Route::delete('invoices/{invoice}/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

        // ---- customers ----
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
        Route::post('customers/{customer}/status', [CustomerController::class, 'setStatus'])->name('customers.status');
        Route::get('customers/{customer}/statement', [CustomerController::class, 'statement'])->name('customers.statement');
        // Soft-deleted customers are still on file and the panel is where they
        // are looked at, so these two resolve trashed rows as well.
        Route::post('customers/{customer}/restore', [CustomerController::class, 'restore'])->name('customers.restore')->withTrashed();
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show')->withTrashed();

        // ---- item catalogue ----
        Route::get('items', [ItemController::class, 'index'])->name('items.index');
        Route::get('items/create', [ItemController::class, 'create'])->name('items.create');
        Route::post('items', [ItemController::class, 'store'])->name('items.store');
        Route::get('items/{item}/edit', [ItemController::class, 'edit'])->name('items.edit');
        Route::put('items/{item}', [ItemController::class, 'update'])->name('items.update');
        Route::delete('items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
        // Soft-deleted catalogue entries are still on file, so this resolves them.
        Route::post('items/{item}/restore', [ItemController::class, 'restore'])->name('items.restore')->withTrashed();

        // ---- owner accounts ----
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        // ---- reports ----
        Route::get('reports/gst', [ReportController::class, 'gst'])->name('reports.gst');
        Route::get('reports/payments', [ReportController::class, 'payments'])->name('reports.payments');
        Route::get('reports/overdue', [ReportController::class, 'overdue'])->name('reports.overdue');

        // ---- sync ----
        Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
        Route::post('devices/{device}/revoke', [DeviceController::class, 'revoke'])->name('devices.revoke');

        Route::get('conflicts', [ConflictController::class, 'index'])->name('conflicts.index');
        Route::post('conflicts/{conflict}/review', [ConflictController::class, 'review'])->name('conflicts.review');
    });
});
