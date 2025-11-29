<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Landing', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', [App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Guest-accessible report creation flow (no auth required)
Route::get('/get-started', [App\Http\Controllers\ReportsController::class, 'getStarted'])->name('get-started');
Route::post('/reports/guest/payslip', [App\Http\Controllers\ReportsController::class, 'storeGuestPayslip'])->name('reports.storeGuestPayslip');
Route::patch('/reports/guest/step2', [App\Http\Controllers\ReportsController::class, 'updateGuestStep2'])->name('reports.updateGuestStep2');

Route::middleware(['auth', 'verified'])->group(function () {
    // Finalize guest report after login
    Route::post('/reports/guest/finalize', [App\Http\Controllers\ReportsController::class, 'finalizeGuestReport'])->name('reports.finalizeGuestReport');
    
    // Authenticated report routes
    Route::get('/reports', [App\Http\Controllers\ReportsController::class, 'index'])->name('reports.index');
    Route::get('/reports/create', [App\Http\Controllers\ReportsController::class, 'create'])->name('reports.create');
    Route::post('/reports/payslip', [App\Http\Controllers\ReportsController::class, 'storePayslip'])->name('reports.storePayslip');
    Route::post('/reports', [App\Http\Controllers\ReportsController::class, 'store'])->name('reports.store');
    Route::patch('/reports/{report}/step1', [App\Http\Controllers\ReportsController::class, 'updateStep1'])->name('reports.updateStep1');
    Route::patch('/reports/{report}/step2', [App\Http\Controllers\ReportsController::class, 'updateStep2'])->name('reports.updateStep2');
    Route::get('/reports/{report}', [App\Http\Controllers\ReportsController::class, 'show'])->name('reports.show');
    
    // Payslip review routes
    Route::get('/payslips/review', [App\Http\Controllers\PayslipReviewController::class, 'index'])->name('payslips.review.index');
    Route::post('/payslips/{payslip}/approve', [App\Http\Controllers\PayslipReviewController::class, 'approve'])->name('payslips.review.approve');
    Route::post('/payslips/{payslip}/deny', [App\Http\Controllers\PayslipReviewController::class, 'deny'])->name('payslips.review.deny');
    Route::patch('/payslips/{payslip}/experience', [App\Http\Controllers\PayslipReviewController::class, 'updateExperience'])->name('payslips.review.updateExperience');
    Route::patch('/payslips/{payslip}/job-title', [App\Http\Controllers\PayslipReviewController::class, 'updateJobTitle'])->name('payslips.review.updateJobTitle');
    Route::patch('/payslips/{payslip}/region', [App\Http\Controllers\PayslipReviewController::class, 'updateRegion'])->name('payslips.review.updateRegion');
    Route::patch('/payslips/{payslip}/salary', [App\Http\Controllers\PayslipReviewController::class, 'updateSalary'])->name('payslips.review.updateSalary');
    Route::patch('/payslips/{payslip}/company-pension-dkk', [App\Http\Controllers\PayslipReviewController::class, 'updateCompanyPensionDkk'])->name('payslips.review.updateCompanyPensionDkk');
    Route::patch('/payslips/{payslip}/company-pension-procent', [App\Http\Controllers\PayslipReviewController::class, 'updateCompanyPensionProcent'])->name('payslips.review.updateCompanyPensionProcent');
    Route::patch('/payslips/{payslip}/salary-supplement', [App\Http\Controllers\PayslipReviewController::class, 'updateSalarySupplement'])->name('payslips.review.updateSalarySupplement');
    Route::patch('/payslips/{payslip}/hours-monthly', [App\Http\Controllers\PayslipReviewController::class, 'updateHoursMonthly'])->name('payslips.review.updateHoursMonthly');
    Route::patch('/payslips/{payslip}/area-of-responsibility', [App\Http\Controllers\PayslipReviewController::class, 'updateAreaOfResponsibility'])->name('payslips.review.updateAreaOfResponsibility');
    Route::post('/payslips/{payslip}/document', [App\Http\Controllers\PayslipReviewController::class, 'uploadDocument'])->name('payslips.review.uploadDocument');
});

require __DIR__.'/settings.php';
