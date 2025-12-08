<?php

namespace App\Services;

use App\Enums\PayslipMatchType;
use App\Models\Report;

class ReportConclusionGenerator
{
    /**
     * Generer og gem konklusion for en rapport
     * 
     * Forudsætter at følgende allerede er sat på rapporten:
     * - payslip_match (match type)
     * - match_metadata (metadata inkl. salary_min, salary_max, payslip_count)
     * - lower_percentile, median, upper_percentile (statistik)
     * - payslips relation (matchende payslips)
     */
    public function generate(Report $report): void
    {
        $report->conclusion = $this->generateConclusion($report);
        $report->save();
    }

    /**
     * Generer konklusion baseret på match-type
     */
    private function generateConclusion(Report $report): string
    {
        return match($report->payslip_match) {
            PayslipMatchType::FULL_MATCH => $this->generateFullMatchConclusion($report),
            PayslipMatchType::EXPERIENCE_MATCH => $this->generateExperienceMatchConclusion($report),
            PayslipMatchType::REGION_MATCH,
            PayslipMatchType::TITLE_MATCH => $this->generateBroadMatchConclusion($report),
            PayslipMatchType::LIMITED_DATA => $this->generateLimitedDataConclusion($report),
            PayslipMatchType::INSUFFICIENT_DATA => $this->generateInsufficientDataConclusion($report),
        };
    }

    /**
     * Fuld match konklusion - både erfaring og region matcher
     */
    private function generateFullMatchConclusion(Report $report): string
    {
        $jobTitle = $report->jobTitle->name_en ?? 'din stilling';
        $region = $report->region->name ?? 'din region';
        $experience = $report->experience;
        $experienceRange = $report->match_metadata['experience_range'] ?? [0, 100];
        $experienceRangeLabel = $this->getExperienceRangeLabel($experience);
        $count = $this->getPayslipCount($report);
        
        $medianFormatted = $this->formatSalary($report->median);
        
        // Beregn brugerens position i erfaringsintervallet
        $rangeMin = $experienceRange[0];
        $rangeMax = $experienceRange[1];
        $rangeSpan = max($rangeMax - $rangeMin, 1);
        $positionInRange = ($experience - $rangeMin) / $rangeSpan;
        
        // Bestem tekst baseret på position i erfaringsintervallet
        $positionText = $this->getExperiencePositionText($report, $experienceRange);
        
        // Beregn anbefalet lønudspil baseret på erfaring
        $recommendedRange = $this->calculateRecommendedSalaryRange($positionInRange, $report);
        $recommendedLower = $this->formatSalary($recommendedRange['lower']);
        $recommendedUpper = $this->formatSalary($recommendedRange['upper']);
        
        $conclusion = "**Din markedsværdi:** Vores data viser, at en {$jobTitle} i {$region} med {$experienceRangeLabel} typisk ligger på ca. {$medianFormatted} (Median).\n\n";
        $conclusion .= "**Vores analyse af din profil:** {$positionText}\n\n";
        $conclusion .= "🎯 **Anbefalet lønudspil:** {$recommendedLower} til {$recommendedUpper}";
        
        return $conclusion;
    }

    /**
     * Erfarings-match konklusion - kun erfaring matcher (hele landet)
     */
    private function generateExperienceMatchConclusion(Report $report): string
    {
        $jobTitle = $report->jobTitle->name_en ?? 'din stilling';
        $experience = $report->experience;
        $experienceRange = $report->match_metadata['experience_range'] ?? [0, 100];
        $experienceRangeLabel = $this->getExperienceRangeLabel($experience);
        
        $medianFormatted = $this->formatSalary($report->median);
        $lowerFormatted = $this->formatSalary($report->lower_percentile);
        $upperFormatted = $this->formatSalary($report->upper_percentile);
        
        // Beregn brugerens position i erfaringsintervallet
        $rangeMin = $experienceRange[0];
        $rangeMax = $experienceRange[1];
        $rangeSpan = max($rangeMax - $rangeMin, 1);
        $positionInRange = ($experience - $rangeMin) / $rangeSpan;
        
        // Generer simpel profil-analyse
        $positionText = $this->getSimpleExperiencePositionText($experience, $positionInRange, $experienceRangeLabel);
        
        // Beregn anbefalet lønudspil baseret på erfaring
        $recommendedRange = $this->calculateRecommendedSalaryRange($positionInRange, $report);
        $recommendedLower = $this->formatSalary($recommendedRange['lower']);
        $recommendedUpper = $this->formatSalary($recommendedRange['upper']);
        
        $conclusion = "**Din markedsværdi:** På landsplan viser vores data, at en {$jobTitle} med {$experienceRangeLabel} typisk ligger på ca. {$medianFormatted} (Median).\n\n";
        $conclusion .= "**Vores analyse af din profil:** {$positionText}\n\n";
        $conclusion .= "🎯 **Anbefalet lønudspil:** {$recommendedLower} til {$recommendedUpper}";
        
        return $conclusion;
    }

