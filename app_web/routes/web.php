<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\CreditApplicationController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\AdminCreditApplicationController;
use App\Http\Controllers\AdminCreditPaymentController;
use App\Http\Controllers\PublicCreditPortalController;

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::get('/', [PublicCreditPortalController::class, 'home'])->name('home');

Route::get('/consultar-pagar-credito', [PublicCreditPortalController::class, 'index'])->name('credit-portal.index');
Route::post('/consultar-pagar-credito/pagar', [PublicCreditPortalController::class, 'startPayment'])->name('credit-portal.pay');
Route::get('/consultar-pagar-credito/checkout/{payment}', [PublicCreditPortalController::class, 'checkout'])->name('credit-portal.checkout');
Route::get('/consultar-pagar-credito/finalizar', [PublicCreditPortalController::class, 'finishPayment'])->name('credit-portal.finish');
Route::post('/consultar-pagar-credito/pagos/{payment}/actualizar', [PublicCreditPortalController::class, 'refreshPayment'])->name('credit-portal.refresh');
Route::post('/webhooks/wompi', [PublicCreditPortalController::class, 'wompiWebhook'])->name('credit-portal.wompi-webhook');

Route::get('/solicitud-credito', [CreditApplicationController::class, 'create'])->name('credit-applications.create');
Route::post('/solicitud-credito', [CreditApplicationController::class, 'store'])->name('credit-applications.store');
Route::post('/solicitud-credito/retomar', [CreditApplicationController::class, 'resume'])->name('credit-applications.resume');
Route::post('/solicitud-credito/enviar-codigo', [CreditApplicationController::class, 'sendPhoneCode'])->name('credit-applications.send-phone-code');
Route::post('/solicitud-credito/verificar-codigo', [CreditApplicationController::class, 'verifyPhoneCode'])->name('credit-applications.verify-phone-code');
Route::get('/solicitud-credito/{creditApplication}/pdf', [CreditApplicationController::class, 'downloadPdf'])->name('credit-applications.pdf');

Route::middleware('auth')->group(function () {  
  Route::get('/dashboard', [DashboardController::class, 'index'])->name('index');

   Route::post('orders/{order}/cancel', [OrderController::class, 'cancelOrder'])->name('orders.cancel');
    Route::post('orders/{order}/restore', [OrderController::class, 'restoreOrder'])->name('orders.restore');
    Route::get('reports/orders/cancelled', [OrderController::class, 'cancelledOrdersReport'])->name('reports.orders.cancelled');

   
  // Resources
    
    Route::resource('users', UserController::class);
    Route::resource('permission', PermissionController::class);
    Route::get('/roles/{roleId}/permissions/edit', [PermissionController::class, 'edit'])->name('permissions.edit');
    Route::put('/roles/{roleId}/permissions', [PermissionController::class, 'update'])->name('permissions.update');
    
    Route::resource('companies', CompanyController::class)->except(['show']);

    Route::get('admin/credit-applications', [AdminCreditApplicationController::class, 'index'])->name('admin.credit-applications.index');
    Route::get('admin/credit-applications/{creditApplication}', [AdminCreditApplicationController::class, 'show'])->name('admin.credit-applications.show');
    Route::patch('admin/credit-applications/{creditApplication}/status', [AdminCreditApplicationController::class, 'updateStatus'])->name('admin.credit-applications.update-status');
    Route::get('admin/credit-payments', [AdminCreditPaymentController::class, 'index'])->name('admin.credit-payments.index');
    Route::get('admin/credit-payments/export', [AdminCreditPaymentController::class, 'export'])->name('admin.credit-payments.export');
    Route::patch('admin/credit-payments/{payment}/status', [AdminCreditPaymentController::class, 'updateStatus'])->name('admin.credit-payments.update-status');

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
