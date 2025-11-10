<?php

namespace App\Services;

use App\Models\Payslip;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PayslipAnalyzer
{
    /**
     * Analyse lønseddel og udtræk løn information
     * 
     * Bruger OpenAI Vision API til at læse lønsedler og ekstrahere grundløn/basisløn
     * (fast månedsløn uden tillæg som overtid, bonus, pension osv.)
     */
    public function analyzeSalary(Payslip $payslip): ?float
    {
        // Hent første billede/dokument
        $media = $payslip->getFirstMedia('documents');
        
        if (!$media) {
            Log::warning('Ingen media fundet for payslip', ['payslip_id' => $payslip->id]);
            return null;
        }

        // Tjek om det er et billede
        if (!$this->isImageFile($media)) {
            Log::warning('Media er ikke et billede', [
                'payslip_id' => $payslip->id,
                'mime_type' => $media->mime_type,
            ]);
            return null;
        }

        try {
            // Konverter billedet til base64
            $imageBase64 = $this->getImageBase64($media);
            
            if (!$imageBase64) {
                return null;
            }

            // Kald OpenAI Vision API
            $salary = $this->extractSalaryFromImage($imageBase64, $media->mime_type);
            
            if ($salary) {
                // Opdater payslip med løn
                $payslip->update(['salary' => $salary]);
                
                Log::info('Løn udtrukket succesfuldt', [
                    'payslip_id' => $payslip->id,
                    'salary' => $salary,
                ]);
            }

            return $salary;

        } catch (\Exception $e) {
            Log::error('Fejl ved analyse af lønseddel', [
                'payslip_id' => $payslip->id,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Udtræk grundløn fra billede ved hjælp af OpenAI Vision
     * 
     * Finder KUN basisløn/grundløn - ignorerer tillæg, bonus, pension osv.
     */
    private function extractSalaryFromImage(string $imageBase64, string $mimeType): ?float
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o', // Billigere model - perfekt til struktureret data
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Du er en ekspert i at læse danske lønsedler. Dit job er at finde og returnere KUN grundlønnen/basislønnen (fast månedsløn uden tillæg). Returner ALTID et JSON object med følgende struktur: {"salary": <nummer>, "confidence": <"high"|"medium"|"low">, "currency": "DKK"}. Hvis du ikke kan finde en løn, returner {"salary": null, "confidence": "low", "currency": "DKK"}. Brug kun tal uden formatering (fx 45000.50 ikke 45.000,50).',
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Find KUN grundlønnen/basislønnen fra denne danske lønseddel. Se efter felter med følgende navne (dansk eller engelsk):

DANSKE TERMER: "Grundløn", "Basisløn", "Fast løn", "Månedsløn", "Fastløn", "Gage", "Løn", "Bruttoløn" (hvis ingen tillæg), "Timeløn" (gang timer), "Normaltimer", "Normal løn".

ENGELSKE TERMER: "Basic salary", "Base salary", "Base pay", "Monthly salary", "Gross salary", "Salary", "Wage", "Pay".

IGNORER ALTID: Overtid, overtidstillæg, bonus, pension, feriepenge, ATP, tillæg, udbetalt i alt, total, netto.

Find KUN den faste månedsløn/gage uden nogen form for tillæg. Returner beløbet i DKK som et tal.',
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => "data:{$mimeType};base64,{$imageBase64}",
                                'detail' => 'high', // 'low' er billigere og hurtigere - perfekt til tekst
                            ],
                        ],
                    ],
                ],
            ],
            'response_format' => [
                'type' => 'json_object',
            ],
            'max_tokens' => 800, // Begrænser token forbrug
            'temperature' => 0, // Deterministisk output for konsistens
        ]);

        $content = $response->choices[0]->message->content;
        $data = json_decode($content, true);

        Log::info('OpenAI response', [
            'raw_response' => $content,
            'parsed_data' => $data,
        ]);

        // Validér response
        if (!isset($data['salary']) || $data['salary'] === null) {
            Log::warning('Ingen løn fundet i billedet', ['response' => $data]);
            return null;
        }

        // Validér at det er et tal og er rimeligt
        $salary = (float) $data['salary'];
        
        if ($salary <= 0 || $salary > 10000000) {
            Log::warning('Løn er uden for forventet interval', [
                'salary' => $salary,
                'response' => $data,
            ]);
            return null;
        }

        return $salary;
    }

    /**
     * Konverter billede til base64
     */
    private function getImageBase64(Media $media): ?string
    {
        try {
            $path = $media->getPath();
            
            if (!file_exists($path)) {
                Log::error('Billedfil findes ikke', ['path' => $path]);
                return null;
            }

            // Tjek filstørrelse - OpenAI har en 20MB grænse
            $fileSizeInMB = filesize($path) / 1024 / 1024;
            
            if ($fileSizeInMB > 20) {
                Log::warning('Billedfil er for stor', [
                    'path' => $path,
                    'size_mb' => $fileSizeInMB,
                ]);
                return null;
            }

            $imageData = file_get_contents($path);
            return base64_encode($imageData);

        } catch (\Exception $e) {
            Log::error('Fejl ved konvertering til base64', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Tjek om media er et billede
     */
    private function isImageFile(Media $media): bool
    {
        $allowedMimeTypes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
        ];

        return in_array($media->mime_type, $allowedMimeTypes);
    }

    /**
     * Beregn estimeret pris for analyse
     * 
     * gpt-4o-mini pricing (November 2024):
     * - Input: $0.15 / 1M tokens
     * - Output: $0.60 / 1M tokens
     * - Images (low detail): ~85 tokens
     * 
     * Estimeret pris per billede: ~$0.0001 (0.1 cent)
     */
    public function estimateCost(int $imageCount): array
    {
        $tokensPerImage = 85; // Low detail
        $tokensPerPrompt = 200; // System + user prompt
        $tokensPerResponse = 50; // JSON response
        
        $totalInputTokens = $imageCount * ($tokensPerImage + $tokensPerPrompt);
        $totalOutputTokens = $imageCount * $tokensPerResponse;
        
        $inputCost = ($totalInputTokens / 1000000) * 0.15;
        $outputCost = ($totalOutputTokens / 1000000) * 0.60;
        $totalCost = $inputCost + $outputCost;

        return [
            'image_count' => $imageCount,
            'estimated_cost_usd' => round($totalCost, 4),
            'estimated_cost_dkk' => round($totalCost * 7, 2), // Approx exchange rate
            'input_tokens' => $totalInputTokens,
            'output_tokens' => $totalOutputTokens,
        ];
    }
}

