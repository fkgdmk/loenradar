<?php

namespace App\Services;

use App\Enums\PayslipMatchType;
use App\Enums\ReportStatus;
use App\Models\JobPosting;
use App\Models\Report;

class ReportFinalizationService
{
    /**
     * Finalize a report by calculating statistics, finding matches, and generating conclusions.
     *
     * @param Report $report
     * @return void
     */
    public function finalize(Report $report): void
    {
        // Calculate statistics
        $findMatchingPayslips = new FindMatchingPayslips();
        $result = $findMatchingPayslips->find($report);
        $matchingPayslips = $result['payslips'];
        $description = $result['description'];
        $matchType = $result['match_type'];
        $metadata = $result['metadata'];

        // Find and attach matching job postings
        $findMatchingJobPostings = new FindMatchingJobPostings();
        $findMatchingJobPostings->findAndAttach($report);

        // Calculate percentiles from matching payslips
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
        $status = ReportStatus::COMPLETED;
        if ($matchType === PayslipMatchType::INSUFFICIENT_DATA) {
            $status = ReportStatus::AWAITING_DATA;
        }

        // Mark report as completed (or awaiting data) with statistics and match data
        $report->update([
            'status' => $status->value,
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
    }

    /**
     * Calculate percentile from sorted data.
     *
     * @param \Illuminate\Support\Collection $sortedData
     * @param float $percentile
     * @return float
     */
    private function calculatePercentile($sortedData, $percentile): float
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
