<?php

namespace App\Services;

use App\Models\JobTitle;
use App\Models\Payslip;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class JobTitleExtractor
{
    /**
     * Ekstrahér job titel, sub titel og erfaring fra payslip titel og beskrivelse
     * 
     * @return array{job_title: JobTitle, sub_job_title: string|null, experience: int|null}|null
     */
    public function extractJobTitle(Payslip $payslip): ?array
    {
        if (empty($payslip->title) && empty($payslip->description)) {
            Log::warning('Ingen titel eller beskrivelse til at ekstrahere job titel', [
                'payslip_id' => $payslip->id,
            ]);
            return null;
        }

        try {
            $extractedData = $this->extractFromOpenAI($payslip->title, $payslip->description);

            if (!$extractedData || !$extractedData['job_title']) {
                return null;
            }

            // Find job titel i databasen (må ikke oprettes ny)
            $jobTitle = JobTitle::where('name', $extractedData['job_title'])->first();

            if (!$jobTitle) {
                Log::error('Job titel findes ikke i databasen efter validering', [
                    'payslip_id' => $payslip->id,
                    'job_title' => $extractedData['job_title'],
                ]);
                return null;
            }

            Log::info('Job titel ekstraheret succesfuldt', [
                'payslip_id' => $payslip->id,
                'job_title' => $extractedData['job_title'],
                'sub_job_title' => $extractedData['sub_job_title'],
                'experience' => $extractedData['experience'],
                'job_title_id' => $jobTitle->id,
            ]);

            return [
                'job_title' => $jobTitle,
                'sub_job_title' => $extractedData['sub_job_title'],
                'experience' => $extractedData['experience'],
            ];

        } catch (\Exception $e) {
            Log::error('Fejl ved ekstraktion af job titel', [
                'payslip_id' => $payslip->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Brug OpenAI til at ekstrahere job titel, sub titel og erfaring
     * 
     * @return array{job_title: string|null, sub_job_title: string|null, experience: int|null}|null
     */
    private function extractFromOpenAI(?string $title, ?string $description): ?array
    {
        $text = trim(($title ?? '') . ' ' . ($description ?? ''));

        if (empty($text)) {
            return null;
        }

        // Hent alle tilladte job titler fra databasen
        $allowedJobTitles = JobTitle::orderBy('name')->pluck('name')->toArray();

        if (empty($allowedJobTitles)) {
            Log::error('Ingen job titler fundet i databasen. Kan ikke ekstrahere job titel.');
            return null;
        }

        // Byg en formateret liste til prompten
        $jobTitlesList = '- ' . implode("\n- ", $allowedJobTitles);

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Du er en ekspert i at identificere job titler ud fra et forum indlæg's titel og beskrivelse. Dit job er at finde den BEDSTE match fra en foruddefineret liste af job titler og ekstrahere yderligere information.

VIGTIGE REGLER:
1. Du må KUN returnere en job_title der findes på listen nedenfor
2. Hvis ingen titel passer, returner {\"job_title\": null, \"sub_job_title\": null, \"experience\": null, \"confidence\": \"low\"}
3. Du må ALDRIG finde på nye job titler
4. Returner ALTID et JSON object: {\"job_title\": \"<titel fra listen>\", \"sub_job_title\": \"<junior/senior/medior/etc eller null>\", \"experience\": <antal år som tal eller null>, \"confidence\": \"high\"|\"medium\"|\"low\"}

SUB JOB TITLE:
- Prøv at identificere senioritetsniveau: \"Junior\", \"Medior\", \"Senior\", \"Lead\", \"Principal\", osv.
- Hvis ikke nævnt, returner null

EXPERIENCE (erfaring i år):
- Prøv at identificere antal års erfaring som et tal (fx 2, 5, 10)
- Hvis der står \"1 års erfaring\" returner 1
- Hvis der står \"efter 3 år\" returner 3
- Hvis ikke nævnt, returner null

TILLADTE JOB TITLER:
{$jobTitlesList}",
                ],
                [
                    'role' => 'user',
                    'content' => "Ekstrahér job titel, sub titel og erfaring fra følgende tekst:\n\nTitel: {$title}\nBeskrivelse: " . mb_substr($description ?? '', 0, 500) . "\n\nFind den mest præcise job titel fra listen ovenfor. Returner KUN en titel der er på listen.",
                ],
            ],
            'response_format' => [
                'type' => 'json_object',
            ],
            'max_tokens' => 200,
            'temperature' => 0,
        ]);

        $content = $response->choices[0]->message->content;
        $data = json_decode($content, true);

        Log::info('OpenAI job titel response', [
            'raw_response' => $content,
            'parsed_data' => $data,
        ]);

        if (!isset($data['job_title']) || $data['job_title'] === null) {
            Log::warning('Ingen job titel fundet i teksten', ['response' => $data]);
            return null;
        }

        // Normaliser job titel
        $jobTitle = $this->normalizeJobTitle($data['job_title']);

        // Verificer at titlen findes i databasen (case-insensitive match)
        $matchedTitle = collect($allowedJobTitles)->first(function ($allowed) use ($jobTitle) {
            return strcasecmp($allowed, $jobTitle) === 0;
        });

        if (!$matchedTitle) {
            Log::warning('OpenAI returnerede en titel der ikke findes i databasen', [
                'returned_title' => $jobTitle,
                'allowed_titles' => $allowedJobTitles,
            ]);
            return null;
        }

        // Normaliser sub_job_title (trim og title case)
        $subJobTitle = isset($data['sub_job_title']) && !empty($data['sub_job_title']) 
            ? trim($data['sub_job_title']) 
            : null;

        // Valider experience er et tal
        $experience = null;
        if (isset($data['experience']) && is_numeric($data['experience'])) {
            $experience = (int) $data['experience'];
            // Sæt grænser for erfaring (0-50 år)
            if ($experience < 0 || $experience > 50) {
                $experience = null;
            }
        }

        return [
            'job_title' => $matchedTitle,
            'sub_job_title' => $subJobTitle,
            'experience' => $experience,
        ];
    }

    /**
     * Normaliser job titel
     */
    private function normalizeJobTitle(string $title): string
    {
        // Trim whitespace
        $title = trim($title);

        // Fjern "job som", "arbejder som", etc.
        $title = preg_replace('/^(job som|arbejder som|stilling som|position som)\s+/i', '', $title);

        // Fjern ekstra whitespace
        $title = preg_replace('/\s+/', ' ', $title);

        return $title;
    }

    /**
     * Beregn estimeret pris for ekstraktion
     */
    public function estimateCost(int $payslipCount): array
    {
        $tokensPerRequest = 300; // System + user prompt + text (øget pga. flere felter)
        $tokensPerResponse = 80; // JSON response med job_title, sub_job_title, experience

        $totalInputTokens = $payslipCount * $tokensPerRequest;
        $totalOutputTokens = $payslipCount * $tokensPerResponse;

        $inputCost = ($totalInputTokens / 1000000) * 0.15; // gpt-4o-mini pricing
        $outputCost = ($totalOutputTokens / 1000000) * 0.60;
        $totalCost = $inputCost + $outputCost;

        return [
            'payslip_count' => $payslipCount,
            'estimated_cost_usd' => round($totalCost, 4),
            'estimated_cost_dkk' => round($totalCost * 7, 2),
            'input_tokens' => $totalInputTokens,
            'output_tokens' => $totalOutputTokens,
        ];
    }
}

