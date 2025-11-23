<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class ExtractJobPostingExperience
{
    /**
     * Ekstrahér minimum experience fra job posting beskrivelse ved hjælp af OpenAI
     *
     * @param string $description Job posting beskrivelse
     * @return int|null Minimum experience i år, eller null hvis ikke fundet/fejl
     */
    public function extractMinimumExperience(string $description): ?int
    {
        if (empty($description)) {
            return null;
        }

        try {
            // Truncate description hvis den er for lang
            $descriptionPreview = mb_substr($description, 0, 4000);

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "Du er en ekspert i at ekstrahere minimum erfaring (i år) fra job posting beskrivelser.

                KRITISKE REGLER:
                1. Du skal identificere MINIMUM erfaring kravet (ikke maksimum eller gennemsnit)
                2. Returner ALTID et JSON object: {\"minimum_experience\": <tal>} eller {\"minimum_experience\": null} hvis ikke fundet
                3. Værdien skal være et heltal mellem 0 og 20 år
                4. Hvis der står \"minimum X år\", \"mindst X år\", \"X+ år\", \"X års erfaring\" → returner X
                5. Hvis der står \"senior\" eller \"erfaren\" uden specifikt tal → returner 5 (standard senior niveau)
                6. Hvis der står \"junior\" eller \"nyuddannet\" → returner 0
                7. Hvis der står \"mid-level\" eller \"erfaren\" → returner 3
                8. Hvis ingen specifik erfaring nævnes → returner null

                HVOR SKAL DU SØGE EFTER ERFARING:
                - Se efter sektioner med overskrifter som: \"requirements\", \"krav\", \"qualifications\", \"erfaring\", \"experience\", \"what you bring\", \"vi søger\", osv.
                - Se efter formuleringer som: \"minimum X år\", \"mindst X år\", \"X+ års erfaring\", \"X års erfaring\", \"senior\", \"junior\", \"erfaren\", osv.
                - Analyser HELE beskrivelsen grundigt - erfaring kan være nævnt både tidligt og sent i teksten

                HVAD SKAL DU IGNORERE:
                - Maksimum erfaring (fx \"maksimalt X år\")
                - Ønsket erfaring der ikke er et krav (fx \"det kunne være fedt hvis du har X år\")
                - Erfaring der kun nævnes i forbifarten uden kontekst

                EKSEMPLER (forskellige brancher):
                - \"Minimum 3 års erfaring inden for X\" → returner 3
                - \"Mindst 5 års relevant erfaring\" → returner 5
                - \"Vi søger en senior projektleder\" → returner 5
                - \"Junior stilling eller nyuddannet\" → returner 0
                - \"Erfaren udvikler med minimum 2 års erfaring\" → returner 2
                - \"Ingen specifik erfaring påkrævet\" → returner null
                - \"Minimum 1 års erfaring som X\" → returner 1
                - \"Erfaren medarbejder med 3+ års erfaring\" → returner 3",
                    ],
                    [
                        'role' => 'user',
                        'content' => "Analyser følgende job posting beskrivelse og identificer minimum erfaring kravet:\n\nBeskrivelse: {$descriptionPreview}",
                    ],
                ],
                'response_format' => [
                    'type' => 'json_object',
                ],
                'max_tokens' => 100,
                'temperature' => 0,
            ]);

            $content = $response->choices[0]->message->content;
            $data = json_decode($content, true);

            Log::info('OpenAI experience extraction response', [
                'raw_response' => $content,
                'parsed_data' => $data,
            ]);

            if (!isset($data['minimum_experience'])) {
                Log::warning('Ugyldig response struktur fra OpenAI', ['response' => $data]);
                return null;
            }

            // Valider og normaliser værdien
            $experience = $data['minimum_experience'];
            
            if ($experience === null) {
                return null;
            }

            // Konverter til integer hvis det er et tal
            if (is_numeric($experience)) {
                $experience = (int) $experience;
                
                // Valider at det er inden for realistisk område (0-20 år)
                if ($experience >= 0 && $experience <= 20) {
                    return $experience;
                }
            }

            Log::warning('OpenAI returnerede ugyldig experience værdi', [
                'experience' => $experience,
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Fejl ved ekstraktion af minimum experience', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Beregn estimeret pris for experience extraction
     */
    public function estimateCost(int $jobPostingCount): array
    {
        // Estimer tokens per request
        // System prompt: ~400 tokens
        // User prompt: ~50 tokens + description (max 4000 chars ≈ 1000 tokens)
        // Response: ~10 tokens
        
        $systemTokens = 400;
        $userPromptBase = 50;
        $descriptionTokens = 1000;
        $tokensPerRequest = $systemTokens + $userPromptBase + $descriptionTokens;
        $tokensPerResponse = 10;

        // Beregn total tokens
        $totalInputTokens = $jobPostingCount * $tokensPerRequest;
        $totalOutputTokens = $jobPostingCount * $tokensPerResponse;

        $inputCost = ($totalInputTokens / 1000000) * 0.15; // gpt-4o-mini pricing
        $outputCost = ($totalOutputTokens / 1000000) * 0.60;
        $totalCost = $inputCost + $outputCost;

        return [
            'job_posting_count' => $jobPostingCount,
            'estimated_cost_usd' => round($totalCost, 4),
            'estimated_cost_dkk' => round($totalCost * 7, 2),
            'input_tokens' => $totalInputTokens,
            'output_tokens' => $totalOutputTokens,
        ];
    }
}

