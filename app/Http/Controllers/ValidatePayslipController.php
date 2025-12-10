<?php

namespace App\Http\Controllers;

use App\Models\Payslip;
use App\Models\Report;
use App\Services\PayslipValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Enums\ReportStatus;

class ValidatePayslipController extends Controller
{
    /**
     * Validate and save anonymized payslip image for a report.
     */
    public function __invoke(Request $request, Report $report)
    {
        $isAdmin = (bool) $request->user()?->is_admin;
        $guestToken = $request->session()->get('guest_report_token');
        
        // Verify access to the report
        if ($report->status !== ReportStatus::DRAFT) {
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


        // Check rate limit (10 per hour for logged-in users, 5 per half hour for guests)
        $rateLimitError = $this->checkRateLimit($request, $isAdmin);
        if ($rateLimitError) {
            return $rateLimitError;
        }

        $request->validate([
            'document' => 'required|file|mimes:png,jpg,jpeg|max:10240', // 10MB max
            'original_file_hash' => 'required|string|size:64', // SHA256 hash er altid 64 karakterer
        ], [
            'document.required' => 'Upload venligst din lønseddel',
            'document.file' => 'Lønsedlen skal være en fil',
            'document.mimes' => 'Filen skal være et billede (PNG eller JPG)',
            'document.max' => 'Filen må maksimalt være 10MB',
            'original_file_hash.required' => 'Original fil hash mangler',
            'original_file_hash.size' => 'Ugyldig hash format',
        ]);

        // Brug original fil hash (før anonymisering) til at tjekke for duplikater
        $fileHash = $request->input('original_file_hash');

        $existingMedia = Media::where('collection_name', 'documents')
            ->whereJsonContains('custom_properties', ['file_hash' => $fileHash])
            ->first();

        if ($existingMedia) {
            return back()->withErrors([
                'error' => 'Denne fil er allerede uploadet. Upload venligst en anden fil.'
            ]);
        }

        DB::beginTransaction();

        try {
            // Get gender from filters
            $gender = $report->filters['gender'] ?? null;

            // Check if payslip already exists for this report
            $payslip = $report->uploadedPayslip;
            
            if ($payslip) {
                // Clear existing documents and upload new one
                $payslip->clearMediaCollection('documents');
                $media = $payslip->addMediaFromRequest('document')
                    ->withCustomProperties(['file_hash' => $fileHash])
                    ->toMediaCollection('documents');
            } else {

                if (!$isAdmin) {
                    $this->incrementRateLimit($request);
                }

                // Create new payslip
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
                    'source' => $request->user() ? 'user_upload' : 'guest_upload',
                ]);

                // Update report with payslip
                $report->update([
                    'uploaded_payslip_id' => $payslip->id,
                ]);

                // Upload new document
                $media = $payslip->addMediaFromRequest('document')
                    ->withCustomProperties(['file_hash' => $fileHash])
                    ->toMediaCollection('documents');

                try {
                    // Valider og analyser lønseddel
                    $validator = new PayslipValidator();
                    $analysis = $validator->validateAndAnalyze($media);

                    // Tjek om det er en lønseddel
                    if (!$analysis['is_payslip']) {
                        $payslip->clearMediaCollection('documents');
                        DB::rollBack();
                        return back()->withErrors([
                            'error' => 'Uploadet fil ser ikke ud til at være en lønseddel. Upload venligst en gyldig lønseddel.'
                        ]);
                    }

                    // Tjek om dato er ældre end et år
                    if ($analysis['payslip_date'] !== null) {
                        try {
                            $payslipDate = \Carbon\Carbon::createFromFormat('Y-m-d', $analysis['payslip_date']);
                            $oneYearAgo = now()->subMonths(18);
                            
                            if ($payslipDate->lt($oneYearAgo)) {
                                $payslip->clearMediaCollection('documents');
                                DB::rollBack();
                                return back()->withErrors([
                                    'error' => 'Lønseddelen er ældre end et år. Upload venligst en lønseddel fra de seneste 12 måneder.'
                                ]);
                            }

                            $payslip->update(['uploaded_at' => $analysis['payslip_date']]);
                        } catch (\Exception $e) {
                            // Hvis dato ikke kan parses, log og fortsæt
                            Log::warning('Kunne ikke parse payslip dato', [
                                'date' => $analysis['payslip_date'],
                                    'error' => $e->getMessage(),
                                ]);
                            }
                    }
                    // Opdater payslip med salary hvis fundet
                    if ($analysis['salary'] !== null) {
                        $payslip->update(['salary' => $analysis['salary']]);
                    }
                } catch (\Exception $e) {}
            }



            DB::commit();

            // Redirect to the same page (get-started or reports.create) with report_id to maintain state
            if ($request->user()) {
                return redirect()->route('reports.create', ['report_id' => $report->id])
                    ->with('success', 'Lønseddel gemt succesfuldt');
            } else {
                return redirect()->route('get-started', ['report_id' => $report->id])
                    ->with('success', 'Lønseddel gemt succesfuldt');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Fejl ved validering af lønseddel', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'Der opstod en fejl ved gemning af lønsedlen.']);
        }
    }

    /**
     * Check rate limit for payslip uploads.
     * Logged-in users: 10 per hour
     * Guests: 5 per half hour
     */
    private function checkRateLimit(Request $request, bool $isAdmin): ?\Illuminate\Http\RedirectResponse
    {
        // if ($isAdmin) {
        //     return null;
        // }

        // Throttle: 3 uploads per minute
        $key = 'analyze-payslip:' . ($request->user()?->id ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors([
                'error' => "Du har uploadet for mange filer. Prøv igen om {$seconds} sekunder."
            ]);
        }

        $isLoggedIn = $request->user() !== null;
        
        if ($isLoggedIn) {
            // Logged-in users: 10 uploads per hour
            $key = 'analyze-payslip-hour:' . $request->user()->id;
            if (RateLimiter::tooManyAttempts($key, 10)) {
                $seconds = RateLimiter::availableIn($key);
                $minutes = ceil($seconds / 60);
                return back()->withErrors([
                    'error' => "Du har forsøgt at uploade for mange filer. Prøv igen om {$minutes} minut(ter)."
                ]);
            }
        } else {
            // Guests: 5 uploads per half hour
            $key = 'analyze-payslip-halfhour:' . $request->ip();
            if (RateLimiter::tooManyAttempts($key, 5)) {
                $seconds = RateLimiter::availableIn($key);
                $minutes = ceil($seconds / 60);
                return back()->withErrors([
                    'error' => "Du har forsøgt at uploade for mange filer. Prøv igen om {$minutes} minut(ter)."
                ]);
            }
        }

        return null;
    }

    /**
     * Increment rate limit counter for payslip uploads.
     */
    private function incrementRateLimit(Request $request): void
    {
        $isLoggedIn = $request->user() !== null;

        RateLimiter::hit('analyze-payslip:' . ($request->user()?->id ?? $request->ip()), 60); // 60 seconds = 1 minute
        
        if ($isLoggedIn) {
            // Logged-in users: 10 uploads per hour (3600 seconds)
            $key = 'analyze-payslip-hour:' . $request->user()->id;
            RateLimiter::hit($key, 3600);
        } else {
            // Guests: 5 uploads per half hour (1800 seconds)
            $key = 'analyze-payslip-halfhour:' . $request->ip();
            RateLimiter::hit($key, 1800);
        }
    }
}