    /**
     * Bred match konklusion - region eller titel matcher (bredt erfaringsspænd)
     */
    private function generateBroadMatchConclusion(Report $report): string
    {
        $experience = $report->experience;
        $metadata = $report->match_metadata ?? [];
        $dataExpMin = $metadata['data_experience_min'] ?? 0;
        $dataExpMax = $metadata['data_experience_max'] ?? 50;
        
        // Tjek om brugerens erfaring er inden for datasættet
        if ($experience >= $dataExpMin && $experience <= $dataExpMax) {
            return $this->generateWithinRangeConclusion($report, $dataExpMin, $dataExpMax);
        } elseif ($experience > $dataExpMax) {
            return $this->generateAboveRangeConclusion($report, $dataExpMin, $dataExpMax);
        } else {
            return $this->generateBelowRangeConclusion($report, $dataExpMin, $dataExpMax);
        }
    }

    /**
     * Konklusion når brugerens erfaring er inden for datasættets interval
     */
    private function generateWithinRangeConclusion(Report $report, int $dataExpMin, int $dataExpMax): string
    {
        $experience = $report->experience;
        $region = $report->region->name ?? 'din region';
        $count = $this->getPayslipCount($report);
        
        $rangeSpan = max($dataExpMax - $dataExpMin, 1);
        $positionInRange = ($experience - $dataExpMin) / $rangeSpan;
        
        $lowerFormatted = $this->formatSalary($report->lower_percentile);
        $upperFormatted = $this->formatSalary($report->upper_percentile);
        
        $matchContext = $report->payslip_match === PayslipMatchType::REGION_MATCH 
            ? "i {$region}" 
            : "på landsplan";
        
        // Bestem hvilket interval der er relevant baseret på position
        if ($positionInRange >= 0.67) {
            $recommendedLower = $this->formatSalary($report->median);
            $recommendedUpper = $this->formatSalary($report->upper_percentile);
            $positionDescription = "i den erfarne ende af dette datasæt, hvilket typisk indikerer, at din markedsværdi ligger i den øvre del af intervallet";
        } elseif ($positionInRange <= 0.33) {
            $recommendedLower = $this->formatSalary($report->lower_percentile);
            $recommendedUpper = $this->formatSalary($report->median);
            $positionDescription = "i den mindre erfarne del af dette datasæt, hvilket typisk indikerer, at din markedsværdi ligger i den nedre del af intervallet";
        } else {
            $recommendedLower = $lowerFormatted;
            $recommendedUpper = $upperFormatted;
            $positionDescription = "i midten af dette datasæt erfaringsmæssigt";
        }
        
        $conclusion = "**Datagrundlag:** Vi har sammenlignet bredt på erfaring ({$dataExpMin}–{$dataExpMax} år) {$matchContext}.\n\n";
        $conclusion .= "**Din placering:** Med dine {$experience} års erfaring placerer du dig {$positionDescription}.\n\n";
        $conclusion .= "🎯 **Anbefalet lønudspil:** {$recommendedLower} til {$recommendedUpper}";
        
        return $conclusion;
    }

