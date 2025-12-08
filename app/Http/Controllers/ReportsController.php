<?php

namespace App\Http\Controllers;

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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
                    'job_title' => $report->jobTitle?->name_en,
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
        $responsibilityLevelId = $report->uploadedPayslip?->responsibility_level_id;
        
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
            'team_size' => $report->uploadedPayslip?->team_size,
            'skill_ids' => $skillIds,
            'step' => $step,
            'document' => $document,
        ];
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
        ], [
            'document.required' => 'Upload venligst din lønseddel',
            'document.file' => 'Dokumentet skal være en fil',
            'document.mimes' => 'Dokumentet skal være en PDF eller et billede (JPG, PNG, WEBP)',
            'document.max' => 'Dokumentet må maksimalt være 10MB',
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

            // Tjek om der er nok payslips EFTER gemning
            $findMatchingPayslips = new FindMatchingPayslips();
            $result = $findMatchingPayslips->find($report);
            $matchingPayslips = $result['payslips'];
            $description = $result['description'];

            // Hvis der ikke er nok payslips (under 5), returner fejl med report_id
            if ($matchingPayslips->count() < 5 && $description) {
                return back()->withErrors([
                    'payslip_warning' => $description,
                    'report_id' => $report->id,
                ]);
            }

            return redirect()->route('reports.create', ['report_id' => $report->id]);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Der opstod en fejl ved oprettelse af lønsedlen.']);
        }
    }

    /**
     * Store contact email for a report (when not enough payslips are available).
     */
    public function storeContactEmail(Request $request)
    {
        $validated = $request->validate([
            'report_id' => 'required|exists:reports,id',
            'contact_email' => 'required|email|max:255',
        ], [
            'contact_email.required' => 'Indtast venligst en email',
            'contact_email.email' => 'Indtast venligst en gyldig email',
        ]);

        $guestToken = $request->session()->get('guest_report_token');
        
        // Find the report - allow both guest token and authenticated user access
        $query = Report::where('id', $validated['report_id']);
        
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
            return back()->withErrors(['error' => 'Rapport ikke fundet.']);
        }

        $report->update([
            'contact_email' => $validated['contact_email'],
        ]);

        return back();
    }

    /**
     * Store payslip data for guests (save to database as draft).
     */
    public function storeGuestPayslip(Request $request)
    {
        $validated = $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'job_title_id' => 'required|exists:job_titles,id',
            'area_of_responsibility_id' => 'nullable|exists:area_of_responsibilities,id',
            'experience' => 'required|integer|min:0|max:50',
            'gender' => 'nullable|string|in:mand,kvinde,andet,Mand,Kvinde,Andet',
            'region_id' => 'required|exists:regions,id',
        ], [
            'document.required' => 'Upload venligst din lønseddel',
            'document.file' => 'Dokumentet skal være en fil',
            'document.mimes' => 'Dokumentet skal være en PDF eller et billede (JPG, PNG, WEBP)',
            'document.max' => 'Dokumentet må maksimalt være 10MB',
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

            // Create payslip without uploader_id (guest)
            $payslip = Payslip::create([
                'job_title_id' => $validated['job_title_id'],
                'area_of_responsibility_id' => $validated['area_of_responsibility_id'] ?? null,
                'experience' => $validated['experience'],
                'gender' => $gender,
                'region_id' => $validated['region_id'],
                'uploader_id' => $request->user()?->id, // null for guests
                'uploaded_at' => now(),
                'source' => 'guest_upload',
            ]);

            // Upload document to payslip
            $payslip->addMediaFromRequest('document')
                ->toMediaCollection('documents');

            // Create draft report
            $report = Report::create([
                'user_id' => $request->user()?->id, // null for guests
                'guest_token' => $guestToken,
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

            // Attach payslip to report
            $report->payslips()->attach($payslip->id);

            // Store guest token in session for verification
            $request->session()->put('guest_report_token', $guestToken);

            DB::commit();

            // Check for matching payslips AFTER saving the report
            $findMatchingPayslips = new FindMatchingPayslips();
            $result = $findMatchingPayslips->find($report);
            $matchingPayslips = $result['payslips'];
            $description = $result['description'];

            // If not enough payslips, return error with report_id
            if ($matchingPayslips->count() < 5 && $description) {
                return back()->withErrors([
                    'payslip_warning' => $description,
                    'report_id' => $report->id,
                ]);
            }

            return redirect()->route('get-started', ['report_id' => $report->id]);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Der opstod en fejl ved oprettelse af lønsedlen.']);
        }
    }

    /**
     * Update step 1 data for guests (when report already exists).
     */
    public function updateGuestStep1(Request $request, Report $report)
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

            // Tjek om der er nok payslips
            $findMatchingPayslips = new FindMatchingPayslips();
            $result = $findMatchingPayslips->find($report);
            $matchingPayslips = $result['payslips'];
            $description = $result['description'];

            // Hvis der ikke er nok payslips (under 5), returner fejl med report_id
            if ($matchingPayslips->count() < 5 && $description) {
                return back()->withErrors([
                    'payslip_warning' => $description,
                    'report_id' => $report->id,
                ]);
            }

            return redirect()->route('get-started', ['report_id' => $report->id]);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Der opstod en fejl ved opdatering af data.']);
        }
    }

    /**
     * Update step 2 data for guests (save to database).
     */
    public function updateGuestStep2(Request $request)
    {
        $validated = $request->validate([
            'report_id' => 'required|exists:reports,id',
            'responsibility_level_id' => 'required|exists:responsibility_levels,id',
            'team_size' => 'nullable|integer|min:0|max:1000',
            'skill_ids' => 'nullable|array|max:5',
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
            // Update payslip with step 2 data
            $payslip = $report->uploadedPayslip;
            if ($payslip) {
                $payslip->update([
                    'responsibility_level_id' => $validated['responsibility_level_id'],
                    'team_size' => $validated['team_size'] ?? null,
                ]);
            }

            // Update report filters with step 2 data
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
     * Finalize guest report after authentication.
     */
    public function finalizeGuestReport(Request $request)
    {
        $validated = $request->validate([
            'report_id' => 'required|exists:reports,id',
        ]);

        $guestToken = $request->session()->get('guest_report_token');
        
        // Find the draft report by guest_token
        $report = Report::where('id', $validated['report_id'])
            ->where('guest_token', $guestToken)
            ->where('status', 'draft')
            ->first();

        if (!$report) {
            return redirect()->route('get-started')->withErrors(['error' => 'Rapport ikke fundet.']);
        }

        // Check if step 2 is completed (responsibility_level_id in filters)
        $filters = $report->filters ?? [];
        if (!isset($filters['responsibility_level_id'])) {
            return redirect()->route('get-started', ['report_id' => $report->id])
                ->withErrors(['error' => 'Udfyld venligst alle trin før du fortsætter.']);
        }

        DB::beginTransaction();
        try {
            // Assign user to report and payslip
            $report->update(['user_id' => $request->user()->id]);
            
            if ($report->uploadedPayslip) {
                $report->uploadedPayslip->update([
                    'uploader_id' => $request->user()->id,
                    'source' => 'user_upload',
                ]);
            }

            // Calculate statistics
            $findMatchingPayslips = new FindMatchingPayslips();
            $result = $findMatchingPayslips->find($report);
            $matchingPayslips = $result['payslips'];
            $description = $result['description'];
            $matchType = $result['match_type'];
            $metadata = $result['metadata'];

            $salaries = $matchingPayslips->pluck('total_salary_dkk')->sort()->values();
            $count = $salaries->count();

            $lower = 0;
            $median = 0;
            $upper = 0;

            if ($count > 0) {
                $lower = $this->calculatePercentile($salaries, 0.25);
                $median = $this->calculatePercentile($salaries, 0.50);
                $upper = $this->calculatePercentile($salaries, 0.75);

                // Attach matching payslips
                $report->payslips()->sync($matchingPayslips->pluck('id'));
            }

            // Mark report as completed with statistics and match data
            $report->update([
                'status' => 'completed',
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

            // Find and attach matching job postings
            $findMatchingJobPostings = new FindMatchingJobPostings();
            $findMatchingJobPostings->findAndAttach($report);

            // Count active job postings from thehub.io for this job title
            $activeJobPostingsCount = JobPosting::where('job_title_id', $report->job_title_id)
                ->where('source', 'thehub.io')
                ->count();
            
            $report->update([
                'active_job_postings_the_hub' => $activeJobPostingsCount,
            ]);

            // Clear guest session token
            $request->session()->forget('guest_report_token');

            DB::commit();

            return redirect()->route('reports.show', $report->id)
                ->with('success', 'Rapport oprettet succesfuldt');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Der opstod en fejl ved oprettelse af rapporten: ' . $e->getMessage()]);
        }
    }

    /**
     * Update step 1 data (job title, area, experience, gender, region).
     */
    public function updateStep1(Request $request, Report $report)
    {
        $validated = $request->validate([
            'job_title_id' => 'required|exists:job_titles,id',
            'area_of_responsibility_id' => 'nullable|exists:area_of_responsibilities,id',
            'experience' => 'required|integer|min:0|max:50',
            'gender' => 'nullable|string|in:mand,kvinde,andet,Mand,Kvinde,Andet',
            'region_id' => 'required|exists:regions,id',
        ], [
            'job_title_id.required' => 'Felt er påkrævet',
            'job_title_id.exists' => 'Den valgte jobtitel er ugyldig',
            'area_of_responsibility_id.exists' => 'Det valgte område er ugyldigt',
            'experience.required' => 'Felt er påkrævet',
            'experience.integer' => 'Erfaring skal være et heltal',
            'experience.min' => 'Erfaring skal være mindst 0 år',
            'experience.max' => 'Erfaring må maksimalt være 50 år',
            'region_id.required' => 'Felt er påkrævet',
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

            // Tjek om der er nok payslips
            $findMatchingPayslips = new FindMatchingPayslips();
            $result = $findMatchingPayslips->find($report);
            $matchingPayslips = $result['payslips'];
            $description = $result['description'];

            // Hvis der ikke er nok payslips (under 5), returner fejl med report_id
            if ($matchingPayslips->count() < 5 && $description) {
                return back()->withErrors([
                    'payslip_warning' => $description,
                    'report_id' => $report->id,
                ]);
            }

            return redirect()->route('reports.create', ['report_id' => $report->id]);
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

            return redirect()->route('reports.create', ['report_id' => $report->id]);
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
            $findMatchingPayslips = new FindMatchingPayslips();
            $result = $findMatchingPayslips->find($report);
            $matchingPayslips = $result['payslips'];
            $description = $result['description'];
            $matchType = $result['match_type'];
            $metadata = $result['metadata'];

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

            $report->update([
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

            // Find og forbind matchende job postings
            $findMatchingJobPostings = new FindMatchingJobPostings();
            $findMatchingJobPostings->findAndAttach($report);

            // Count active job postings from thehub.io for this job title
            $activeJobPostingsCount = JobPosting::where('job_title_id', $report->job_title_id)
                ->where('source', 'thehub.io')
                ->count();
            
            $report->update([
                'active_job_postings_the_hub' => $activeJobPostingsCount,
            ]);

            DB::commit();

            return redirect()->route('reports.show', $report->id)
                ->with('success', 'Rapport oprettet succesfuldt');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Der opstod en fejl ved oprettelse af rapporten: ' . $e->getMessage()]);
        }
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
