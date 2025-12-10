<?php

namespace App\Http\Controllers;

use App\Enums\PayslipMatchType;
use App\Models\AreaOfResponsibility;
use App\Models\JobPosting;
use App\Models\JobTitle;
use App\Models\Payslip;
use App\Models\Region;
use App\Models\Report;
use App\Models\ResponsibilityLevel;
use App\Models\Skill;
use App\Services\FindMatchingJobPostings;
use App\Services\FindMatchingPayslips;
use App\Services\ReportConclusionGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;
use Inertia\Response;

class ReportsController extends Controller
{
    /**
     * Display a listing of the reports for the authenticated user.
     */
    public function index(Request $request): Response
    {
        $reports = Report::where('user_id', $request->user()->id)
            ->whereIn('status', ['completed', 'draft'])
            ->with(['jobTitle', 'region', 'areaOfResponsibility'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($report) {
                return [
                    'id' => $report->id,
                    'job_title' => $report->jobTitle?->name_en,
                    'sub_job_title' => $report->sub_job_title,
                    'experience' => $report->experience,
                    'region' => $report->region?->name,
                    'area_of_responsibility' => $report->areaOfResponsibility?->name,
                    'lower_percentile' => $report->lower_percentile,
                    'median' => $report->median,
                    'upper_percentile' => $report->upper_percentile,
                    'conclusion' => $report->conclusion,
                    'status' => $report->status,
                    'created_at' => $report->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return Inertia::render('Reports', [
            'reports' => $reports,
        ]);
    }

    /**
     * Show the form for creating a new report (authenticated users only).
     */
    public function create(Request $request): Response
    {
        $formData = $this->getReportFormData();

        $reportData = null;
        $reportId = $request->query('report_id');

        if ($reportId) {
            $guestToken = $request->session()->get('guest_report_token');
            
            // Find report - either by user_id (for existing reports) or guest_token (for guest reports being finalized)
            $query = Report::where('id', $reportId)
                ->where('status', 'draft')
                ->with(['uploadedPayslip', 'jobTitle', 'region', 'areaOfResponsibility']);
            
            if ($guestToken) {
                // Check for guest report first
                $query->where(function ($q) use ($request, $guestToken) {
                    $q->where('user_id', $request->user()->id)
                      ->orWhere('guest_token', $guestToken);
                });
            } else {
                // Only check user_id if no guest token
                $query->where('user_id', $request->user()->id);
            }
            
            $report = $query->first();

            if ($report) {
                $reportData = $this->formatReportData($report);
            }
        }

        return Inertia::render('Reports/Create', [
            ...$formData,
            'report' => $reportData,
        ]);
    }

    /**
     * Show the get started page for guests (and authenticated users).
     */
    public function getStarted(Request $request): Response
    {
        $formData = $this->getReportFormData();

        $reportData = null;
        $reportId = $request->query('report_id');

        if ($reportId) {
            // Try to find report by ID and guest_token from session
            $guestToken = $request->session()->get('guest_report_token');
            
            $query = Report::where('id', $reportId)
                ->where('status', 'draft')
                ->with(['uploadedPayslip', 'jobTitle', 'region', 'areaOfResponsibility']);
            
            // If user is authenticated, check user_id
            if ($request->user()) {
                $query->where(function ($q) use ($request, $guestToken) {
                    $q->where('user_id', $request->user()->id)
                      ->orWhere('guest_token', $guestToken);
                });
            } else {
                // For guests, verify with guest_token
                $query->where('guest_token', $guestToken);
            }
            
            $report = $query->first();

            if ($report) {
                $reportData = $this->formatReportData($report);
            }
        }

        return Inertia::render('GetStarted', [
            ...$formData,
            'report' => $reportData,
        ]);
    }

    /**
     * Get common form data for report creation.
     */
    private function getReportFormData(): array
    {
        $jobTitles = JobTitle::with('skills:id')
            ->orderBy('name_en')
            ->get()
            ->map(fn($jt) => [
                'id' => $jt->id,
                'name' => $jt->name,
                'name_en' => $jt->name_en,
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

        return [
            'job_titles' => $jobTitles,
            'regions' => $regions,
            'areas_of_responsibility' => $areasOfResponsibility,
            'responsibility_levels' => $responsibilityLevels,
            'skills' => $skills,
            'leadership_roles' => $leadershipRoles,
        ];
    }

    /**
     * Format report data for frontend.
     */
    private function formatReportData(Report $report): array
    {
        $filters = $report->filters ?? [];
        $skillIds = $filters['skill_ids'] ?? [];
        
        // Check both filters and uploadedPayslip for responsibility_level_id
        // (filters is used before payslip is uploaded, uploadedPayslip after)
        $responsibilityLevelId = $filters['responsibility_level_id'] 
            ?? $report->uploadedPayslip?->responsibility_level_id 
            ?? null;
        
        $teamSize = $filters['team_size'] 
            ?? $report->uploadedPayslip?->team_size 
            ?? null;
        
        // Bestem step baseret på data
        $step = 1;
        if ($report->job_title_id && $report->region_id && $report->experience !== null) {
            $step = 2;
            if ($responsibilityLevelId) {
                $step = 3;
            }
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

        return [
            'id' => $report->id,
            'job_title_id' => $report->job_title_id,
            'area_of_responsibility_id' => $report->area_of_responsibility_id,
            'experience' => $report->experience,
            'gender' => $filters['gender'] ?? null,
            'region_id' => $report->region_id,
            'responsibility_level_id' => $responsibilityLevelId,
            'team_size' => $teamSize,
            'skill_ids' => $skillIds,
            'step' => $step,
            'document' => $document,
            'payslip_match' => $report->payslip_match,
            'match_metadata' => $report->match_metadata,
        ];
    }

    /**
     * Store job details (step 1) - creates a new draft report.
     */
    public function storeJobDetails(Request $request)
    {
        $validated = $request->validate([
            'job_title_id' => 'required|exists:job_titles,id',
            'area_of_responsibility_id' => 'nullable|exists:area_of_responsibilities,id',
            'experience' => 'required|integer|min:0|max:50',
            'gender' => 'nullable|string|in:mand,kvinde,andet,Mand,Kvinde,Andet',
            'region_id' => 'required|exists:regions,id',
        ], [
            'job_title_id.required' => 'Vælg venligst en jobtitel',
            'job_title_id.exists' => 'Den valgte jobtitel er ugyldig',
            'area_of_responsibility_id.exists' => 'Det valgte område er ugyldigt',
            'experience.required' => 'Indtast venligst dit erfaringsniveau',
            'experience.integer' => 'Erfaring skal være et heltal',
            'experience.min' => 'Erfaring skal være mindst 0 år',
            'experience.max' => 'Erfaring må maksimalt være 50 år',
            'region_id.required' => 'Vælg venligst en region',
            'region_id.exists' => 'Den valgte region er ugyldig',
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
            // Opret draft report (uden payslip endnu)
            $report = Report::create([
                'user_id' => $request->user()->id,
                'job_title_id' => $validated['job_title_id'],
                'area_of_responsibility_id' => $validated['area_of_responsibility_id'] ?? null,
                'experience' => $validated['experience'],
                'region_id' => $validated['region_id'],
                'status' => 'draft',
                'filters' => [
                    'gender' => $gender,
                ],
            ]);

            DB::commit();

            // Calculate and save match status
            $this->updateReportMatchStatus($report);

            return redirect()->route('reports.create', ['report_id' => $report->id]);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Der opstod en fejl ved oprettelse af rapporten.']);
        }
    }

    /**
     * Store payslip for a report (step 3).
     */
    public function storePayslip(Request $request, Report $report)
    {
        // Verify ownership
        if ($report->user_id !== $request->user()->id || $report->status !== 'draft') {
            return back()->withErrors(['error' => 'Adgang nægtet.']);
        }

        $validated = $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ], [
            'document.required' => 'Upload venligst din lønseddel',
            'document.file' => 'Dokumentet skal være en fil',
            'document.mimes' => 'Dokumentet skal være en PDF eller et billede (JPG, PNG, WEBP)',
            'document.max' => 'Dokumentet må maksimalt være 10MB',
        ]);

        DB::beginTransaction();
        try {
            // Get gender from filters
            $gender = $report->filters['gender'] ?? null;

            // Opret payslip
            $payslip = Payslip::create([
                'job_title_id' => $report->job_title_id,
                'area_of_responsibility_id' => $report->area_of_responsibility_id,
                'experience' => $report->experience,
                'gender' => $gender,
                'region_id' => $report->region_id,
                'responsibility_level_id' => $report->filters['responsibility_level_id'] ?? null,
                'team_size' => $report->filters['team_size'] ?? null,
                'uploader_id' => $request->user()->id,
                'uploaded_at' => now(),
                'source' => 'user_upload',
            ]);

            // Upload dokument
            $payslip->addMediaFromRequest('document')
                ->toMediaCollection('documents');

            // Opdater report med payslip
            $report->update([
                'uploaded_payslip_id' => $payslip->id,
            ]);

            // Knyt payslip til report
            $report->payslips()->attach($payslip->id);

            DB::commit();

            // Tjek om der er nok payslips EFTER gemning
            $findMatchingPayslips = new FindMatchingPayslips();
            $result = $findMatchingPayslips->find($report);
            $matchingPayslips = $result['payslips'];
            $description = $result['description'];
            $matchType = $result['match_type'];

            // Check if we should override INSUFFICIENT_DATA
            if ($matchType === PayslipMatchType::INSUFFICIENT_DATA) {
                 $findMatchingJobPostings = new FindMatchingJobPostings();
                 $jobCount = $findMatchingJobPostings->findAndAttach($report);
                 
                 if ($jobCount >= 3) {
                     $matchType = PayslipMatchType::LIMITED_DATA;
                 }
            }

            // Update report with match status and metadata (important for redirect logic after login)
            $report->update([
                'payslip_match' => $matchType,
                'match_metadata' => $result['metadata'],
            ]);

            // Hvis der ikke er nok payslips (under 5) og vi stadig er på INSUFFICIENT_DATA
            if ($matchType === PayslipMatchType::INSUFFICIENT_DATA) {
                return redirect()->route('reports.index')
                    ->with('success', 'insufficient_data');
            }

            return back();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Der opstod en fejl ved upload af lønsedlen.']);
        }
    }

    /**
     * Store job details for guests (step 1) - creates a new draft report.
     */
    public function storeGuestJobDetails(Request $request)
    {
        $validated = $request->validate([
            'job_title_id' => 'required|exists:job_titles,id',
            'area_of_responsibility_id' => 'nullable|exists:area_of_responsibilities,id',
            'experience' => 'required|integer|min:0|max:50',
            'gender' => 'nullable|string|in:mand,kvinde,andet,Mand,Kvinde,Andet',
            'region_id' => 'required|exists:regions,id',
        ], [
            'job_title_id.required' => 'Vælg venligst en jobtitel',
            'job_title_id.exists' => 'Den valgte jobtitel er ugyldig',
            'area_of_responsibility_id.exists' => 'Det valgte område er ugyldigt',
            'experience.required' => 'Indtast venligst dit erfaringsniveau',
            'experience.integer' => 'Erfaring skal være et heltal',
            'experience.min' => 'Erfaring skal være mindst 0 år',
            'experience.max' => 'Erfaring må maksimalt være 50 år',
            'region_id.required' => 'Vælg venligst en region',
            'region_id.exists' => 'Den valgte region er ugyldig',
        ]);

        // Normaliser gender
        $gender = $validated['gender'] ?? null;
        if ($gender && trim($gender) !== '') {
            $gender = ucfirst(strtolower(trim($gender)));
        } else {
            $gender = null;
        }

        DB::beginTransaction();
        try {
            // Generate unique guest token
            $guestToken = Str::uuid()->toString();

            // Create draft report (uden payslip endnu)
            $report = Report::create([
                'user_id' => $request->user()?->id, // null for guests
                'guest_token' => $guestToken,
                'job_title_id' => $validated['job_title_id'],
                'area_of_responsibility_id' => $validated['area_of_responsibility_id'] ?? null,
                'experience' => $validated['experience'],
                'region_id' => $validated['region_id'],
                'status' => 'draft',
                'filters' => [
                    'gender' => $gender,
                ],
            ]);

            // Store guest token in session for verification
            $request->session()->put('guest_report_token', $guestToken);

            DB::commit();

            // Calculate and save match status
            $this->updateReportMatchStatus($report);

            return redirect()->route('get-started', ['report_id' => $report->id]);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Der opstod en fejl ved oprettelse af rapporten.']);
        }
    }

    /**
     * Store payslip for guests (step 3).
     */
    public function storeGuestPayslip(Request $request, Report $report)
    {
        $guestToken = $request->session()->get('guest_report_token');
        
        // Verify access to the report
        if ($report->status !== 'draft') {
            return back()->withErrors(['error' => 'Rapport kan ikke opdateres.']);
        }
        
        if ($request->user()) {
            if ($report->user_id !== $request->user()->id && $report->guest_token !== $guestToken) {
                return back()->withErrors(['error' => 'Adgang nægtet.']);
            }
        } else {
            if ($report->guest_token !== $guestToken) {
                return back()->withErrors(['error' => 'Adgang nægtet.']);
            }
        }

        $validated = $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ], [
            'document.required' => 'Upload venligst din lønseddel',
            'document.file' => 'Dokumentet skal være en fil',
            'document.mimes' => 'Dokumentet skal være en PDF eller et billede (JPG, PNG, WEBP)',
            'document.max' => 'Dokumentet må maksimalt være 10MB',
        ]);

        // Get gender from filters
        $gender = $report->filters['gender'] ?? null;

        DB::beginTransaction();
        try {
            // Create payslip
            $payslip = Payslip::create([
                'job_title_id' => $report->job_title_id,
                'area_of_responsibility_id' => $report->area_of_responsibility_id,
                'experience' => $report->experience,
                'gender' => $gender,
                'region_id' => $report->region_id,
                'responsibility_level_id' => $report->filters['responsibility_level_id'] ?? null,
                'team_size' => $report->filters['team_size'] ?? null,
                'uploader_id' => $request->user()?->id, // null for guests
                'uploaded_at' => now(),
                'source' => 'guest_upload',
            ]);

            // Upload document to payslip
            $payslip->addMediaFromRequest('document')
                ->toMediaCollection('documents');

            // Update report with payslip
            $report->update([
                'uploaded_payslip_id' => $payslip->id,
            ]);

            // Attach payslip to report
            $report->payslips()->attach($payslip->id);

            DB::commit();

            // Check for matching payslips AFTER saving the report
            $findMatchingPayslips = new FindMatchingPayslips();
            $result = $findMatchingPayslips->find($report);
            $matchingPayslips = $result['payslips'];
            $description = $result['description'];
            $matchType = $result['match_type'];

            // Check if we should override INSUFFICIENT_DATA
            if ($matchType === PayslipMatchType::INSUFFICIENT_DATA) {
                 $findMatchingJobPostings = new FindMatchingJobPostings();
                 $jobCount = $findMatchingJobPostings->findAndAttach($report);
                 
                 if ($jobCount >= 3) {
                     $matchType = PayslipMatchType::LIMITED_DATA;
                 }
            }

            // Update report with match status and metadata (important for redirect logic after login)
            $report->update([
                'payslip_match' => $matchType,
                'match_metadata' => $result['metadata'],
            ]);

            // If not enough payslips, return error with report_id
            if ($matchType === PayslipMatchType::INSUFFICIENT_DATA && $description) {
                return back()->withErrors([
                    'payslip_warning' => $description,
                    'report_id' => $report->id,
                ]);
            }

            return back();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Der opstod en fejl ved upload af lønsedlen.']);
        }
    }

    /**
     * Update job details for guests (step 1) when report already exists.
     */
    public function updateGuestJobDetails(Request $request, Report $report)
    {
        $guestToken = $request->session()->get('guest_report_token');
        
        // Verify access to the report
        if ($report->status !== 'draft') {
            return back()->withErrors(['error' => 'Rapport kan ikke opdateres.']);
        }
        
        if ($request->user()) {
            if ($report->user_id !== $request->user()->id && $report->guest_token !== $guestToken) {
                return back()->withErrors(['error' => 'Adgang nægtet.']);
            }
        } else {
            if ($report->guest_token !== $guestToken) {
                return back()->withErrors(['error' => 'Adgang nægtet.']);
            }
        }

        $validated = $request->validate([
            'job_title_id' => 'required|exists:job_titles,id',
            'area_of_responsibility_id' => 'nullable|exists:area_of_responsibilities,id',
            'experience' => 'required|integer|min:0|max:50',
            'gender' => 'nullable|string|in:mand,kvinde,andet,Mand,Kvinde,Andet',
            'region_id' => 'required|exists:regions,id',
        ], [
            'job_title_id.required' => 'Vælg venligst en jobtitel',
            'job_title_id.exists' => 'Den valgte jobtitel er ugyldig',
            'area_of_responsibility_id.exists' => 'Det valgte område er ugyldigt',
            'experience.required' => 'Indtast venligst dit erfaringsniveau',
            'experience.integer' => 'Erfaring skal være et heltal',
            'experience.min' => 'Erfaring skal være mindst 0 år',
            'experience.max' => 'Erfaring må maksimalt være 50 år',
            'region_id.required' => 'Vælg venligst en region',
            'region_id.exists' => 'Den valgte region er ugyldig',
        ]);

        // Normaliser gender
        $gender = $validated['gender'] ?? null;
        if ($gender && trim($gender) !== '') {
            $gender = ucfirst(strtolower(trim($gender)));
        } else {
            $gender = null;
        }

        DB::beginTransaction();
        try {
            // Opdater payslip hvis det findes
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

            // Calculate and save match status
            $this->updateReportMatchStatus($report);

            return redirect()->route('get-started', ['report_id' => $report->id]);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Der opstod en fejl ved opdatering af data.']);
        }
    }

    /**
     * Update competencies for guests (step 2) - responsibility level, team size, skills.
     */
    public function updateGuestCompetencies(Request $request)
    {
        $validated = $request->validate([
            'report_id' => 'required|exists:reports,id',
            'responsibility_level_id' => 'required|exists:responsibility_levels,id',
            'team_size' => 'nullable|integer|min:0|max:1000',
            'skill_ids' => 'nullable|array|max:10',
            'skill_ids.*' => 'exists:skills,id',
        ]);

        $guestToken = $request->session()->get('guest_report_token');
        
        // Find the draft report
        $query = Report::where('id', $validated['report_id'])
            ->where('status', 'draft');
        
        if ($request->user()) {
            $query->where(function ($q) use ($request, $guestToken) {
                $q->where('user_id', $request->user()->id)
                  ->orWhere('guest_token', $guestToken);
            });
        } else {
            $query->where('guest_token', $guestToken);
        }
        
        $report = $query->first();

        if (!$report) {
            return back()->withErrors(['error' => 'Rapport ikke fundet. Start forfra venligst.']);
        }

        DB::beginTransaction();
        try {
            // Update payslip with competencies data hvis det findes
            $payslip = $report->uploadedPayslip;
            if ($payslip) {
                $payslip->update([
                    'responsibility_level_id' => $validated['responsibility_level_id'],
                    'team_size' => $validated['team_size'] ?? null,
                ]);
            }

            // Update report filters with competencies data
            $report->update([
                'filters' => array_merge($report->filters ?? [], [
                    'responsibility_level_id' => $validated['responsibility_level_id'],
                    'team_size' => $validated['team_size'] ?? null,
                    'skill_ids' => $validated['skill_ids'] ?? [],
                ]),
            ]);

            DB::commit();

            return redirect()->route('get-started', ['report_id' => $report->id]);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Der opstod en fejl ved opdatering af data.']);
        }
    }

    /**
     * Update competencies (step 2) - responsibility level, team size, skills.
     */
    public function updateCompetencies(Request $request, Report $report)
    {
        // Verify ownership
        if ($report->user_id !== $request->user()->id || $report->status !== 'draft') {
            return back()->withErrors(['error' => 'Adgang nægtet.']);
        }

        $validated = $request->validate([
            'responsibility_level_id' => 'required|exists:responsibility_levels,id',
            'team_size' => 'nullable|integer|min:0|max:1000',
            'skill_ids' => 'nullable|array|max:10',
            'skill_ids.*' => 'exists:skills,id',
        ]);

        DB::beginTransaction();
        try {
            // Opdater payslip med competencies data hvis det findes
            $payslip = $report->uploadedPayslip;
            if ($payslip) {
                $payslip->update([
                    'responsibility_level_id' => $validated['responsibility_level_id'],
                    'team_size' => $validated['team_size'] ?? null,
                ]);
            }

            // Opdater report med competencies data
            $report->update([
                'filters' => array_merge($report->filters ?? [], [
                    'responsibility_level_id' => $validated['responsibility_level_id'],
                    'team_size' => $validated['team_size'] ?? null,
                    'skill_ids' => $validated['skill_ids'] ?? [],
                ]),
            ]);

            DB::commit();

            return redirect()->route('reports.create', ['report_id' => $report->id]);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Der opstod en fejl ved opdatering af data.']);
        }
    }

    /**
     * Display the specified report.
     */
    public function show(Request $request, Report $report)
    {
        if ($report->status !== 'completed') {
            return Redirect::route('reports.index');
        }

        $guestToken = $request->session()->get('guest_report_token');
        if ($report->user_id !== $request->user()->id && $report->guest_token !== $guestToken) {
            return Redirect::route('reports.index');
        }

        $report->load([
            'jobTitle.prosaCategories.salaryStats',
            'region.prosaAreas',
            'areaOfResponsibility',
            'payslips' => function ($query) {
                $query->orderBy('experience', 'asc')->with('region');
            },
            'uploadedPayslip',
            'jobPostings' => function ($query) {
                $query->orderBy('report_job_posting.match_score', 'desc')
                    ->with(['region', 'skills:id,name']);
            }
        ]);

        return Inertia::render('Reports/Show', [
            'report' => $report,
            'responsibilityLevel' => $report->responsibilityLevel(),
        ]);
    }

    /**
     * Store a newly created report.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'report_id' => 'required|exists:reports,id',
            'responsibility_level_id' => 'required|exists:responsibility_levels,id',
            'team_size' => 'nullable|integer|min:0|max:1000',
            'skill_ids' => 'nullable|array|max:10',
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

            // Opdater report med step 2 data
            $report->update([
                'filters' => array_merge($report->filters ?? [], [
                    'responsibility_level_id' => $validated['responsibility_level_id'],
                    'team_size' => $validated['team_size'] ?? null,
                    'skill_ids' => $validated['skill_ids'],
                ]),
            ]);

            // Beregn statistik
            $findMatchingPayslips = new FindMatchingPayslips();
            $result = $findMatchingPayslips->find($report);
            $matchingPayslips = $result['payslips'];
            $description = $result['description'];
            $matchType = $result['match_type'];
            $metadata = $result['metadata'];

            // Find and attach matching job postings
            $findMatchingJobPostings = new FindMatchingJobPostings();
            $jobPostingCount = $findMatchingJobPostings->findAndAttach($report);

            // Check if we should override INSUFFICIENT_DATA
            if ($matchType === PayslipMatchType::INSUFFICIENT_DATA && $jobPostingCount >= 3) {
                 $matchType = PayslipMatchType::LIMITED_DATA;
            }

            $salaries = $matchingPayslips->pluck('total_salary_dkk')->sort()->values();
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

            // Determine status
            $status = 'completed';
            if ($matchType === PayslipMatchType::INSUFFICIENT_DATA) {
                $status = 'draft';
            }

            $report->update([
                'status' => $status,
                'lower_percentile' => $lower,
                'median' => $median,
                'upper_percentile' => $upper,
                'description' => $description,
                'payslip_match' => $matchType->value,
                'match_metadata' => $metadata,
            ]);

            // Generate conclusion using dedicated service
            $conclusionGenerator = new ReportConclusionGenerator();
            $conclusionGenerator->generate($report);

            // Count active job postings from thehub.io for this job title
            $activeJobPostingsCount = JobPosting::where('job_title_id', $report->job_title_id)
                ->where('source', 'thehub.io')
                ->count();
            
            $report->update([
                'active_job_postings_the_hub' => $activeJobPostingsCount,
            ]);

            DB::commit();

            if ($status === 'draft') {
                return redirect()->route('reports.index')
                    ->with('success', 'insufficient_data');
            }

            return redirect()->route('reports.show', $report->id)
                ->with('success', 'Rapport oprettet succesfuldt');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Der opstod en fejl ved oprettelse af rapporten: ' . $e->getMessage()]);
        }
    }


    /**
     * Delete payslip document for a report.
     */
    public function deletePayslip(Request $request, Report $report)
    {
        $guestToken = $request->session()->get('guest_report_token');
        
        // Verify access to the report
        if ($report->status !== 'draft') {
            return back()->withErrors(['error' => 'Rapport kan ikke opdateres.']);
        }
        
        if ($request->user()) {
            if ($report->user_id !== $request->user()->id && $report->guest_token !== $guestToken) {
                return back()->withErrors(['error' => 'Adgang nægtet.']);
            }
        } else {
            if ($report->guest_token !== $guestToken) {
                return back()->withErrors(['error' => 'Adgang nægtet.']);
            }
        }

        DB::beginTransaction();
        try {
            $payslip = $report->uploadedPayslip;
            
            if ($payslip) {
                // Delete the document
                $payslip->clearMediaCollection('documents');
                
                $report->update([
                    'uploaded_payslip_id' => null,
                ]);
                $payslip->delete();
            }

            DB::commit();

            // Redirect to the same page with report_id to maintain state
            if ($request->user()) {
                return redirect()->route('reports.create', ['report_id' => $report->id])
                    ->with('success', 'Lønseddel slettet');
            } else {
                return redirect()->route('get-started', ['report_id' => $report->id])
                    ->with('success', 'Lønseddel slettet');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Der opstod en fejl ved sletning af lønsedlen.']);
        }
    }

    /**
     * Helper to update match status and metadata based on current report data.
     */
    private function updateReportMatchStatus(Report $report)
    {
        $findMatchingPayslips = new FindMatchingPayslips();
        $result = $findMatchingPayslips->find($report);
        $matchType = $result['match_type'];
        
        // Check if we should override INSUFFICIENT_DATA with job postings
        if ($matchType === PayslipMatchType::INSUFFICIENT_DATA) {
            $findMatchingJobPostings = new FindMatchingJobPostings();
            $jobPostingCount = $findMatchingJobPostings->findAndAttach($report);
            
            if ($jobPostingCount >= 3) {
                // If we have enough job postings, we consider it "limited data" instead of insufficient
                $matchType = PayslipMatchType::LIMITED_DATA;
            }
        }
        
        $report->update([
            'payslip_match' => $matchType,
            'match_metadata' => $result['metadata'],
        ]);
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

        return round($d0 + ($d1 - $d0) * ($index - $floor), 0);
    }
}