    /**
     * Konklusion når brugerens erfaring er over datasættets maksimum
     */
    private function generateAboveRangeConclusion(Report $report, int $dataExpMin, int $dataExpMax): string
    {
        $experience = $report->experience;
        $maxSalary = $report->match_metadata['salary_max'] ?? $report->upper_percentile;
        $maxFormatted = $this->formatSalary($maxSalary);
        
        $conclusion = "**Datagrundlag:** {$dataExpMin}–{$dataExpMax} års erfaring\n\n";
        $conclusion .= "Vi har i øjeblikket flest data på profiler med kortere anciennitet end dig.\n\n";
        $conclusion .= "**Din profil ({$experience} år):**\n";
        $conclusion .= "Da du har markant mere erfaring end gennemsnittet i vores database, kan vi ikke give dig et præcist markeds-estimat endnu.\n\n";
        $conclusion .= "📊 **Til sammenligning:** Toppen for profiler med {$dataExpMax} års erfaring ligger på {$maxFormatted}.\n\n";
        $conclusion .= "💡 Som seniorprofil med {$experience} års erfaring bør du naturligvis ligge væsentligt over dette niveau.";
        
        return $conclusion;
    }

    /**
     * Konklusion når brugerens erfaring er under datasættets minimum
     */
    private function generateBelowRangeConclusion(Report $report, int $dataExpMin, int $dataExpMax): string
    {
        $experience = $report->experience;
        $minSalary = $report->match_metadata['salary_min'] ?? $report->lower_percentile;
        $minFormatted = $this->formatSalary($minSalary);
        
        $conclusion = "**Datagrundlag:** {$dataExpMin}–{$dataExpMax} års erfaring\n\n";
        $conclusion .= "Vi har i øjeblikket flest data på profiler med mere erfaring end dig.\n\n";
        $conclusion .= "**Din profil ({$experience} år):**\n";
        $conclusion .= "Da du har mindre erfaring end de fleste i vores database for denne stilling, kan vi kun give dig et vejledende estimat.\n\n";
        $conclusion .= "📊 **Til sammenligning:** Bunden for profiler med {$dataExpMin} års erfaring ligger på {$minFormatted}.\n\n";
        $conclusion .= "💡 Som ny i branchen med {$experience} års erfaring er det naturligt at starte lidt under dette niveau, men du har et stort vækstpotentiale.";
        
        return $conclusion;
    }

    /**i
     * Konklusion med begrænset data (5-9 payslips)
     */
    private function generateLimitedDataConclusion(Report $report): string
    {
        $jobTitle = $report->jobTitle->name_en ?? 'din stilling';
        $experience = $report->experience;
        $count = $this->getPayslipCount($report);
        $lowerFormatted = $this->formatSalary($report->lower_percentile);
        $upperFormatted = $this->formatSalary($report->upper_percentile);
        $medianFormatted = $this->formatSalary($report->median);
        
        $conclusion = "**Din markedsværdi:** Baseret på {$count} datapunkter for {$jobTitle} ligger lønnen typisk omkring **{$lowerFormatted} til {$upperFormatted}**.\n\n";
        $conclusion .= "*Dette interval er baseret på et begrænset datagrundlag og bør kun bruges som en vejledende pejling. Efterhånden som vi indsamler flere data, vil vi kunne give dig et mere præcist estimat.*";
        
        return $conclusion;
    }

    /**
     * Konklusion med utilstrækkelig data
     */
    private function generateInsufficientDataConclusion(Report $report): string
    {
        $count = $this->getPayslipCount($report);
        
        if ($count === 0) {
            return "**Utilstrækkelig data**\n\nVi har desværre ikke nok data til at give dig et lønestimering for din profil endnu. Prøv igen senere, når vi har indsamlet flere lønsedler.";
        }
        
        $lowerFormatted = $this->formatSalary($report->lower_percentile);
        $upperFormatted = $this->formatSalary($report->upper_percentile);
        
        return "**Meget begrænset data ({$count} datapunkter)**\n\n📊 **Vejledende interval:** {$lowerFormatted} til {$upperFormatted}\n\n**Disclaimer:** Dette estimat er baseret på et meget begrænset datagrundlag og bør tages med forbehold. Vi anbefaler at supplere med andre kilder, når du skal vurdere din markedsværdi.";
    }

