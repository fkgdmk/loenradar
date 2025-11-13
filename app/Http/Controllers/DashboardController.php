<?php

namespace App\Http\Controllers;

use App\Models\JobTitle;
use App\Models\Payslip;
use App\Models\Region;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with statistics
     */
    public function index(): Response
    {
        // Antal verificerede payslips (med job_title_id, salary og verified_at)
        $verifiedPayslipsCount = Payslip::whereNotNull('verified_at')
            ->has('media')
            ->whereNotNull('job_title_id')
            ->whereNotNull('salary')
            ->count();

        // Antal unikke jobtitler der har payslips
        $jobTitlesCount = JobTitle::whereHas('payslips', function ($query) {
            $query->whereNotNull('verified_at');
        })->count();

        // Antal unikke regioner der har payslips
        $salaryDataPointsCount = Payslip::whereNotNull('verified_at')
            ->whereNotNull('job_title_id')
            ->whereNotNull('salary')
            ->count();

        // Hent jobtitler med counts (sorteret efter antal payslips)
        $jobTitlesWithCounts = JobTitle::whereHas('payslips', function ($query) {
                $query->whereNotNull('verified_at')
                    ->whereNotNull('job_title_id')
                    ->whereNotNull('salary');
            })
            ->get()
            ->map(function ($jobTitle) {
                // Tæl løndatapunkter (med verified_at, job_title_id og salary)
                $salaryDataPointsCount = $jobTitle->payslips()
                    ->whereNotNull('verified_at')
                    ->whereNotNull('job_title_id')
                    ->whereNotNull('salary')
                    ->count();

                // Tæl verificerede lønseddeler (med verified_at, media, job_title_id og salary)
                $verifiedPayslipsCount = $jobTitle->payslips()
                    ->whereNotNull('verified_at')
                    ->whereNotNull('job_title_id')
                    ->whereNotNull('salary')
                    ->has('media')
                    ->count();

                return [
                    'name' => $jobTitle->name,
                    'count' => $salaryDataPointsCount, // Behold eksisterende count for bagudkompatibilitet
                    'salaryDataPoints' => $salaryDataPointsCount,
                    'verifiedPayslips' => $verifiedPayslipsCount,
                ];
            })
            ->filter(function ($jobTitle) {
                return $jobTitle['salaryDataPoints'] > 0;
            })
            ->sortByDesc('salaryDataPoints')
            ->values();

        // Hent regioner med counts (sorteret efter antal payslips)
        $regionsWithCounts = Region::withCount('payslips')
            ->whereHas('payslips', function ($query) {
                $query->whereNotNull('verified_at');
            })
            ->having('payslips_count', '>', 0)
            ->orderBy('payslips_count', 'desc')
            ->get()
            ->map(function ($region) {
                return [
                    'name' => $region->name,
                    'count' => $region->payslips_count,
                ];
            });

        return Inertia::render('Dashboard', [
            'statistics' => [
                'verifiedPayslips' => $verifiedPayslipsCount,
                'jobTitles' => $jobTitlesCount,
                'salaryDataPoints' => $salaryDataPointsCount,
            ],
            'jobTitles' => $jobTitlesWithCounts,
            'regions' => $regionsWithCounts,
        ]);
    }
}

