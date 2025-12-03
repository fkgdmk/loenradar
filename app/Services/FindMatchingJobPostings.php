<?php

namespace App\Services;

use App\Models\JobPosting;
use App\Models\Report;
use Illuminate\Support\Facades\Log;

class FindMatchingJobPostings
{
    /**
     * Find matchende job postings baseret på report's job titel
     * og forbind dem til reporten via pivot tabel med match_score
     * 
     * @param Report $report
     * @return int Antal matchende job postings fundet og forbundet
     */
    public function findAndAttach(Report $report): int
    {
        // Tjek at report har en job_title_id
        if (!$report->job_title_id) {
            Log::warning('Report mangler job_title_id', [
                'report_id' => $report->id,
            ]);
            return 0;
        }

        // Find alle job postings med samme job_title_id og load skills
        $matchingJobPostings = JobPosting::where('job_title_id', $report->job_title_id)
            ->whereNotNull('salary_from')
            ->with('skills:id')
            ->get();

        if ($matchingJobPostings->isEmpty()) {
            Log::info('Ingen matchende job postings fundet', [
                'report_id' => $report->id,
                'job_title_id' => $report->job_title_id,
            ]);
            return 0;
        }

        // Hent report data til matching
        $reportExperience = $report->experience ?? 0;
        $reportRegionId = $report->region_id;
        $reportSkillIds = $report->filters['skill_ids'] ?? [];

        // Beregn match_score for hver job posting og byg sync data
        $syncData = [];
        foreach ($matchingJobPostings as $jobPosting) {
            $matchScore = $this->calculateMatchScore(
                $reportExperience,
                $reportRegionId,
                $reportSkillIds,
                $jobPosting
            );

            $syncData[$jobPosting->id] = [
                'match_score' => $matchScore,
            ];
        }

        // Forbind job postings til reporten via pivot tabel med match_score
        $report->jobPostings()->sync($syncData);

        Log::info('Matchende job postings fundet og forbundet med match_score', [
            'report_id' => $report->id,
            'job_title_id' => $report->job_title_id,
            'count' => count($syncData),
            'scores_distribution' => array_count_values(array_column($syncData, 'match_score')),
        ]);

        return count($syncData);
    }

    /**
     * Beregn match_score baseret på region, experience og skills
     * 
     * @param int $reportExperience Reportens erfaring i år
     * @param int|null $reportRegionId Reportens region ID
     * @param array $reportSkillIds Reportens skill IDs
     * @param JobPosting $jobPosting Job posting der skal matches
     * @return int Match score (0-10)
     */
    private function calculateMatchScore(
        int $reportExperience,
        ?int $reportRegionId,
        array $reportSkillIds,
        JobPosting $jobPosting
    ): int {
        // Hent job posting data
        $jobPostingRegionId = $jobPosting->region_id;
        $jobPostingMinimumExperience = $jobPosting->minimum_experience ?? 0;
        $jobPostingSkillIds = $jobPosting->skills->pluck('id')->toArray();

        // Beregn matches
        $regionMatches = $reportRegionId && $jobPostingRegionId && $reportRegionId === $jobPostingRegionId;
        $experienceMatches = $reportExperience >= $jobPostingMinimumExperience;
        $matchingSkills = array_intersect($reportSkillIds, $jobPostingSkillIds);
        $matchingSkillCount = count($matchingSkills);

        // Beregn match_score baseret på prioritet (højeste først)
        
        // Score 10: Region + Experience + 3+ skills
        if ($regionMatches && $experienceMatches && $matchingSkillCount >= 3) {
            return 10;
        }

        // Score 8: Region + Experience + 1-2 skills
        if ($regionMatches && $experienceMatches && $matchingSkillCount >= 1 && $matchingSkillCount <= 2) {
            return 8;
        }

        // Score 6: Region + Experience (ingen skills krav)
        if ($regionMatches && $experienceMatches) {
            return 6;
        }

        // Score 5: Kun region
        if ($regionMatches) {
            return 5;
        }

        // Score 4: Experience + 2+ skills (ingen region)
        if ($experienceMatches && $matchingSkillCount >= 2) {
            return 4;
        }

        // Score 3: Experience ELLER skills (men ikke begge, og ikke region)
        // Dette dækker: kun experience, kun skills, eller experience + 1 skill (uden region)
        if ($experienceMatches || $matchingSkillCount >= 1) {
            return 3;
        }

        // Score 0: Ingen match (kun job titel match)
        return 0;
    }
}

