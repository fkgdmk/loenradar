<?php

namespace App\Http\Controllers;

use App\Models\Payslip;
use Inertia\Inertia;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;

class LandingController extends Controller
{
    public function index()
    {
        $payslipCount = Cache::remember('payslip_count', 7200, function () {
            return Payslip::query()
                ->where(function ($query) {
                    $query->whereNotNull('verified_at')
                        ->orWhereNotNull('denied_at');
                })
                ->whereHas('media')
                ->count();
        });

        return Inertia::render('Landing', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'laravelVersion' => Application::VERSION,
            'phpVersion' => PHP_VERSION,
            'payslipCount' => $payslipCount,
        ]);
    }
}
