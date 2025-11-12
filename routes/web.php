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
    Route::get('/payslips/review', [App\Http\Controllers\PayslipReviewController::class, 'index'])->name('payslips.review.index');
    Route::post('/payslips/{payslip}/approve', [App\Http\Controllers\PayslipReviewController::class, 'approve'])->name('payslips.review.approve');
    Route::post('/payslips/{payslip}/deny', [App\Http\Controllers\PayslipReviewController::class, 'deny'])->name('payslips.review.deny');
    Route::patch('/payslips/{payslip}/experience', [App\Http\Controllers\PayslipReviewController::class, 'updateExperience'])->name('payslips.review.updateExperience');
    Route::patch('/payslips/{payslip}/job-title', [App\Http\Controllers\PayslipReviewController::class, 'updateJobTitle'])->name('payslips.review.updateJobTitle');
    Route::patch('/payslips/{payslip}/region', [App\Http\Controllers\PayslipReviewController::class, 'updateRegion'])->name('payslips.review.updateRegion');
    Route::patch('/payslips/{payslip}/salary', [App\Http\Controllers\PayslipReviewController::class, 'updateSalary'])->name('payslips.review.updateSalary');
    Route::patch('/payslips/{payslip}/area-of-responsibility', [App\Http\Controllers\PayslipReviewController::class, 'updateAreaOfResponsibility'])->name('payslips.review.updateAreaOfResponsibility');
});

require __DIR__.'/settings.php';
