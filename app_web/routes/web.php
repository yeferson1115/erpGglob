<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\BusinessRegistrationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\CreditApplicationController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\AdminCreditApplicationController;
use App\Http\Controllers\AdminCreditPaymentController;
use App\Http\Controllers\AdminPlatformController;
use App\Http\Controllers\PublicCreditPortalController;

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::get('/', [PublicCreditPortalController::class, 'home'])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/registro-negocio', [BusinessRegistrationController::class, 'create'])->name('business.register');
    Route::post('/registro-negocio', [BusinessRegistrationController::class, 'store'])->name('business.register.store');
});

Route::middleware('auth')->group(function () {  
  Route::get('/dashboard', [DashboardController::class, 'index'])->name('index');

   Route::post('orders/{order}/cancel', [OrderController::class, 'cancelOrder'])->name('orders.cancel');
    Route::post('orders/{order}/restore', [OrderController::class, 'restoreOrder'])->name('orders.restore');
    Route::get('reports/orders/cancelled', [OrderController::class, 'cancelledOrdersReport'])->name('reports.orders.cancelled');

   
  // Resources
    
    Route::resource('users', UserController::class);
    Route::put('users/{user}/services', [UserController::class, 'updateServices'])->name('users.services.update');
    Route::resource('permission', PermissionController::class);
    Route::get('/roles/{roleId}/permissions/edit', [PermissionController::class, 'edit'])->name('permissions.edit');
    Route::put('/roles/{roleId}/permissions', [PermissionController::class, 'update'])->name('permissions.update');
    
    Route::resource('companies', CompanyController::class)->except(['show']);
    Route::resource('plans', PlanController::class)->except(['show']);
    Route::post('companies/{company}/cashiers', [CompanyController::class, 'storeCashier'])->name('companies.cashiers.store');
    Route::delete('companies/{company}/users/{user}', [CompanyController::class, 'unassignBusinessUser'])->name('companies.users.unassign');
    Route::put('companies/{company}/users/{user}/role', [CompanyController::class, 'updateBusinessUserRole'])->name('companies.users.role.update');
    Route::post('companies/{company}/assign-user', [CompanyController::class, 'assignExistingUser'])->name('companies.users.assign');


    Route::get('admin/platform', [AdminPlatformController::class, 'index'])->name('admin.platform.index');
    Route::post('admin/platform/marketing', [AdminPlatformController::class, 'storeMarketing'])->name('admin.platform.marketing.store');
    Route::post('admin/platform/promotions', [AdminPlatformController::class, 'storePromotion'])->name('admin.platform.promotions.store');
    Route::post('admin/platform/catalog', [AdminPlatformController::class, 'storeCatalog'])->name('admin.platform.catalog.store');

});

Route::middleware(['auth'])->get('/api/products/{product}/addons', function(\App\Models\Product $product){
    return $product->addons()->get(['id','name','price']);
});

// En routes/web.php
Route::middleware('guest')->group(function () {
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});


Route::get('/clear-cache', function () {
  echo Artisan::call('config:clear');
  echo Artisan::call('config:cache');
  echo Artisan::call('cache:clear');
  echo Artisan::call('route:clear');
  echo Artisan::call('view:clear');
});