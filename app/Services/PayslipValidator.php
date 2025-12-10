<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PayslipValidator
{
    /**
     * Valider og analyser lønseddel billede
     * 
     * Returnerer array med:
     * - is_payslip: boolean (vigtigste - er det en lønseddel?)
     * - salary: float|null (kun hvis high confidence)
     * - payslip_date: string|null (kun hvis high confidence, format: Y-m-d)
     */
    public function validateAndAnalyze(Media $media): array
    {
        try {
            // Konverter billedet til base64
            $imageBase64 = $this->getImageBase64($media);

            if (!$imageBase64) {
                return [
                    'is_payslip' => false,
                    'salary' => null,
                    'payslip_date' => null,
                ];
            }

            // Kald OpenAI Vision API
            $result = $this->extractDataFromImage($imageBase64, $media->mime_type);
            
            return $result;

        } catch (\Exception $e) {
            Log::error('Fejl ved validering af lønseddel', [
                'error' => $e->getMessage(),
                'file_name' => $media->file_name,
            ]);
            
            return [
                'is_payslip' => false,
                'salary' => null,
                'payslip_date' => null,
            ];
        }
    }

    /**
     * Udtræk data fra billede ved hjælp af OpenAI Vision
     * 
     * Prioritering: is_payslip > salary > payslip_date
     */
    private function extractDataFromImage(string $imageBase64, string $mimeType): array
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Du er en ekspert i at læse danske lønsedler. Dit job er at:
                1. FØRST: Bestemme om billedet er en lønseddel (is_payslip)
                2. DEREFTER: Hvis det er en lønseddel, find grundlønnen/basislønnen (fast månedsløn uden tillæg)
                3. TIL SLUT: Hvis det er en lønseddel, find oprettelsesdatoen for lønseddelen

                Returner ALTID et JSON object med følgende struktur:
                {
                "is_payslip": <boolean>,
                "salary": <nummer eller null>,
                "salary_confidence": <"high"|"medium"|"low">,
                "payslip_date": <"Y-m-d" format eller null>,
                "date_confidence": <"high"|"medium"|"low">,
                "currency": "DKK"
                }

                VIGTIGT:
                - is_payslip skal være true KUN hvis du er sikker på at det er en lønseddel
                - salary skal kun returneres hvis confidence er "high" (ellers null)
                - payslip_date skal kun returneres hvis confidence er "high" (ellers null)
                - Brug kun tal uden formatering for salary (fx 45000.50 ikke 45.000,50)
                - Dato skal være i formatet Y-m-d (fx 2024-01-15)',
                                ],
                                [
                                    'role' => 'user',
                                    'content' => [
                                        [
                                            'type' => 'text',
                                            'text' => 'Analyser dette billede og bestem:

                1. ER DETTE EN LØNSEDDEL? (is_payslip)
                - Tjek om billedet indeholder typiske lønseddel-elementer: lønbeløb, periode, firmanavn, medarbejdernavn, osv.
                - Hvis du er i tvivl, sæt is_payslip til false

                2. Hvis det ER en lønseddel, find GRUNDLØNEN/BASISLØNNEN (salary):
                - Find KUN grundlønnen/basislønnen (fast månedsløn uden tillæg)
                - Se efter felter med følgende navne (dansk eller engelsk):
                
                DANSKE TERMER: "Grundløn", "Basisløn", "Fast løn", "Månedsløn", "Fastløn", "Gage", "Løn", "Bruttoløn" (hvis ingen tillæg), "Timeløn" (gang timer), "Normaltimer", "Normal løn".
                
                ENGELSKE TERMER: "Basic salary", "Base salary", "Base pay", "Monthly salary", "Gross salary", "Salary", "Wage", "Pay".
                
                IGNORER ALTID: Overtid, overtidstillæg, bonus, pension, feriepenge, ATP, tillæg, udbetalt i alt, total, netto.
                
                - Find KUN den faste månedsløn/gage uden nogen form for tillæg
                - Returner beløbet i DKK som et tal
                - Sæt salary_confidence til "high" KUN hvis du er helt sikker på at det er grundlønnen og ikke et tillæg
                - Hvis du er i tvivl, sæt salary til null og salary_confidence til "low"

                3. Hvis det ER en lønseddel, find OPRETTELSESDATOEN (payslip_date):
                - Find datoen for hvornår lønseddelen er lavet/udstedt
                - Se efter: "Periode", "Udbetalingsdato", "Dato", "Date", "Period", osv.
                - Returner datoen i formatet Y-m-d (fx 2024-01-15)
                - Sæt date_confidence til "high" KUN hvis du er helt sikker på at det er oprettelsesdatoen
                - Hvis du er i tvivl, sæt payslip_date til null og date_confidence til "low"',
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

        Log::info('PayslipValidator OpenAI response', [
            'raw_response' => $content,
            'parsed_data' => $data,
        ]);

        // Validér og ekstraher data
        $isPayslip = isset($data['is_payslip']) && $data['is_payslip'] === true;
        
        $salary = null;
        if ($isPayslip && isset($data['salary']) && $data['salary'] !== null) {
            $salaryConfidence = $data['salary_confidence'] ?? 'low';
            
            // Kun returner salary hvis confidence er "high"
            if ($salaryConfidence === 'high') {
                $salaryValue = (float) $data['salary'];
                
                // Validér at løn er rimelig
                if ($salaryValue > 0 && $salaryValue <= 10000000) {
                    $salary = $salaryValue;
                } else {
                    Log::warning('Løn er uden for forventet interval', [
                        'salary' => $salaryValue,
                        'response' => $data,
                    ]);
                }
            }
        }

        $payslipDate = null;
        if ($isPayslip && isset($data['payslip_date']) && $data['payslip_date'] !== null) {
            $dateConfidence = $data['date_confidence'] ?? 'low';
            
            // Kun returner dato hvis confidence er "high"
            if ($dateConfidence === 'high') {
                $dateValue = $data['payslip_date'];
                
                // Validér dato format
                if ($this->isValidDate($dateValue)) {
                    $payslipDate = $dateValue;
                } else {
                    Log::warning('Ugyldigt dato format', [
                        'date' => $dateValue,
                        'response' => $data,
                    ]);
                }
            }
        }

        return [
            'is_payslip' => $isPayslip,
            'salary' => $salary,
            'payslip_date' => $payslipDate,
        ];
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
     * Validér at dato er i korrekt format (Y-m-d)
     */
    private function isValidDate(?string $date): bool
    {
        if (!$date) {
            return false;
        }

        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
