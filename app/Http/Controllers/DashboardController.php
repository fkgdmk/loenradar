<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Models\JobTitle;
use App\Models\Payslip;
use App\Models\Region;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Get experience range for a given number of years
     */
    private function getExperienceRange($years): ?array
    {
        if ($years === null) {
            return null;
        }
        
        if ($years <= 3) {
            return [0, 3];
        }
        if ($years <= 9) {
            return [4, 9];
        }
        return [10, null]; // 10+ år
    }

    /**
     * Format experience range as string
     */
    private function formatExperienceRange(?array $range): ?string
    {
        if ($range === null) {
            return null;
        }
        if ($range[1] === null) {
            return $range[0] . '+ år';
        }
        return $range[0] . '-' . $range[1] . ' år';
    }

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

        // Hent statistiske grupper med counts (sorteret efter antal payslips)
        $regionsWithCounts = DB::table('regions')
            ->join('payslips', 'regions.id', '=', 'payslips.region_id')
            ->whereNotNull('payslips.verified_at')
            ->whereNotNull('regions.statistical_group')
            ->select('regions.statistical_group as name', DB::raw('COUNT(DISTINCT payslips.id) as count'))
            ->groupBy('regions.statistical_group')
            ->havingRaw('COUNT(DISTINCT payslips.id) > 0')
            ->orderBy('count', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->name,
                    'count' => $item->count,
                ];
            });

        // Nye statistikker
        
        // 1. Jobtitler med 5+ verificerede lønsedler (total)
        $jobTitlesWith5PlusPayslips = JobTitle::whereHas('payslips', function ($query) {
                $query->whereNotNull('verified_at')
                    ->has('media')
                    ->whereNotNull('job_title_id')
                    ->whereNotNull('salary');
            })
            ->get()
            ->filter(function ($jobTitle) {
                $count = $jobTitle->payslips()
                    ->whereNotNull('verified_at')
                    ->has('media')
                    ->whereNotNull('job_title_id')
                    ->whereNotNull('salary')
                    ->count();
                return $count >= 5;
            })
            ->count();

        // 2. Jobtitler med 3+ jobopslag (total)
        $jobTitlesWith3PlusJobPostings = JobTitle::whereHas('jobPostings')
            ->get()
            ->filter(function ($jobTitle) {
                return $jobTitle->jobPostings()->count() >= 3;
            })
            ->count();

        // 3. Liste: Jobtitler med >= 5 lønseddler pr statistisk gruppe
        $jobTitlesWith5PlusPayslipsPerRegionList = DB::table('job_titles')
            ->join('payslips', 'job_titles.id', '=', 'payslips.job_title_id')
            ->join('regions', 'payslips.region_id', '=', 'regions.id')
            ->whereNotNull('payslips.verified_at')
            ->whereNotNull('payslips.region_id')
            ->whereNotNull('payslips.salary')
            ->whereNotNull('regions.statistical_group')
            ->select('job_titles.name', 'regions.statistical_group as region_name', DB::raw('COUNT(*) as count'))
            ->groupBy('job_titles.id', 'job_titles.name', 'regions.statistical_group')
            ->havingRaw('COUNT(*) >= 5')
            ->orderBy('count', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'jobTitle' => $item->name,
                    'region' => $item->region_name,
                    'count' => $item->count,
                ];
            });

        // 4. Liste: Jobtitler med >= 3 jobopslag pr statistisk gruppe
        $jobTitlesWith3PlusJobPostingsPerRegionList = DB::table('job_titles')
            ->join('job_postings', 'job_titles.id', '=', 'job_postings.job_title_id')
            ->join('regions', 'job_postings.region_id', '=', 'regions.id')
            ->whereNotNull('job_postings.region_id')
            ->whereNotNull('regions.statistical_group')
            ->select('job_titles.name', 'regions.statistical_group as region_name', DB::raw('COUNT(*) as count'))
            ->groupBy('job_titles.id', 'job_titles.name', 'regions.statistical_group')
            ->havingRaw('COUNT(*) >= 3')
            ->orderBy('count', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'jobTitle' => $item->name,
                    'region' => $item->region_name,
                    'count' => $item->count,
                ];
            });

        // Experience range statistikker
        
        // Hent experience ranges med counts (sorteret efter antal payslips)
        $experienceRangesWithCounts = DB::table('payslips')
            ->whereNotNull('verified_at')
            ->whereNotNull('experience')
            ->select(
                DB::raw('CASE 
                    WHEN experience <= 3 THEN "0-3 år"
                    WHEN experience <= 9 THEN "4-9 år"
                    ELSE "10+ år"
                END as name'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy(DB::raw('CASE 
                WHEN experience <= 3 THEN "0-3 år"
                WHEN experience <= 9 THEN "4-9 år"
                ELSE "10+ år"
            END'))
            ->orderBy('count', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->name,
                    'count' => $item->count,
                ];
            });

        // Liste: Jobtitler med >= 5 lønseddler pr experience range
        $jobTitlesWith5PlusPayslipsPerExperienceRangeList = DB::table('job_titles')
            ->join('payslips', 'job_titles.id', '=', 'payslips.job_title_id')
            ->whereNotNull('payslips.verified_at')
            ->whereNotNull('payslips.job_title_id')
            ->whereNotNull('payslips.salary')
            ->whereNotNull('payslips.experience')
            ->select(
                'job_titles.name',
                DB::raw('CASE 
                    WHEN payslips.experience <= 3 THEN "0-3 år"
                    WHEN payslips.experience <= 9 THEN "4-9 år"
                    ELSE "10+ år"
                END as experience_range'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy(
                'job_titles.id',
                'job_titles.name',
                DB::raw('CASE 
                    WHEN payslips.experience <= 3 THEN "0-3 år"
                    WHEN payslips.experience <= 9 THEN "4-9 år"
                    ELSE "10+ år"
                END')
            )
            ->havingRaw('COUNT(*) >= 5')
            ->orderBy('count', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'jobTitle' => $item->name,
                    'experienceRange' => $item->experience_range,
                    'count' => $item->count,
                ];
            });

        // Liste: Jobtitler med >= 3 jobopslag pr experience range
        $jobTitlesWith5PlusJobPostingsPerExperienceRangeList = DB::table('job_titles')
            ->join('job_postings', 'job_titles.id', '=', 'job_postings.job_title_id')
            ->whereNotNull('job_postings.job_title_id')
            ->whereNotNull('job_postings.minimum_experience')
            ->select(
                'job_titles.name',
                DB::raw('CASE 
                    WHEN job_postings.minimum_experience <= 3 THEN "0-3 år"
                    WHEN job_postings.minimum_experience <= 9 THEN "4-9 år"
                    ELSE "10+ år"
                END as experience_range'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy(
                'job_titles.id',
                'job_titles.name',
                DB::raw('CASE 
                    WHEN job_postings.minimum_experience <= 3 THEN "0-3 år"
                    WHEN job_postings.minimum_experience <= 9 THEN "4-9 år"
                    ELSE "10+ år"
                END')
            )
            ->havingRaw('COUNT(*) >= 3')
            ->orderBy('count', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'jobTitle' => $item->name,
                    'experienceRange' => $item->experience_range,
                    'count' => $item->count,
                ];
            });

        // NY: Komplet oversigt - Jobtitler med verificerede lønsedler pr statistisk gruppe OG erfaringsniveau
        $verifiedPayslipsPerJobTitleRegionExperience = DB::table('job_titles')
            ->join('payslips', 'job_titles.id', '=', 'payslips.job_title_id')
            ->join('regions', 'payslips.region_id', '=', 'regions.id')
            ->whereNotNull('payslips.verified_at')
            ->whereNotNull('payslips.region_id')
            ->whereNotNull('payslips.salary')
            ->whereNotNull('regions.statistical_group')
            ->whereNotNull('payslips.experience')
            ->select(
                'job_titles.name',
                'regions.statistical_group as region_name',
                DB::raw('CASE 
                    WHEN payslips.experience <= 3 THEN "0-3 år"
                    WHEN payslips.experience <= 9 THEN "4-9 år"
                    ELSE "10+ år"
                END as experience_range'),
                DB::raw('CASE 
                    WHEN payslips.experience <= 3 THEN 1
                    WHEN payslips.experience <= 9 THEN 2
                    ELSE 3
                END as experience_sort'),
                DB::raw('COUNT(DISTINCT payslips.id) as count')
            )
            ->groupBy(
                'job_titles.id',
                'job_titles.name',
                'regions.statistical_group',
                DB::raw('CASE 
                    WHEN payslips.experience <= 3 THEN "0-3 år"
                    WHEN payslips.experience <= 9 THEN "4-9 år"
                    ELSE "10+ år"
                END'),
                DB::raw('CASE 
                    WHEN payslips.experience <= 3 THEN 1
                    WHEN payslips.experience <= 9 THEN 2
                    ELSE 3
                END')
            )
            ->orderBy('count', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'jobTitle' => $item->name,
                    'region' => $item->region_name,
                    'experienceRange' => $item->experience_range,
                    'count' => $item->count,
                ];
            });

        return Inertia::render('Dashboard', [
            'statistics' => [
                'verifiedPayslips' => $verifiedPayslipsCount,
                'jobTitles' => $jobTitlesCount,
                'salaryDataPoints' => $salaryDataPointsCount,
                'jobTitlesWith5PlusPayslips' => $jobTitlesWith5PlusPayslips,
                'jobTitlesWith3PlusJobPostings' => $jobTitlesWith3PlusJobPostings,
            ],
            'jobTitles' => $jobTitlesWithCounts,
            'regions' => $regionsWithCounts,
            'jobTitlesWith5PlusPayslipsPerRegionList' => $jobTitlesWith5PlusPayslipsPerRegionList,
            'jobTitlesWith3PlusJobPostingsPerRegionList' => $jobTitlesWith3PlusJobPostingsPerRegionList,
            'experienceRanges' => $experienceRangesWithCounts,
            'jobTitlesWith5PlusPayslipsPerExperienceRangeList' => $jobTitlesWith5PlusPayslipsPerExperienceRangeList,
            'jobTitlesWith5PlusJobPostingsPerExperienceRangeList' => $jobTitlesWith5PlusJobPostingsPerExperienceRangeList,
            'verifiedPayslipsPerJobTitleRegionExperience' => $verifiedPayslipsPerJobTitleRegionExperience,
        ]);
    }
}

