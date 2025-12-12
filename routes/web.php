<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', [App\Http\Controllers\LandingController::class, 'index'])->name('home');


// Guest-accessible report creation flow (no auth required)
Route::get('/get-started', [App\Http\Controllers\ReportsController::class, 'getStarted'])->name('get-started');

// Social Auth Routes
Route::get('auth/{provider}', [App\Http\Controllers\SocialAuthController::class, 'redirect'])->name('social.redirect');
Route::get('auth/{provider}/callback', [App\Http\Controllers\SocialAuthController::class, 'callback'])->name('social.callback');

Route::post('/reports/guest/job-details', [App\Http\Controllers\ReportsController::class, 'storeGuestJobDetails'])->name('reports.storeGuestJobDetails');
Route::patch('/reports/guest/{report}/job-details', [App\Http\Controllers\ReportsController::class, 'updateGuestJobDetails'])->name('reports.updateGuestJobDetails');
Route::patch('/reports/guest/competencies', [App\Http\Controllers\ReportsController::class, 'updateGuestCompetencies'])->name('reports.updateGuestCompetencies');
Route::post('/reports/guest/{report}/payslip', [App\Http\Controllers\ReportsController::class, 'storeGuestPayslip'])->name('reports.storeGuestPayslip');
Route::post('/reports/guest/{report}/analyze', App\Http\Controllers\ValidatePayslipController::class)->name('reports.analyzeGuestPayslip');
Route::delete('/reports/guest/{report}/payslip', [App\Http\Controllers\ReportsController::class, 'deletePayslip'])->name('reports.deleteGuestPayslip');

Route::middleware(['auth', 'verified'])->group(function () {    
    Route::get('dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    // Authenticated report routes
    Route::get('/reports', [App\Http\Controllers\ReportsController::class, 'index'])->name('reports.index');
    Route::get('/reports/create', [App\Http\Controllers\ReportsController::class, 'create'])->name('reports.create');
    Route::post('/reports/job-details', [App\Http\Controllers\ReportsController::class, 'storeJobDetails'])->name('reports.storeJobDetails');
    Route::post('/reports', [App\Http\Controllers\ReportsController::class, 'store'])->name('reports.store');
    Route::patch('/reports/{report}/job-details', [App\Http\Controllers\ReportsController::class, 'updateJobDetails'])->name('reports.updateJobDetails');
    Route::patch('/reports/{report}/competencies', [App\Http\Controllers\ReportsController::class, 'updateCompetencies'])->name('reports.updateCompetencies');
    Route::post('/reports/{report}/payslip', [App\Http\Controllers\ReportsController::class, 'storePayslip'])->name('reports.storePayslip');
    Route::post('/reports/{report}/analyze', App\Http\Controllers\ValidatePayslipController::class)->name('reports.analyzePayslip');
    Route::delete('/reports/{report}/payslip', [App\Http\Controllers\ReportsController::class, 'deletePayslip'])->name('reports.deletePayslip');
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
