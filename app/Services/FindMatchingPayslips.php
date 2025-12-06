<?php

namespace App\Services;

use App\Enums\PayslipMatchType;
use App\Models\Payslip;
use App\Models\Report;
use Illuminate\Database\Eloquent\Collection;

class FindMatchingPayslips
{
    /**
     * Find matchende payslips baseret på report's kriterier
     * 
     * @param Report $report
     * @return array{ payslips: Collection, description: string|null, match_type: PayslipMatchType, metadata: array }
     */
    public function find(Report $report): array
    {
        $experience = $report->experience;
        $statisticalGroup = $report->region->statistical_group;
        $experienceRange = $this->getExperienceRange($experience);

        $baseQuery = Payslip::where('job_title_id', $report->job_title_id)
            ->where('id', '!=', $report->uploaded_payslip_id)   
            ->whereNotNull('verified_at')
            ->whereNotNull('salary');

        // Forsøg 1: Fuld match - både erfaring og region
        $matchingPayslips = $baseQuery->clone()
            ->whereBetween('experience', $experienceRange)
            ->whereHas('region', function ($query) use ($statisticalGroup) {
                $query->where('statistical_group', $statisticalGroup);
            })
            ->get();

        $description = null;
        $matchType = PayslipMatchType::FULL_MATCH;
        $metadata = [
            'experience_range' => $experienceRange,
            'statistical_group' => $statisticalGroup,
            'user_experience' => $experience,
        ];

        // Forsøg 2: Kun erfaring matcher (hele landet)
        if ($matchingPayslips->count() < 5) {
            $description = "Grundet begrænset data i {$report->region->name} for din profil, er rapporten baseret på tal fra hele landet for dit erfaringsniveau. Brug tallene som et generelt pejlemærke for markedet.";

            $matchingPayslips = $baseQuery->clone()
                ->whereBetween('experience', $experienceRange)
                ->get();

            $matchType = PayslipMatchType::EXPERIENCE_MATCH;
        }

        // Forsøg 3: Kun region matcher (alle erfaringsniveauer)
        if ($matchingPayslips->count() < 5) {
            $description = "Vi har endnu ikke nok profiler for dit erfaringsniveau ({$experience} år), så vi har bygget rapporten baseret på data fra {$statisticalGroup} på tværs af alle erfaringsniveauer. Vær opmærksom på, at lønspændet derfor kan være bredere end normalt.";

            $matchingPayslips = $baseQuery->clone()
                ->whereHas('region', function ($query) use ($statisticalGroup) {
                    $query->where('statistical_group', $statisticalGroup);
                })
                ->get();

            $matchType = PayslipMatchType::REGION_MATCH;
            
            // Beregn min/max erfaring i datasættet
            $metadata['data_experience_min'] = $matchingPayslips->min('experience');
            $metadata['data_experience_max'] = $matchingPayslips->max('experience');
        }

        // Forsøg 4: Kun jobtitel matcher (hele landet, alle erfaringsniveauer)
        if ($matchType === PayslipMatchType::REGION_MATCH && $matchingPayslips->count() < 10) {
            $description = "Rapporten viser det generelle lønniveau for hele landet, på tværs af alle erfaringsniveauer, da vi mangler mere data for din profil. Tallene er derfor kun vejledende.";

            $matchingPayslips = $baseQuery->clone()->get();

            $matchType = PayslipMatchType::TITLE_MATCH;
            
            // Beregn min/max erfaring i datasættet
            $metadata['data_experience_min'] = $matchingPayslips->min('experience');
            $metadata['data_experience_max'] = $matchingPayslips->max('experience');
        }

        // Tjek for begrænset data
        $count = $matchingPayslips->count();
        if ($count < 5) {
            $displayCount = max($count, 3);
            $description = "Vi fandt desværre kun {$displayCount} datapunkter der passede på din profil.";
            $matchType = PayslipMatchType::INSUFFICIENT_DATA;
        } elseif ($count < 10) {
            // 5-9 payslips - begrænset data, men brugbart
            if ($matchType === PayslipMatchType::REGION_MATCH || $matchType === PayslipMatchType::TITLE_MATCH) {
                $matchType = PayslipMatchType::LIMITED_DATA;
            }
        }

        // Beregn metadata for løn-statistik
        if ($matchingPayslips->count() > 0) {
            $salaries = $matchingPayslips->pluck('total_salary_dkk')->sort()->values();
            $metadata['salary_min'] = $salaries->first();
            $metadata['salary_max'] = $salaries->last();
            $metadata['payslip_count'] = $salaries->count();
        }

        return [
            'payslips' => $matchingPayslips,
            'description' => $description,
            'match_type' => $matchType,
            'metadata' => $metadata,
        ];
    }

    /**
     * Beregn erfaringsområde baseret på antal år
     * 
     * @param int $years
     * @return array{0: int, 1: int}
     */
    private function getExperienceRange(int $years): array
    {
        if ($years <= 3) {
            return [0, 3];
        }
        if ($years <= 9) {
            return [4, 9];
        }
        return [10, 100]; // 10+ år
    }

    /**
     * Hent erfaringsområde-label baseret på antal år
     */
    public function getExperienceRangeLabel(int $years): string
    {
        if ($years <= 3) {
            return '0-3 års erfaring';
        }
        if ($years <= 9) {
            return '4-9 års erfaring';
        }
        return '10+ års erfaring';
    }
}
