<?php

namespace App\Http\Controllers;

use App\Models\AreaOfResponsibility;
use App\Models\JobTitle;
use App\Models\Payslip;
use App\Models\Region;
use App\Models\Report;
use App\Models\ResponsibilityLevel;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    /**
     * Display a listing of the reports for the authenticated user.
     */
    public function index(Request $request): Response
    {
        $reports = Report::where('user_id', $request->user()->id)
            ->where('status', 'completed')
            ->with(['jobTitle', 'region', 'areaOfResponsibility'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($report) {
                return [
                    'id' => $report->id,
                    'job_title' => $report->jobTitle?->name,
                    'sub_job_title' => $report->sub_job_title,
                    'experience' => $report->experience,
                    'region' => $report->region?->name,
                    'area_of_responsibility' => $report->areaOfResponsibility?->name,
                    'lower_percentile' => $report->lower_percentile,
                    'median' => $report->median,
                    'upper_percentile' => $report->upper_percentile,
                    'conclusion' => $report->conclusion,
                    'created_at' => $report->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return Inertia::render('Reports', [
            'reports' => $reports,
        ]);
    }

    /**
     * Show the form for creating a new report.
     */
    public function create(Request $request): Response
    {
        $jobTitles = JobTitle::with('skills:id')
            ->orderBy('name')
            ->get()
            ->map(fn($jt) => [
                'id' => $jt->id,
                'name' => $jt->name,
                'skill_ids' => $jt->skills->pluck('id')->values()->all(),
            ]);

        // Sorter regioner med Storkøbenhavn først
        $regions = Region::all()->sortBy(function($region) {
            if ($region->name === 'Storkøbenhavn') {
                return 0;
            }
            return 1;
        })->values()->map(fn($r) => [
            'id' => $r->id,
            'name' => $r->name,
        ]);

        $areasOfResponsibility = AreaOfResponsibility::orderBy('name')->get()->map(fn($aor) => [
            'id' => $aor->id,
            'name' => $aor->name,
        ]);

        $responsibilityLevels = ResponsibilityLevel::orderBy('id')->get()->map(fn($rl) => [
            'id' => $rl->id,
            'name' => $rl->name,
        ]);

        $skills = Skill::orderBy('name')->get()->map(fn($s) => [
            'id' => $s->id,
            'name' => $s->name,
        ]);

        $leadershipRoles = [
            'Afdelingsleder',
            'Projektleder',
            'Manager',
            'Product Manager',
            'Teamleder / Team Lead',
        ];

        // Hent eksisterende report hvis report_id er i query
        $report = null;
        $reportId = $request->query('report_id');
        if ($reportId) {
            $report = Report::where('id', $reportId)
                ->where('user_id', $request->user()->id)
                ->where('status', 'draft')
                ->with(['uploadedPayslip', 'jobTitle', 'region', 'areaOfResponsibility'])
                ->first();
        }

        $reportData = null;
        if ($report) {
            $filters = $report->filters ?? [];
            $skillIds = $filters['skill_ids'] ?? [];
            $responsibilityLevelId = $report->uploadedPayslip?->responsibility_level_id;
            
            // Bestem step baseret på data
            $step = 1;
            if ($responsibilityLevelId) {
                $step = count($skillIds) >= 3 ? 3 : 2;
            }

            // Hent dokument fra payslip
            $document = null;
            if ($report->uploadedPayslip) {
                $media = $report->uploadedPayslip->getFirstMedia('documents');
                if ($media) {
                    $document = [
                        'name' => $media->file_name,
                        'size' => $media->size,
                        'mime_type' => $media->mime_type,
                        'preview_url' => $media->mime_type && str_starts_with($media->mime_type, 'image/')
                            ? $media->getFullUrl()
                            : null,
                        'download_url' => $media->getFullUrl(),
                    ];
                }
            }

            $reportData = [
                'id' => $report->id,
                'job_title_id' => $report->job_title_id,
                'area_of_responsibility_id' => $report->area_of_responsibility_id,
                'experience' => $report->experience,
                'gender' => $filters['gender'] ?? null,
                'region_id' => $report->region_id,
                'responsibility_level_id' => $responsibilityLevelId,
                'team_size' => $report->uploadedPayslip?->team_size,
                'skill_ids' => $skillIds,
                'step' => $step,
                'document' => $document,
            ];
        }

        return Inertia::render('Reports/Create', [
            'job_titles' => $jobTitles,
            'regions' => $regions,
            'areas_of_responsibility' => $areasOfResponsibility,
            'responsibility_levels' => $responsibilityLevels,
            'skills' => $skills,
            'leadership_roles' => $leadershipRoles,
            'report' => $reportData,
        ]);
    }

    /**
     * Store payslip after step 1.
     */
    public function storePayslip(Request $request)
    {
        $validated = $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'job_title_id' => 'required|exists:job_titles,id',
            'area_of_responsibility_id' => 'nullable|exists:area_of_responsibilities,id',
            'experience' => 'required|integer|min:0|max:50',
            'gender' => 'nullable|string|in:mand,kvinde,andet,Mand,Kvinde,Andet',
            'region_id' => 'required|exists:regions,id',
        ]);

        // Normaliser gender til at have caps på første bogstav, eller null hvis tom
        $gender = $validated['gender'] ?? null;
        if ($gender && trim($gender) !== '') {
            $gender = ucfirst(strtolower(trim($gender)));
        } else {
            $gender = null;
        }

        DB::beginTransaction();
        try {
            // Opret payslip (uden responsibility_level_id og team_size endnu)
            $payslip = Payslip::create([
                'job_title_id' => $validated['job_title_id'],
                'area_of_responsibility_id' => $validated['area_of_responsibility_id'] ?? null,
                'experience' => $validated['experience'],
                'gender' => $gender,
                'region_id' => $validated['region_id'],
                'uploader_id' => $request->user()->id,
                'uploaded_at' => now(),
                'source' => 'user_upload',
            ]);

            // Upload dokument
            $payslip->addMediaFromRequest('document')
                ->toMediaCollection('documents');

            // Opret draft report
            $report = Report::create([
                'user_id' => $request->user()->id,
                'uploaded_payslip_id' => $payslip->id,
                'job_title_id' => $validated['job_title_id'],
                'area_of_responsibility_id' => $validated['area_of_responsibility_id'] ?? null,
                'experience' => $validated['experience'],
                'region_id' => $validated['region_id'],
                'status' => 'draft',
                'filters' => [
                    'gender' => $gender,
                ],
            ]);

            // Knyt payslip til report
            $report->payslips()->attach($payslip->id);

            DB::commit();

            return Inertia::location(route('reports.create', ['report_id' => $report->id]));
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Der opstod en fejl ved oprettelse af lønsedlen.']);
        }
    }

    /**
     * Update step 1 data (job title, area, experience, gender, region).
     */
    public function updateStep1(Request $request, Report $report)
    {
        // Tjek at reporten tilhører brugeren og er draft
        if ($report->user_id !== $request->user()->id || $report->status !== 'draft') {
            abort(403);
        }

        $validated = $request->validate([
            'job_title_id' => 'required|exists:job_titles,id',
            'area_of_responsibility_id' => 'nullable|exists:area_of_responsibilities,id',
            'experience' => 'required|integer|min:0|max:50',
            'gender' => 'nullable|string|in:mand,kvinde,andet,Mand,Kvinde,Andet',
            'region_id' => 'required|exists:regions,id',
        ]);

        // Normaliser gender til at have caps på første bogstav, eller null hvis tom
        $gender = $validated['gender'] ?? null;
        if ($gender && trim($gender) !== '') {
            $gender = ucfirst(strtolower(trim($gender)));
        } else {
            $gender = null;
        }

        DB::beginTransaction();
        try {
            // Opdater payslip
            $payslip = $report->uploadedPayslip;
            if ($payslip) {
                $payslip->update([
                    'job_title_id' => $validated['job_title_id'],
                    'area_of_responsibility_id' => $validated['area_of_responsibility_id'] ?? null,
                    'experience' => $validated['experience'],
                    'gender' => $gender,
                    'region_id' => $validated['region_id'],
                ]);
            }

            // Opdater report
            $report->update([
                'job_title_id' => $validated['job_title_id'],
                'area_of_responsibility_id' => $validated['area_of_responsibility_id'] ?? null,
                'experience' => $validated['experience'],
                'region_id' => $validated['region_id'],
                'filters' => array_merge($report->filters ?? [], [
                    'gender' => $gender,
                ]),
            ]);

            DB::commit();

            return Inertia::location(route('reports.create', ['report_id' => $report->id]));
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Der opstod en fejl ved opdatering af data.']);
        }
    }

    /**
     * Update step 2 data (responsibility level, team size, skills).
     */
    public function updateStep2(Request $request, Report $report)
    {
        // Tjek at reporten tilhører brugeren og er draft
        if ($report->user_id !== $request->user()->id || $report->status !== 'draft') {
            abort(403);
        }

        $validated = $request->validate([
            'responsibility_level_id' => 'required|exists:responsibility_levels,id',
            'team_size' => 'nullable|integer|min:0|max:1000',
            'skill_ids' => 'nullable|array|max:5',
            'skill_ids.*' => 'exists:skills,id',
        ]);

        DB::beginTransaction();
        try {

            // Opdater payslip med step 2 data
            $payslip = $report->uploadedPayslip;
            if ($payslip) {
                $payslip->update([
                    'responsibility_level_id' => $validated['responsibility_level_id'],
                    'team_size' => $validated['team_size'] ?? null,
                ]);
            }

            // Opdater report med step 2 data (men ikke markér som completed)
            $report->update([
                'filters' => array_merge($report->filters ?? [], [
                    'responsibility_level_id' => $validated['responsibility_level_id'],
                    'team_size' => $validated['team_size'] ?? null,
                    'skill_ids' => $validated['skill_ids'],
                ]),
            ]);

            DB::commit();

            return Inertia::location(route('reports.create', ['report_id' => $report->id]));
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Der opstod en fejl ved opdatering af data.']);
        }
    }

    /**
     * Display the specified report.
     */
    public function show(Request $request, Report $report): Response
    {
        if ($report->user_id !== $request->user()->id) {
            abort(403);
        }

        $report->load([
            'jobTitle.prosaCategories.salaryStats',
            'region.prosaAreas',
            'areaOfResponsibility',
            'payslips' => function ($query) {
                $query->orderBy('experience', 'asc')->with('region');
            },
            'uploadedPayslip'
        ]);

        return Inertia::render('Reports/Show', [
            'report' => $report,
        ]);
    }

    /**
     * Store a newly created report.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'report_id' => 'required|exists:reports,id',
            // Step 2
            'responsibility_level_id' => 'required|exists:responsibility_levels,id',
            'team_size' => 'nullable|integer|min:0|max:1000',
            'skill_ids' => 'nullable|array|max:5',
            'skill_ids.*' => 'exists:skills,id',
        ]);

        DB::beginTransaction();
        try {
            // Find eksisterende report
            $report = Report::where('id', $validated['report_id'])
                ->where('user_id', $request->user()->id)
                ->where('status', 'draft')
                ->firstOrFail();

            // Opdater payslip med step 2 data
            $payslip = $report->uploadedPayslip;
            if ($payslip) {
                $payslip->update([
                    'responsibility_level_id' => $validated['responsibility_level_id'],
                    'team_size' => $validated['team_size'] ?? null,
                ]);
            }

            // Opdater report med step 2 data og markér som completed
            $report->update([
                'status' => 'completed',
                'filters' => array_merge($report->filters ?? [], [
                    'responsibility_level_id' => $validated['responsibility_level_id'],
                    'team_size' => $validated['team_size'] ?? null,
                    'skill_ids' => $validated['skill_ids'],
                ]),
            ]);

            // Beregn statistik
            $experience = $report->experience;
            $experienceRange = $this->getExperienceRange($experience);

            $matchingPayslips = Payslip::where('job_title_id', $report->job_title_id)
                ->whereBetween('experience', $experienceRange)
                ->where('id', '!=', $report->uploaded_payslip_id)
                ->whereNotNull('verified_at')
                ->whereNotNull('salary')
                ->get();

            $salaries = $matchingPayslips->pluck('salary')->sort()->values();
            $count = $salaries->count();

            $lower = 0;
            $median = 0;
            $upper = 0;

            if ($count > 0) {
                $lower = $this->calculatePercentile($salaries, 0.25);
                $median = $this->calculatePercentile($salaries, 0.50);
                $upper = $this->calculatePercentile($salaries, 0.75);

                // Attach payslips
                $report->payslips()->sync($matchingPayslips->pluck('id'));
            }

            $conclusion = "Baseret på {$count} datapunkter for din profil, er et realistisk og velbegrundet lønudspil i intervallet " . number_format($lower, 0, ',', '.') . " kr. til " . number_format($upper, 0, ',', '.') . " kr.";

            $report->update([
                'lower_percentile' => $lower,
                'median' => $median,
                'upper_percentile' => $upper,
                'conclusion' => $conclusion,
            ]);

            DB::commit();

            return redirect()->route('reports.show', $report->id)
                ->with('success', 'Rapport oprettet succesfuldt');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Der opstod en fejl ved oprettelse af rapporten: ' . $e->getMessage()]);
        }
    }

    private function getExperienceRange($years)
    {
        if ($years <= 5) {
            return [0, 5];
        }
        if ($years <= 11) {
            return [6, 11];
        }
        if ($years <= 20) {
            return [12, 20];
        }
        return [21, 100];
    }

    private function calculatePercentile($sortedData, $percentile)
    {
        $index = ($sortedData->count() - 1) * $percentile;
        $floor = floor($index);
        $ceil = ceil($index);

        if ($floor == $ceil) {
            return $sortedData[$index];
        }

        $d0 = $sortedData[$floor];
        $d1 = $sortedData[$ceil];

        return $d0 + ($d1 - $d0) * ($index - $floor);
    }
}
