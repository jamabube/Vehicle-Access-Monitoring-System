<?php

use App\Http\Controllers\AccessLogController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\RfidAssignmentController;
use App\Http\Controllers\RfidReaderController;
use App\Http\Controllers\RfidTagController;
use App\Http\Controllers\SystemLogController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\VisitorController;
use App\Http\Controllers\VisitorVisitController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:login');

    Route::get('forgot-password', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [App\Http\Controllers\Auth\ResetPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('reset-password', [App\Http\Controllers\Auth\ResetPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.update');
});

Route::middleware(['auth', 'account.active'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/gate-activity', [DashboardController::class, 'gateActivity'])
        ->name('dashboard.gate-activity');

    // Reporting views (read-only history). 'export' is registered before the
    // index only for grouping; neither collides with a resource route.
    Route::get('access-logs', [AccessLogController::class, 'index'])->name('access-logs.index');
    // Stricter rate limit for CSV export to prevent resource exhaustion
    Route::get('access-logs/export', [AccessLogController::class, 'export'])
        ->middleware('throttle:5,1')
        ->name('access-logs.export');
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('system-logs', [SystemLogController::class, 'index'])->name('system-logs.index');

    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::resource('users', App\Http\Controllers\UserController::class);
    Route::resource('employees', EmployeeController::class);
    Route::resource('vehicles', VehicleController::class);
    Route::post('vehicles/{vehicle}/update-state', [VehicleController::class, 'updateState'])
        ->name('vehicles.update-state');
    Route::resource('visitors', VisitorController::class);
    Route::resource('visitor-visits', VisitorVisitController::class);
    Route::post('visitor-visits/{visitor_visit}/check-out', [VisitorVisitController::class, 'checkOut'])
        ->name('visitor-visits.check-out');
    // Registered before the resource so 'recent-scans' is not swallowed by the
    // resource's rfid-tags/{rfid_tag} show route.
    Route::get('rfid-tags/recent-scans', [RfidTagController::class, 'recentScans'])
        ->name('rfid-tags.recent-scans');
    Route::resource('rfid-tags', RfidTagController::class);
    Route::resource('rfid-readers', RfidReaderController::class);
    Route::post('rfid-readers/{rfid_reader}/regenerate-credentials', [RfidReaderController::class, 'regenerateCredentials'])
        ->name('rfid-readers.regenerate-credentials');
    Route::resource('rfid-assignments', RfidAssignmentController::class);
    Route::post('rfid-assignments/{rfid_assignment}/release', [RfidAssignmentController::class, 'release'])
        ->name('rfid-assignments.release');
});
