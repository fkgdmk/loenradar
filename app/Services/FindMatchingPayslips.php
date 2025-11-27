<?php

namespace App\Services;

use App\Models\Payslip;
use App\Models\Report;
use Illuminate\Database\Eloquent\Collection;

class FindMatchingPayslips
{
    /**
     * Find matchende payslips baseret på report's kriterier
     * 
     * @param Report $report
     * @return array{ payslips: Collection, description: string|null }
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

        $matchingPayslips = $baseQuery->clone()
            ->whereBetween('experience', $experienceRange)
            ->whereHas('region', function ($query) use ($statisticalGroup) {
                $query->where('statistical_group', $statisticalGroup);
            })
            ->get();

        $description = null;

        if ($matchingPayslips->count() < 5) {
            $description = "Grundet begrænset data i {$report->region->name} for din profil er rapporten baseret på tal fra hele landet for dit erfaringsniveau. Brug tallene som et generelt pejlemærke for markedet.";

            $matchingPayslips = $baseQuery->clone()
                ->whereBetween('experience', $experienceRange)
                ->get();
        }

        if ($matchingPayslips->count() < 5) {
            $description = "Vi har endnu ikke nok profiler for dit erfaringsniveau ({$experience} år), så vi har bygget rapporten baseret på data fra {$statisticalGroup} på tværs af alle erfaringsniveauer. Vær opmærksom på, at lønspændet derfor kan være bredere end normalt.";

            $matchingPayslips = $baseQuery->clone()
                ->whereHas('region', function ($query) use ($statisticalGroup) {
                    $query->where('statistical_group', $statisticalGroup);
                })
                ->get();
        }

        if ($matchingPayslips->count() < 5) {
            $description = "Rapporten viser det generelle lønniveau for hele landet på tværs af alle erfaringsniveauer, da vi mangler specifik data for din profil. Tallene er derfor kun vejledende.";

            $matchingPayslips = $baseQuery->clone()->get();
        }

        if ($matchingPayslips->count() < 5) {
            $count = $matchingPayslips->count();

            if ($count < 3) {
                $count = 3;
            }
            
            $description = "Vi fandt desværre kun {$count} datapunkter der passede på din profil.";
        }

        return [
            'payslips' => $matchingPayslips,
            'description' => $description,
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
}

