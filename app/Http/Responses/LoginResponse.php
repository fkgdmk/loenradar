<?php

namespace App\Http\Responses;

use App\Models\Report;
use App\Services\ReportFinalizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use App\Enums\ReportStatus;

class LoginResponse implements LoginResponseContract
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
                ->where('status', ReportStatus::DRAFT)
                ->with('uploadedPayslip')
                ->first();
            
            if ($report) {
                // Update user_id on the report when guest logs in
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
                            
                            $finalizationService = new ReportFinalizationService();
                            $finalizationService->finalize($report);
                            
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
            return new JsonResponse(['two_factor' => false, 'redirect' => $redirectUrl], 200);
        }
        
        return redirect()->intended($redirectUrl);
    }
}
