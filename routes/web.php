<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', [App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/reports', [App\Http\Controllers\ReportsController::class, 'index'])->name('reports.index');
    Route::get('/reports/create', [App\Http\Controllers\ReportsController::class, 'create'])->name('reports.create');
    Route::post('/reports/payslip', [App\Http\Controllers\ReportsController::class, 'storePayslip'])->name('reports.storePayslip');
    Route::post('/reports', [App\Http\Controllers\ReportsController::class, 'store'])->name('reports.store');
    Route::patch('/reports/{report}/step1', [App\Http\Controllers\ReportsController::class, 'updateStep1'])->name('reports.updateStep1');
    Route::patch('/reports/{report}/step2', [App\Http\Controllers\ReportsController::class, 'updateStep2'])->name('reports.updateStep2');
    Route::get('/reports/{report}', [App\Http\Controllers\ReportsController::class, 'show'])->name('reports.show');
    Route::get('/payslips/review', [App\Http\Controllers\PayslipReviewController::class, 'index'])->name('payslips.review.index');
    Route::post('/payslips/{payslip}/approve', [App\Http\Controllers\PayslipReviewController::class, 'approve'])->name('payslips.review.approve');
    Route::post('/payslips/{payslip}/deny', [App\Http\Controllers\PayslipReviewController::class, 'deny'])->name('payslips.review.deny');
    Route::patch('/payslips/{payslip}/experience', [App\Http\Controllers\PayslipReviewController::class, 'updateExperience'])->name('payslips.review.updateExperience');
    Route::patch('/payslips/{payslip}/job-title', [App\Http\Controllers\PayslipReviewController::class, 'updateJobTitle'])->name('payslips.review.updateJobTitle');
    Route::patch('/payslips/{payslip}/region', [App\Http\Controllers\PayslipReviewController::class, 'updateRegion'])->name('payslips.review.updateRegion');
    Route::patch('/payslips/{payslip}/salary', [App\Http\Controllers\PayslipReviewController::class, 'updateSalary'])->name('payslips.review.updateSalary');
    Route::patch('/payslips/{payslip}/area-of-responsibility', [App\Http\Controllers\PayslipReviewController::class, 'updateAreaOfResponsibility'])->name('payslips.review.updateAreaOfResponsibility');
    Route::post('/payslips/{payslip}/document', [App\Http\Controllers\PayslipReviewController::class, 'uploadDocument'])->name('payslips.review.uploadDocument');
});

require __DIR__.'/settings.php';