    /**
     * Beregn anbefalet løninterval baseret på position i erfaringsintervallet
     */
    private function calculateRecommendedSalaryRange(float $positionInRange, Report $report): array
    {
        $lower = (float) $report->lower_percentile;
        $median = (float) $report->median;
        $upper = (float) $report->upper_percentile;
        
        // Hvis i øverste tredjedel af erfaringsintervallet
        if ($positionInRange >= 0.67) {
            return ['lower' => $median, 'upper' => $upper];
        }
        
        // Hvis i nederste tredjedel
        if ($positionInRange <= 0.33) {
            return ['lower' => $lower, 'upper' => $median];
        }
        
        // Midten - interpoler baseret på position
        $salaryRange = $upper - $lower;
        $baseLower = $lower + ($salaryRange * $positionInRange * 0.5);
        $baseUpper = $median + ($salaryRange * $positionInRange * 0.3);
        
        return [
            'lower' => round($baseLower, -3),
            'upper' => min(round($baseUpper, -3), $upper),
        ];
    }

    /**
     * Generer simpel tekst om brugerens position (til experience match)
     */
    private function getSimpleExperiencePositionText(int $experience, float $positionInRange, string $experienceRangeLabel): string
    {
        if ($positionInRange <= 0.33) {
            return "Med {$experience} års erfaring er du relativt ny i denne kategori ({$experienceRangeLabel}). Du har et godt vækstpotentiale de kommende år.";
        }
        
        if ($positionInRange >= 0.67) {
            return "Med {$experience} års erfaring er du i den erfarne del af denne kategori ({$experienceRangeLabel}). Du bør forvente at ligge i den øvre del af intervallet.";
        }
        
        return "Med {$experience} års erfaring placerer du dig omkring midten af denne kategori ({$experienceRangeLabel}).";
    }

    /**
     * Generer tekst om brugerens position i erfaringsintervallet
     */
    private function getExperiencePositionText(Report $report, array $experienceRange): string
    {
        $experience = $report->experience;
        $rangeMin = $experienceRange[0];
        $rangeMax = $experienceRange[1];
        $rangeSpan = max($rangeMax - $rangeMin, 1);
        $positionInRange = ($experience - $rangeMin) / $rangeSpan;
        
        $lowerFormatted = $this->formatSalary($report->lower_percentile);
        $upperFormatted = $this->formatSalary($report->upper_percentile);
        
        $rangeLabel = $rangeMax >= 100 ? "{$rangeMin}+ års erfaring" : "{$rangeMin}-{$rangeMax} års erfaring";
        
        if ($positionInRange <= 0.25) {
            return "Med {$experience} års erfaring er du relativt ny i denne kategori ({$rangeLabel}). Det betyder, at det er helt naturligt, hvis du lige nu ligger tættere på {$lowerFormatted} end {$upperFormatted}.\n\nMen det betyder også, at du har et stort vækstpotentiale de kommende år.";
        }
        
        if ($positionInRange >= 0.75) {
            return "Med {$experience} års erfaring er du i den erfarne del af denne kategori ({$rangeLabel}). Du har solid erfaring og bør forvente at ligge i den øvre del af intervallet, tættere på {$upperFormatted}.";
        }
        
        return "Med {$experience} års erfaring placerer du dig omkring midten af denne kategori ({$rangeLabel}). Du bør forvente at ligge omkring medianen, med potentiale for at nå den øvre del efterhånden som du opbygger mere erfaring.";
    }

    /**
     * Hent antal payslips fra metadata eller relation
     */
    private function getPayslipCount(Report $report): int
    {
        return $report->match_metadata['payslip_count'] ?? $report->payslips()->count();
    }

    /**
     * Formater løn til dansk format (rundet til nærmeste tusinde)
     */
    private function formatSalary(float|int $salary): string
    {
        $rounded = round($salary, -3);
        return number_format($rounded, 0, ',', '.') . ' kr.';
    }

    /**
     * Hent erfaringsområde-label baseret på antal år
     */
    private function getExperienceRangeLabel(int $years): string
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
