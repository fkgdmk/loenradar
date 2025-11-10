<?php

namespace App\Services;

use App\Models\JobTitle;
use App\Models\Payslip;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class JobTitleExtractor
{
    /**
     * Ekstrahér job titel fra payslip titel og beskrivelse
     */
    public function extractJobTitle(Payslip $payslip): ?JobTitle
    {
        if (empty($payslip->title) && empty($payslip->description)) {
            Log::warning('Ingen titel eller beskrivelse til at ekstrahere job titel', [
                'payslip_id' => $payslip->id,
            ]);
            return null;
        }

        try {
            $jobTitleName = $this->extractFromOpenAI($payslip->title, $payslip->description);

            if (!$jobTitleName) {
                return null;
            }

            // Find eller opret job titel
            $jobTitle = JobTitle::firstOrCreate(['name' => $jobTitleName]);

            // Opdater payslip med job titel
            $payslip->update(['job_title_id' => $jobTitle->id]);

            Log::info('Job titel ekstraheret succesfuldt', [
                'payslip_id' => $payslip->id,
                'job_title' => $jobTitleName,
                'job_title_id' => $jobTitle->id,
            ]);

            return $jobTitle;

        } catch (\Exception $e) {
            Log::error('Fejl ved ekstraktion af job titel', [
                'payslip_id' => $payslip->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Brug OpenAI til at ekstrahere job titel
     */
    private function extractFromOpenAI(?string $title, ?string $description): ?string
    {
        $text = trim(($title ?? '') . ' ' . ($description ?? ''));

        if (empty($text)) {
            return null;
        }

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Du er en ekspert i at identificere job titler fra danske lønsedler. Dit job er at ekstrahere den præcise job titel fra tekst. Returner ALTID et JSON object med strukturen: {"job_title": "<titel>", "confidence": <"high"|"medium"|"low">}. Hvis du ikke kan finde en job titel, returner {"job_title": null, "confidence": "low"}. Brug kun dansk standard job titler (fx "Software Engineer", "Data Scientist", "Læge", "Sygeplejerske", "Psykolog").',
                ],
                [
                    'role' => 'user',
                    'content' => "Ekstrahér job titlen fra følgende tekst:\n\nTitel: {$title}\nBeskrivelse: " . mb_substr($description ?? '', 0, 500) . "\n\nFind den mest præcise job titel. Returner kun titlen uden ekstra information som firma, lokation eller anciennitet.",
                ],
            ],
            'response_format' => [
                'type' => 'json_object',
            ],
            'max_tokens' => 150,
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

        // Normaliser job titel (trim, title case)
        $jobTitle = $this->normalizeJobTitle($data['job_title']);

        return $jobTitle;
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
        $tokensPerRequest = 250; // System + user prompt + text
        $tokensPerResponse = 50; // JSON response

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

