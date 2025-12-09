<?php

namespace App\Http\Responses;

use App\Models\Report;
use App\Services\FindMatchingPayslips;
use App\Services\FindMatchingJobPostings;
use App\Services\ReportConclusionGenerator;
use App\Enums\PayslipMatchType;
use App\Models\JobPosting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class RegisterResponse implements RegisterResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        // Check if there's a guest report token in session
        $guestToken = $request->session()->get('guest_report_token');
        $redirectUrl = config('fortify.home', '/dashboard');
        
        if ($guestToken) {
            // Find the draft report by guest token
            $report = Report::where('guest_token', $guestToken)
                ->where('status', 'draft')
                ->with('uploadedPayslip')
                ->first();
            
            if ($report) {
                // Update user_id on the report when guest registers
                if (!$report->user_id && $request->user()) {
                    $report->update(['user_id' => $request->user()->id]);
                    
                    // Also update uploaded payslip if it exists
                    if ($report->uploadedPayslip && !$report->uploadedPayslip->uploader_id) {
                        $report->uploadedPayslip->update([
                            'uploader_id' => $request->user()->id,
                            'source' => 'user_upload',
                        ]);
                    }
                    
                    // Auto-finalize report if it's ready (has payslip and responsibility_level_id)
                    $filters = $report->filters ?? [];
                    if ($report->uploadedPayslip && isset($filters['responsibility_level_id'])) {
                        try {
                            DB::beginTransaction();
                            
                            // Calculate statistics
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

                                // Attach matching payslips
                                $report->payslips()->sync($matchingPayslips->pluck('id'));
                            }

                            // Determine status
                            $status = 'completed';
                            if ($matchType === PayslipMatchType::INSUFFICIENT_DATA) {
                                $status = 'draft';
                            }

                            // Mark report as completed (or draft) with statistics and match data
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

                            // Refresh report to get updated status
                            $report->refresh();
                            
                            DB::commit();
                        } catch (\Exception $e) {
                            DB::rollBack();
                            // Continue with redirect even if finalization fails
                        }
                    }
                }
                
                // Refresh report to get latest status
                $report->refresh();
                
                // Redirect to /reports if insufficient data, otherwise to report show page
                if ($report->payslip_match === 'insufficient_data') {
                    // Use Inertia::location() to preserve flash messages
                    $request->session()->flash('success', 'insufficient_data');
                    return Inertia::location(route('reports.index'));
                } elseif ($report->status === 'completed' && $report->payslip_match && $report->payslip_match !== 'insufficient_data') {
                    // Use Inertia::location() to preserve flash messages
                    $request->session()->flash('success', 'Rapport oprettet succesfuldt');
                    return Inertia::location(route('reports.show', $report->id));
                } else {
                    // If not finalized yet or insufficient data, go to /reports
                    $redirectUrl = route('reports.index');
                }
            }
        }

        if ($request->wantsJson()) {
            return new JsonResponse(['redirect' => $redirectUrl], 201);
        }
        
        return redirect()->intended($redirectUrl);
    }
    
    /**
     * Calculate percentile from sorted data.
     */
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
