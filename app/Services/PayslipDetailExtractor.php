<?php

namespace App\Services;

use App\Models\Payslip;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PayslipDetailExtractor
{
    /**
     * Analyse payslip and extract detailed information
     */
    public function extractDetails(Payslip $payslip): ?array
    {
        $media = $payslip->getFirstMedia('documents');
        
        if (!$media) {
            Log::warning('Ingen media fundet for payslip', ['payslip_id' => $payslip->id]);
            return null;
        }

        if (!$this->isImageFile($media)) {
            Log::warning('Media er ikke et billede', [
                'payslip_id' => $payslip->id,
                'mime_type' => $media->mime_type,
            ]);
            return null;
        }

        try {
            $imageBase64 = $this->getImageBase64($media);
            
            if (!$imageBase64) {
                return null;
            }

            $details = $this->extractDetailsFromImage($imageBase64, $media->mime_type);
            
            if ($details) {
                // Filter out null values to avoid overwriting with null if we want to keep existing data
                // But requirement says: "opdater de fundne resultater".
                // The command will call this service.
                // Since the command targets payslips where these are null, it's safe to update.
                
                $updateData = [];
                if (isset($details['company_pension_dkk'])) $updateData['company_pension_dkk'] = $details['company_pension_dkk'];
                if (isset($details['company_pension_procent'])) $updateData['company_pension_procent'] = $details['company_pension_procent'];
                if (isset($details['salary_supplement'])) $updateData['salary_supplement'] = $details['salary_supplement'];
                if (isset($details['hours_monthly'])) $updateData['hours_monthly'] = $details['hours_monthly'];

                if (!empty($updateData)) {
                    $payslip->update($updateData);
                    
                    Log::info('Payslip detaljer opdateret', [
                        'payslip_id' => $payslip->id,
                        'data' => $updateData,
                    ]);
                }
            }

            return $details;

        } catch (\Exception $e) {
            Log::error('Fejl ved ekstrahering af detaljer', [
                'payslip_id' => $payslip->id,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    private function extractDetailsFromImage(string $imageBase64, string $mimeType): ?array
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Du er en ekspert i dansk lønadministration. Din opgave er at analysere en lønseddel og udtrække specifikke datapunkter med ekstrem nøjagtighed.

                Du skal finde følgende 3 værdier:
                1. FIRMAPENSION (Arbejdsgivers bidrag)
                2. FASTE TILLÆG (Salary supplement)
                3. MÅNEDLIGE TIMER (Hours monthly)

                Brug følgende "Chain of Thought" proces før du svarer:

                TRIN 1: PENSION ANALYSE
                - Scan dokumentet for ordet "Pension", "Arbejdsgiverbidrag", "Firmabidrag", "Pension (firma)", "Arbejdsgiver".
                - Identificer linjer der vedrører pension.
                - SKELN skarpt mellem "Eget bidrag" (medarbejderens andel) og "Firma/Arbejdsgiver bidrag".
                - Vi leder UDELUKKENDE efter firmaets/arbejdsgiverens bidrag.
                - Hvis beløbet er angivet i DKK, noter det som "company_pension_dkk".
                - Hvis en procentsats er angivet for arbejdsgiveren (fx 8%, 10%), noter det som "company_pension_procent".
                - Hvis både beløb og procent findes, returner begge.
                - Hvis KUN eget bidrag findes, returner NULL for firma pension.

                TRIN 2: TILLÆG ANALYSE (Salary Supplement)
                - Scan for tillæg udover grundlønnen.
                - Inkluder KUN FASTE tillæg (fx "Fritvalgstillæg", "Kompetencetillæg", "Funktionstillæg", "Rådighedstillæg", "Skifteholdstillæg" (hvis fast)).
                - EKSKLUDER ALTID: "Overtid", "Bonus", "Feriepenge", "Efterregulering", "Kørselsgodtgørelse", "Udbetalt afspadsering".
                - Summer alle faste tillæg til én total.
                - Hvis ingen faste tillæg findes, returner NULL (eller 0 hvis der tydeligvis ingen er, men foretræk NULL ved tvivl).

                TRIN 3: TIMER ANALYSE (Hours Monthly)
                - Find det antal timer lønnen er baseret på.
                - Kig efter "Normtid", "Timer", "Beskæftigelsesgrad", "Arbejdstimer", "Sats".
                - Det er typisk 160.33 for fuldtid, eller 37 timer/uge.
                - Hvis der står "160,33", rund ned/op til nærmeste heltal (160).
                - Hvis der står "37 timer/uge", omregn til måned (37 * 4.33 = 160).
                - Hvis intet kan findes, returner NULL.

                FORMAT:
                Returner svaret som et JSON objekt. Du MÅ inkludere et felt "_thought_process" med din tænkning, men de endelige data skal være i nøglerne:
                - company_pension_dkk (int eller null)
                - company_pension_procent (decimal/float eller null)
                - salary_supplement (int eller null)
                - hours_monthly (int eller null)',
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Analyser denne lønseddel og find firma pension (DKK og/eller %), faste tillæg, og månedlige timer. Returner JSON.',
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => "data:{$mimeType};base64,{$imageBase64}",
                                'detail' => 'high',
                            ],
                        ],
                    ],
                ],
            ],
            'response_format' => [
                'type' => 'json_object',
            ],
            'max_tokens' => 1000,
            'temperature' => 0,
        ]);

        $content = $response->choices[0]->message->content;
        $data = json_decode($content, true);

        Log::info('OpenAI detail extraction response', [
            'parsed_data' => $data,
        ]);

        // Map og validér data
        $result = [];

        // Pension DKK
        if (isset($data['company_pension_dkk']) && is_numeric($data['company_pension_dkk'])) {
            $result['company_pension_dkk'] = (int) $data['company_pension_dkk'];
        }

        // Pension Procent
        if (isset($data['company_pension_procent']) && is_numeric($data['company_pension_procent'])) {
            $result['company_pension_procent'] = (float) $data['company_pension_procent'];
        }

        // Supplement
        if (isset($data['salary_supplement']) && is_numeric($data['salary_supplement'])) {
            $result['salary_supplement'] = (int) $data['salary_supplement'];
        }

        // Hours
        if (isset($data['hours_monthly']) && is_numeric($data['hours_monthly'])) {
            $result['hours_monthly'] = (int) $data['hours_monthly'];
        }

        return $result;
    }

    private function getImageBase64(Media $media): ?string
    {
        try {
            $path = $media->getPath();
            
            if (!file_exists($path)) {
                Log::error('Billedfil findes ikke', ['path' => $path]);
                return null;
            }

            if (filesize($path) > 20 * 1024 * 1024) {
                return null;
            }

            $imageData = file_get_contents($path);
            return base64_encode($imageData);

        } catch (\Exception $e) {
            Log::error('Fejl ved konvertering til base64', ['error' => $e->getMessage()]);
            return null;
        }
    }

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
}

