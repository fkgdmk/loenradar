<?php

namespace App\Services;

use App\Models\AreaOfResponsibility;
use App\Models\JobTitle;
use App\Models\Payslip;
use App\Models\Region;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class JobTitleExtractor
{
    /**
     * Ekstrahér job titel, sub titel, erfaring, ansvarsområde og region fra payslip
     * 
     * @return array{job_title: JobTitle, sub_job_title: string|null, experience: int|null, area_of_responsibility: AreaOfResponsibility|null, region: Region|null}|null
     */
    public function extractJobTitle(Payslip $payslip): ?array
    {
        if (empty($payslip->title) && empty($payslip->description) && empty($payslip->comments)) {
            Log::warning('Ingen titel, beskrivelse eller kommentarer til at ekstrahere job titel', [
                'payslip_id' => $payslip->id,
            ]);
            return null;
        }

        try {
            $extractedData = $this->extractFromOpenAI(
                $payslip->title, 
                $payslip->description,
                $payslip->comments
            );

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

            // Find region i databasen hvis angivet
            $region = null;
            if (!empty($extractedData['region'])) {
                $region = Region::where('name', $extractedData['region'])->first();
            }

            // Ekstrahér area of responsibility KUN hvis job titel er en leder-rolle
            $areaOfResponsibility = null;
            $leadershipRoles = [
                'Afdelingsleder',
                'Projektleder',
                'Manager',
                'Product Manager',
                'Teamleder / Team Lead',
            ];

            if (in_array($jobTitle->name, $leadershipRoles)) {
                $areaOfResponsibility = $this->extractAreaOfResponsibility(
                    $payslip->title,
                    $payslip->description,
                    $payslip->comments
                );
            }

            Log::info('Job titel ekstraheret succesfuldt', [
                'payslip_id' => $payslip->id,
                'job_title' => $extractedData['job_title'],
                'sub_job_title' => $extractedData['sub_job_title'],
                'experience' => $extractedData['experience'],
                'area_of_responsibility' => $areaOfResponsibility?->name ?? null,
                'region' => $extractedData['region'] ?? null,
                'job_title_id' => $jobTitle->id,
                'area_of_responsibility_id' => $areaOfResponsibility?->id,
                'region_id' => $region?->id,
            ]);

            return [
                'job_title' => $jobTitle,
                'sub_job_title' => $extractedData['sub_job_title'],
                'experience' => $extractedData['experience'],
                'area_of_responsibility' => $areaOfResponsibility,
                'region' => $region,
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
     * Brug OpenAI til at ekstrahere job titel, sub titel, erfaring og region
     * 
     * @param array|null $comments Array af forfatterens kommentarer
     * @return array{job_title: string|null, sub_job_title: string|null, experience: int|null, region: string|null}|null
     */
    private function extractFromOpenAI(?string $title, ?string $description, ?array $comments): ?array
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

        // Hent alle tilladte job titler fra databasen
        $allowedJobTitles = JobTitle::orderBy('name')->pluck('name')->toArray();

        if (empty($allowedJobTitles)) {
            Log::error('Ingen job titler fundet i databasen. Kan ikke ekstrahere job titel.');
            return null;
        }

        // Hent alle regioner
        $allowedRegions = Region::orderBy('name')->pluck('name')->toArray();

        // Byg formaterede lister til prompten
        $jobTitlesList = '- ' . implode("\n- ", $allowedJobTitles);
        $regionsList = '- ' . implode("\n- ", $allowedRegions);

        // Byg user prompt med titel, beskrivelse og kommentarer
        $userPrompt = "Ekstrahér job titel, sub titel, erfaring og region fra følgende forum indlæg:\n\n";
        $userPrompt .= "Titel: {$title}\n";
        
        if (!empty($description)) {
            $userPrompt .= "Beskrivelse: " . mb_substr($description, 0, 500) . "\n";
        }
        
        if (!empty($comments) && is_array($comments)) {
            $userPrompt .= "\nKommentarer fra forfatteren:\n";
            foreach ($comments as $index => $comment) {
                $commentPreview = mb_substr($comment, 0, 300);
                $userPrompt .= ($index + 1) . ". " . $commentPreview . "\n";
            }
        }
        
        $userPrompt .= "\n\nAnalyser teksten nøje og find KUN matches hvis der er TYDELIGE og PRÆCISE matches fra listerne. Hvis jobbet ikke passer til nogen af titlerne på listen, eller hvis du er i tvivl, returner null for det pågældende felt. Gæt IKKE - det er bedre at returnere null end forkerte værdier.";

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Du er en ekspert i at identificere job titler og regioner ud fra et forum indlægs titel, beskrivelse og forfatterens kommentarer. Dit job er at finde de BEDSTE matches fra foruddefinerede lister og ekstrahere yderligere information.

            VIGTIGE REGLER:
            1. Du må KUN returnere værdier der findes PRÆCIST på listerne nedenfor
            2. Hvis ingen match passer GODT, returner null for det pågældende felt
            3. Du må ALDRIG finde på nye værdier eller gætte
            4. Returner ALTID et JSON object: {\"job_title\": \"<titel fra listen eller null>\", \"sub_job_title\": \"<junior/senior/medior/studentermedhjæler/etc eller null>\", \"experience\": <antal år som tal eller null>, \"region\": \"<region fra listen eller null>\", \"confidence\": \"high\"|\"medium\"|\"low\"}
            5. VÆR KONSERVATIV: Det er BEDRE at returnere null end at gætte forkert

            KRITISK: UNDGÅ IT-BIAS
            - Default IKKE til IT-roller medmindre der er TYDELIGE IT-indikatorer
            - Hvis teksten nævner industri, produktion, pharma, byggeri, håndværk, mekanik → Det er IKKE IT
            - Hvis teksten nævner smed, tekniker (uden IT), ingeniør (uden software) → Det er IKKE IT
            - \"Projektleder\" er IKKE det samme som \"IT-Projektleder\" - vælg den IKKE-IT version hvis ingen IT-kontekst
            - \"Projekt\" alene betyder IKKE IT - tjek konteksten nøje

            HVORNÅR SKAL DU RETURNERE NULL:
            - Når jobbet tydeligt er i en branche der ikke er på listen (fx industri, byggeri, håndværk, pharma produktion)
            - Når job titlen er specifik og ikke matcher nogen på listen (fx \"Industritekniker\", \"Projektingeniør\", \"Kvalitetsingeniør\")
            - Når du er i tvivl mellem flere titler
            - Når konteksten indikerer en helt anden type job end dem på listen

            KONTEKST:
            - Du får et forum indlægs titel, beskrivelse og kommentarer
            - Kommentarerne er skrevet af forfatteren selv til deres eget indlæg
            - Kommentarerne kan indeholde vigtig information om job titel, erfaring, senioritet og lokation
            - Brug ALLE tilgængelige informationer (titel, beskrivelse og kommentarer) til at finde de bedste matches

            SUB JOB TITLE:
            - Prøv at identificere senioritetsniveau: \"Junior\", \"Medior\", \"Senior\", \"Lead\", \"Principal\", osv.
            - Hvis ikke nævnt, returner null

            EXPERIENCE (erfaring i år):
            - Prøv at identificere antal års erfaring som et tal (fx 2, 5, 10)
            - Hvis der står \"1 års erfaring\" returner 1
            - Hvis der står \"efter 3 år\" returner 3
            - Hvis ikke nævnt, returner null
            - Hvis der står \"senior\" eller \"erfaren\" uden specifikt tal → returner 5 (standard senior niveau)
            - Hvis der står \"junior\" eller \"nyuddannet\" → returner 0
            - Hvis der står \"mid-level\" eller \"erfaren\" → returner 3

            REGION:
            - Identificer hvilken region personen arbejder i baseret på by eller område nævnt i teksten
            - Returner PRÆCIST et navn fra listen nedenfor eller null
            - Hvis ingen specifik lokation nævnes, returner null
            - Eksempler på byer i regioner:
            * Storkøbenhavn: København, Frederiksberg, Gentofte, Glostrup, Lyngby, Rødovre, osv.
            * Øvrige Sjælland & Øer: Roskilde, Næstved, Køge, Slagelse, Nykøbing F, osv.
            * Fyn: Odense, Svendborg, Nyborg, Middelfart, osv.
            * Østjylland: Aarhus, Randers, Horsens, Skanderborg, Silkeborg, osv.
            * Region Sydjylland: Kolding, Esbjerg, Vejle, Fredericia, Aabenraa, osv.
            * Midt-, Vest- & Nordjylland: Aalborg, Viborg, Herning, Skive, Thisted, Hjørring, osv.

            TILLADTE JOB TITLER:
            {$jobTitlesList}

            TILLADTE REGIONER:
            {$regionsList}",
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

        // Valider region findes i databasen
        $region = null;
        if (isset($data['region']) && !empty($data['region'])) {
            $matchedRegion = collect($allowedRegions)->first(function ($allowed) use ($data) {
                return strcasecmp($allowed, $data['region']) === 0;
            });
            $region = $matchedRegion;
        }

        return [
            'job_title' => $matchedTitle,
            'sub_job_title' => $subJobTitle,
            'experience' => $experience,
            'region' => $region,
        ];
    }

    /**
     * Ekstrahér ansvarsområde for leder-roller
     * 
     * @param array|null $comments Array af forfatterens kommentarer
     * @return AreaOfResponsibility|null
     */
    private function extractAreaOfResponsibility(?string $title, ?string $description, ?array $comments): ?AreaOfResponsibility
    {
        try {
            // Kombiner tekst
            $textParts = array_filter([
                $title ?? '',
                $description ?? '',
            ]);
            
            if (!empty($comments) && is_array($comments)) {
                $textParts[] = implode(' ', $comments);
            }
            
            $text = trim(implode(' ', $textParts));

            if (empty($text)) {
                return null;
            }

            // Hent alle ansvarsområder
            $allowedAreas = AreaOfResponsibility::orderBy('name')->pluck('name')->toArray();
            $areasList = '- ' . implode("\n- ", $allowedAreas);

            // Byg prompt specifik for ansvarsområde ekstraktion
            $userPrompt = "Baseret på følgende information om en leder-position, identificer hvilket ansvarsområde lederen har:\n\n";
            $userPrompt .= "Titel: {$title}\n";
            
            if (!empty($description)) {
                $userPrompt .= "Beskrivelse: " . mb_substr($description, 0, 500) . "\n";
            }
            
            if (!empty($comments) && is_array($comments)) {
                $userPrompt .= "\nKommentarer:\n";
                foreach ($comments as $index => $comment) {
                    $commentPreview = mb_substr($comment, 0, 200);
                    $userPrompt .= ($index + 1) . ". " . $commentPreview . "\n";
                }
            }
            
            $userPrompt .= "\n\nIdentificer hvilket hovedområde denne leder har ansvar for. Returner KUN et ansvarsområde fra listen hvis der er en TYDELIG indikation. Hvis usikker, returner null.";

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "Du er en ekspert i at identificere hvilke ansvarsområder ledere har baseret på job beskrivelser.

VIGTIGE REGLER:
1. Du må KUN returnere et ansvarsområde der findes PRÆCIST på listen nedenfor
2. Hvis ingen passer GODT eller du er i tvivl, returner {\"area_of_responsibility\": null}
3. Du må ALDRIG finde på nye ansvarsområder
4. Returner ALTID et JSON object: {\"area_of_responsibility\": \"<område fra listen eller null>\"}
5. VÆR KONSERVATIV: Det er bedre at returnere null end at gætte forkert

GUIDELINES FOR IDENTIFIKATION:
- Kig efter nøgleord i titel, beskrivelse og kommentarer
- \"IT\", \"teknologi\", \"software\", \"system\" → \"IT & Teknologi\"
- \"salg\", \"marketing\", \"commercial\", \"business development\" → \"Salg & Marketing\"
- \"økonomi\", \"finans\", \"regnskab\", \"budget\" → \"Finans & Økonomi\"
- \"HR\", \"personale\", \"rekruttering\", \"administration\" → \"HR & Administration\"
- \"produktion\", \"logistik\", \"supply chain\", \"operations\", \"manufacturing\" → \"Produktion & Logistik\"
- \"kundeservice\", \"support\", \"kunde\", \"service\" → \"Kundeservice & Support\"
- Hvis ingen af ovenstående passer eller du er usikker → \"Andet\"
- Hvis INGEN indikation → null

TILLADTE ANSVARSOMRÅDER:
{$areasList}",
                    ],
                    [
                        'role' => 'user',
                        'content' => $userPrompt,
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

            if (!isset($data['area_of_responsibility']) || $data['area_of_responsibility'] === null) {
                return null;
            }

            // Valider at området findes i databasen
            $matchedArea = collect($allowedAreas)->first(function ($allowed) use ($data) {
                return strcasecmp($allowed, $data['area_of_responsibility']) === 0;
            });

            if (!$matchedArea) {
                return null;
            }

            // Find AreaOfResponsibility model
            return AreaOfResponsibility::where('name', $matchedArea)->first();

        } catch (\Exception $e) {
            Log::warning('Fejl ved ekstraktion af ansvarsområde', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
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
     * 
     * Note: Dette er et estimat. Leder-roller (ca. 15% af posts) vil have et ekstra API call
     * for ansvarsområde ekstraktion, hvilket øger prisen lidt.
     */
    public function estimateCost(int $payslipCount): array
    {
        // Hovedekstraktion: job title, sub title, experience, region
        $tokensPerRequest = 450; // System + user prompt + text (inkl. kommentarer, regions)
        $tokensPerResponse = 100; // JSON response med job_title, sub_job_title, experience, region

        // Ansvarsområde ekstraktion (kun ~15% af payslips - leder-roller)
        $leadershipPercentage = 0.15;
        $leadershipPayslips = (int) ($payslipCount * $leadershipPercentage);
        $areaTokensPerRequest = 250; // Separat prompt for ansvarsområde
        $areaTokensPerResponse = 30; // JSON med bare area_of_responsibility

        // Beregn total tokens
        $totalInputTokens = ($payslipCount * $tokensPerRequest) + ($leadershipPayslips * $areaTokensPerRequest);
        $totalOutputTokens = ($payslipCount * $tokensPerResponse) + ($leadershipPayslips * $areaTokensPerResponse);

        $inputCost = ($totalInputTokens / 1000000) * 0.15; // gpt-4o-mini pricing
        $outputCost = ($totalOutputTokens / 1000000) * 0.60;
        $totalCost = $inputCost + $outputCost;

        return [
            'payslip_count' => $payslipCount,
            'estimated_leadership_roles' => $leadershipPayslips,
            'estimated_cost_usd' => round($totalCost, 4),
            'estimated_cost_dkk' => round($totalCost * 7, 2),
            'input_tokens' => $totalInputTokens,
            'output_tokens' => $totalOutputTokens,
        ];
    }
}

