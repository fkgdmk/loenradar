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
     * Analyser payslip tekst og udtræk løn og erfaring
     * 
     * Bruger OpenAI Chat API til at analysere titel, beskrivelse og kommentarer
     * og ekstrahere grundløn/basisløn og erfaring
     */
    public function analyzeSalaryFromText(Payslip $payslip): ?array
    {
        if (empty($payslip->title) && empty($payslip->description) && empty($payslip->comments)) {
            Log::warning('Ingen titel, beskrivelse eller kommentarer til at analysere', [
                'payslip_id' => $payslip->id,
            ]);
            return null;
        }

        try {
            $extractedData = $this->extractSalaryFromText(
                $payslip->title,
                $payslip->description,
                $payslip->comments
            );

            if (!$extractedData) {
                $payslip->markAsDenied();
                return null;
            }

            $updateData = [];

            // Opdater løn hvis fundet
            if (isset($extractedData['salary']) && $extractedData['salary'] !== null) {
                $updateData['salary'] = $extractedData['salary'];
            } else {
                $payslip->markAsDenied();
            }

            // Opdater erfaring hvis fundet og payslip ikke allerede har en
            if (isset($extractedData['experience']) && $extractedData['experience'] !== null && $payslip->experience === null) {
                $updateData['experience'] = $extractedData['experience'];
            }

            if (!empty($updateData)) {
                $payslip->update($updateData);
                
                Log::info('Løn og erfaring udtrukket fra tekst', [
                    'payslip_id' => $payslip->id,
                    'salary' => $extractedData['salary'] ?? null,
                    'experience' => $extractedData['experience'] ?? null,
                ]);
            }

            return $extractedData;

        } catch (\Exception $e) {
            Log::error('Fejl ved analyse af tekst', [
                'payslip_id' => $payslip->id,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Udtræk grundløn og erfaring fra tekst ved hjælp af OpenAI Chat API
     * 
     * Finder KUN basisløn/grundløn - ignorerer tillæg, bonus, pension osv.
     * VIGTIGT: Gætter IKKE - kun data der eksplicit står i teksten
     */
    private function extractSalaryFromText(?string $title, ?string $description, ?array $comments): ?array
    {
        // Kombiner titel, beskrivelse og kommentarer
        $textParts = array_filter([
            $title ?? '',
            $description ?? '',
        ]);
        
        // Tilføj kommentarer hvis de findes
        if (!empty($comments) && is_array($comments)) {
            $textParts[] = implode(' ', $comments);
        }
        
        $text = trim(implode(' ', $textParts));

        if (empty($text)) {
            return null;
        }

        // Byg user prompt med titel, beskrivelse og kommentarer
        $userPrompt = "Analyser følgende tekst og find KUN løn og erfaring hvis de eksplicit nævnes:\n\n";
        $userPrompt .= "Titel: {$title}\n";
        
        if (!empty($description)) {
            $userPrompt .= "Beskrivelse: " . mb_substr($description, 0, 1000) . "\n";
        }
        
        if (!empty($comments) && is_array($comments)) {
            $userPrompt .= "\nKommentarer fra forfatteren:\n";
            foreach ($comments as $index => $comment) {
                $commentPreview = mb_substr($comment, 0, 500);
                $userPrompt .= ($index + 1) . ". " . $commentPreview . "\n";
            }
        }
        
        $userPrompt .= "\n\nVIGTIGT: Du må KUN returnere løn og erfaring hvis de eksplicit står i teksten. Gæt IKKE og find IKKE på tal. Hvis løn eller erfaring ikke nævnes tydeligt, returner null for det pågældende felt.";

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Du er en ekspert i at analysere danske lønseddel-indlæg fra fora. Dit job er at finde og returnere KUN grundlønnen/basislønnen (fast månedsløn uden tillæg) og erfaring hvis de eksplicit nævnes i teksten.

KRITISK: Du må ALDRIG gætte eller finde på tal. Du må KUN returnere værdier der eksplicit står i teksten.

Returner ALTID et JSON object med følgende struktur: {"salary": <nummer eller null>, "experience": <antal år som tal eller null>, "confidence": <"high"|"medium"|"low">, "currency": "DKK"}.

LØN (salary):
- Find KUN grundlønnen/basislønnen (fast månedsløn uden tillæg)
- Se efter eksplicitte nævnelser af: "Grundløn", "Basisløn", "Fast løn", "Månedsløn", "Fastløn", "Gage", "Løn", "Bruttoløn" (hvis ingen tillæg), "Timeløn" (gang timer), "Normaltimer", "Normal løn"
- Eller engelske termer: "Basic salary", "Base salary", "Base pay", "Monthly salary", "Gross salary", "Salary", "Wage", "Pay"
- IGNORER ALTID: Overtid, overtidstillæg, bonus, pension, feriepenge, ATP, tillæg, udbetalt i alt, total, netto
- Hvis lønnen nævnes som timeløn, gang med antal timer (fx "200 kr/timen, 37 timer/ugen" = 200 * 37 * 4.33 = 32.042 DKK)
- Brug kun tal uden formatering (fx 45000.50 ikke 45.000,50)
- Hvis løn IKKE eksplicit nævnes, returner null

ERFARING (experience):
- Find antal års erfaring hvis det eksplicit nævnes
- Se efter: "X års erfaring", "efter X år", "X år i branchen", "X års erfaring", osv.
- Returner som et heltal (fx 2, 5, 10)
- Hvis erfaring IKKE eksplicit nævnes, returner null

CONFIDENCE:
- "high": Løn/erfaring står tydeligt og eksplicit i teksten
- "medium": Løn/erfaring kan udledes men kræver lidt fortolkning
- "low": Løn/erfaring er usikker eller ikke nævnt

HUSK: Det er BEDRE at returnere null end at gætte forkert!',
                ],
                [
                    'role' => 'user',
                    'content' => $userPrompt,
                ],
            ],
            'response_format' => [
                'type' => 'json_object',
            ],
            'max_tokens' => 200,
            'temperature' => 0, // Deterministisk output for konsistens
        ]);

        $content = $response->choices[0]->message->content;
        $data = json_decode($content, true);

        Log::info('OpenAI tekst analyse response', [
            'raw_response' => $content,
            'parsed_data' => $data,
        ]);

        // Validér response struktur
        if (!is_array($data)) {
            Log::warning('Ugyldig response struktur', ['response' => $data]);
            return null;
        }

        $result = [
            'salary' => null,
            'experience' => null,
        ];

        // Validér og ekstraher løn
        if (isset($data['salary']) && $data['salary'] !== null) {
            $salary = (float) $data['salary'];
            
            // Validér at løn er rimelig
            if ($salary > 0 && $salary <= 10000000) {
                $result['salary'] = $salary;
            } else {
                Log::warning('Løn er uden for forventet interval', [
                    'salary' => $salary,
                    'response' => $data,
                ]);
            }
        }

        // Validér og ekstraher erfaring
        if (isset($data['experience']) && $data['experience'] !== null && is_numeric($data['experience'])) {
            $experience = (int) $data['experience'];
            
            // Validér at erfaring er rimelig (0-50 år)
            if ($experience >= 0 && $experience <= 50) {
                $result['experience'] = $experience;
            } else {
                Log::warning('Erfaring er uden for forventet interval', [
                    'experience' => $experience,
                    'response' => $data,
                ]);
            }
        }

        // Returner kun hvis mindst én værdi blev fundet
        if ($result['salary'] === null && $result['experience'] === null) {
            Log::info('Ingen løn eller erfaring fundet i teksten', ['response' => $data]);
            return null;
        }

        return $result;
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

    /**
     * Beregn estimeret pris for tekstanalyse
     * 
     * gpt-4o-mini pricing (November 2024):
     * - Input: $0.15 / 1M tokens
     * - Output: $0.60 / 1M tokens
     */
    public function estimateTextAnalysisCost(int $payslipCount): array
    {
        $tokensPerPrompt = 600; // System + user prompt med titel, beskrivelse og kommentarer
        $tokensPerResponse = 100; // JSON response
        
        $totalInputTokens = $payslipCount * $tokensPerPrompt;
        $totalOutputTokens = $payslipCount * $tokensPerResponse;
        
        $inputCost = ($totalInputTokens / 1000000) * 0.15;
        $outputCost = ($totalOutputTokens / 1000000) * 0.60;
        $totalCost = $inputCost + $outputCost;

        return [
            'payslip_count' => $payslipCount,
            'estimated_cost_usd' => round($totalCost, 4),
            'estimated_cost_dkk' => round($totalCost * 7, 2), // Approx exchange rate
            'input_tokens' => $totalInputTokens,
            'output_tokens' => $totalOutputTokens,
        ];
    }
}

