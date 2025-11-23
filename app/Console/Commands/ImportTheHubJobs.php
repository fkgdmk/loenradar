<?php

namespace App\Console\Commands;

use App\Models\JobPosting;
use App\Models\JobTitle;
use App\Models\Region;
use App\Models\Skill;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use DOMDocument;
use DOMXPath;

class ImportTheHubJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'thehub:import-jobs
                            {url? : URL til et specifikt job (fx https://thehub.io/jobs/69174b99ec344369a26bd8e3)}
                            {--delay=3 : Sekunder mellem requests (rate limiting)}
                            {--limit=50 : Maksimalt antal jobs der skal importeres}
                            {--dry-run : Vis jobs uden at gemme dem}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importer danske jobs fra thehub.io med salary information';

    /**
     * Base URL for The Hub jobs
     */
    private const BASE_URL = 'https://thehub.io';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $url = $this->argument('url');
        $delay = (int) $this->option('delay');
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        // Hvis URL er angivet, behandle kun det specifikke job
        if ($url) {
            return $this->handleSingleJob($url, $dryRun);
        }

        $this->info('Starter import af danske jobs fra thehub.io...');
        $this->info("Delay mellem requests: {$delay} sekunder");
        $this->info("Maksimalt antal jobs: {$limit}");
        $this->info("Paginering: Automatisk (fortsætter indtil der ikke er flere jobs)");
        
        if ($dryRun) {
            $this->warn('DRY RUN mode - ingen jobs vil blive gemt');
        }

        try {

            $jobTitles = [
                'Backend Udvikler' => 'https://thehub.io/jobs?roles=backenddeveloper&countryCode=DK&sorting=newJobs&positionTypes=5b8e46b3853f039706b6ea70',
                'Data Scientist' => 'https://thehub.io/jobs?roles=datascience&countryCode=DK&sorting=newJobs&positionTypes=5b8e46b3853f039706b6ea70',
                'DevOps Engineer' => 'https://thehub.io/jobs?roles=devops&countryCode=DK&sorting=newJobs&positionTypes=5b8e46b3853f039706b6ea70',
                'Frontend Udvikler' => 'https://thehub.io/jobs?roles=frontenddeveloper&countryCode=DK&sorting=newJobs&positionTypes=5b8e46b3853f039706b6ea70',
                'Full-stack Udvikler' => 'https://thehub.io/jobs?roles=fullstackdeveloper&countryCode=DK&sorting=newJobs&positionTypes=5b8e46b3853f039706b6ea70',
                'UX/UI Designer' => 'https://thehub.io/jobs?positionTypes=5b8e46b3853f039706b6ea70&roles=uxuidesigner&countryCode=DK&sorting=newJobs',
                'Product Manager' => 'https://thehub.io/jobs?positionTypes=5b8e46b3853f039706b6ea70&roles=productmanagement&countryCode=DK&sorting=newJobs',
                'Data Analyst' => 'https://thehub.io/jobs?positionTypes=5b8e46b3853f039706b6ea70&roles=analyst&countryCode=DK&sorting=newJobs',
                'Projektleder' => 'https://thehub.io/jobs?positionTypes=5b8e46b3853f039706b6ea70&roles=projectmanagement&countryCode=DK&sorting=newJobs',
                'Softwareudvikler' => 'https://thehub.io/jobs?positionTypes=5b8e46b3853f039706b6ea70&roles=engineer&countryCode=DK&sorting=newJobs',
                'Softwareudvikler' => 'https://thehub.io/jobs?positionTypes=5b8e46b3853f039706b6ea70&search=software%20developer&countryCode=DK&sorting=newJobs',
                'Marketingkoordinator' => 'https://thehub.io/jobs?positionTypes=5b8e46b3853f039706b6ea70&roles=marketing&search=Manager&countryCode=DK&sorting=newJobs',
            ];

            foreach ($jobTitles as $jobTitle => $baseUrl) {
                $jobUrls = $this->fetchJobUrlsWithPagination($baseUrl, $limit, $delay);
                
                if (empty($jobUrls)) {
                    $this->warn('Ingen job URLs fundet');
                    return Command::SUCCESS;
                }
    
                $this->info("Fundet " . count($jobUrls) . " job URLs");
                $this->newLine();
    
                $imported = 0;
                $skipped = 0;
                $errors = 0;
    
                foreach ($jobUrls as $index => $jobUrl) {
                    $this->line("─────────────────────────────────────────────────────");
                    $this->info("Processing job " . ($index + 1) . "/" . count($jobUrls) . ": {$jobUrl}");
    
                    // Rate limiting
                    if ($index > 0) {
                        $this->comment("Venter {$delay} sekunder...");
                        sleep($delay);
                    }
    
                    try {
                        $jobData = $this->fetchJobDetails($jobUrl);

                        $jobData['job_title_id'] = JobTitle::where('name', $jobTitle)->first()?->id;
    
                        if (!$jobData) {
                            $this->warn("  ⚠️  Kunne ikke hente job data");
                            $errors++;
                            continue;
                        }
    
                        // Tjek om salary er tilgængelig
                        if (!$this->hasSalary($jobData)) {
                            $this->warn("  ⚠️  Ingen salary information - springer over");
                            $skipped++;
                            continue;
                        }
    
                        // Vis job data
                        $this->displayJobData($jobData);
    
                        if ($dryRun) {
                            $this->comment("  [DRY RUN] Ville gemme dette job");
                            $imported++;
                            continue;
                        }
    
                        // Gem jobbet
                        $saved = $this->saveJob($jobData);
                        
                        if ($saved) {
                            $this->info("  ✅ Job gemt succesfuldt");
                            $imported++;
                        } else {
                            $this->warn("  ⚠️  Job blev ikke gemt (måske allerede eksisterer)");
                            $skipped++;
                        }
    
                    } catch (\Exception $e) {
                        $this->error("  ❌ Fejl: " . $e->getMessage());
                        Log::error('Fejl ved import af job', [
                            'url' => $jobUrl,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        $errors++;
                    }
                }
            }
            

            $this->newLine();
            $this->info("─────────────────────────────────────────────────────");
            $this->info("Import fuldført!");
            $this->info("  ✅ Importeret: {$imported}");
            $this->info("  ⏭️  Sprunget over: {$skipped}");
            $this->info("  ❌ Fejl: {$errors}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Kritisk fejl: ' . $e->getMessage());
            Log::error('Kritisk fejl ved import af jobs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Hent liste af job URLs fra thehub.io med automatisk paginering
     */
    private function fetchJobUrlsWithPagination(string $baseUrl, int $limit, int $delay): array
    {
        $allUrls = [];
        $currentPage = 1;
        $hasMorePages = true;

        while ($hasMorePages && count($allUrls) < $limit) {
            // Byg URL med page parameter
            $url = $baseUrl;
            if (str_contains($url, '?')) {
                $url .= '&page=' . $currentPage;
            } else {
                $url .= '?page=' . $currentPage;
            }

            $this->comment("Henter job liste fra side {$currentPage}: {$url}");

            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'da-DK,da;q=0.9,en-US;q=0.8,en;q=0.7',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
            ])->timeout(30)->get($url);

            if ($response->failed()) {
                $this->error("Kunne ikke hente job liste fra side {$currentPage}. Status: " . $response->status());
                break; // Stop hvis vi ikke kan hente siden
            }

            $html = $response->body();
            $pageUrls = $this->extractJobUrls($html, $limit - count($allUrls));

            if (empty($pageUrls)) {
                $this->comment("Ingen jobs fundet på side {$currentPage} - stopper paginering");
                $hasMorePages = false; // Stop hvis der ikke er flere jobs
                break;
            }

            // Tilføj URLs fra denne side (undgå duplikater)
            foreach ($pageUrls as $url) {
                if (!in_array($url, $allUrls)) {
                    $allUrls[] = $url;
                    if (count($allUrls) >= $limit) {
                        $hasMorePages = false; // Stop hvis vi har nået limit
                        break 2; // Break både inner og outer loop
                    }
                }
            }

            $this->info("Fundet " . count($pageUrls) . " jobs på side {$currentPage} (total: " . count($allUrls) . ")");

            $currentPage++;

            // Rate limiting mellem sider (hvis der er flere sider)
            if ($hasMorePages && count($allUrls) < $limit) {
                $this->comment("Venter {$delay} sekunder før næste side...");
                sleep($delay);
            }
        }

        $this->info("Paginering fuldført. Total jobs fundet: " . count($allUrls) . " fra {$currentPage} side(r)");

        return $allUrls;
    }

    /**
     * Hent liste af job URLs fra thehub.io (enkelt side)
     */
    private function fetchJobUrls(string $url, int $limit = 50): array
    {
        
        $this->comment("Henter job liste fra: {$url}");

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'da-DK,da;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
        ])->timeout(30)->get($url);

        if ($response->failed()) {
            $this->error("Kunne ikke hente job liste. Status: " . $response->status());
            return [];
        }

        $html = $response->body();
        $jobUrls = $this->extractJobUrls($html, $limit);

        return $jobUrls;
    }

    /**
     * Ekstraher job URLs fra HTML
     */
    private function extractJobUrls(string $html, int $limit): array
    {
        $urls = [];

        // Prøv først at finde JSON data i HTML (mange moderne sites bruger dette)
        if (preg_match('/<script[^>]*type=["\']application\/json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            $jsonData = json_decode($matches[1], true);
            if ($jsonData && isset($jsonData['jobs'])) {
                foreach ($jsonData['jobs'] as $job) {
                    if (isset($job['url']) || isset($job['id'])) {
                        $url = $job['url'] ?? self::BASE_URL . '/jobs/' . $job['id'];
                        if (preg_match('/\/jobs\/[a-f0-9]+/i', $url)) {
                            $urls[] = $url;
                            if (count($urls) >= $limit) {
                                break;
                            }
                        }
                    }
                }
            }
        }

        // Hvis ingen JSON data, prøv HTML parsing
        if (empty($urls)) {
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            libxml_clear_errors();
            $xpath = new DOMXPath($dom);

            // Prøv forskellige selektorer for at finde job links
            $links = $xpath->query("//a[contains(@href, '/jobs/')] | //a[starts-with(@href, '/jobs/')]");

            foreach ($links as $link) {
                if (!($link instanceof \DOMElement)) {
                    continue;
                }
                $href = $link->getAttribute('href');
                
                // Normaliser URL
                if (str_starts_with($href, '/jobs/')) {
                    $fullUrl = self::BASE_URL . $href;
                } elseif (str_starts_with($href, 'http')) {
                    $fullUrl = $href;
                } else {
                    continue;
                }

                // Fjern query params og fragments
                $fullUrl = strtok($fullUrl, '?');
                $fullUrl = strtok($fullUrl, '#');

                // Undgå duplikater - The Hub bruger hex IDs
                if (!in_array($fullUrl, $urls) && preg_match('/\/jobs\/[a-f0-9]{24,}$/i', $fullUrl)) {
                    $urls[] = $fullUrl;
                    
                    if (count($urls) >= $limit) {
                        break;
                    }
                }
            }
        }

        return array_unique($urls);
    }

    /**
     * Hent detaljer om et specifikt job
     */
    private function fetchJobDetails(string $url): ?array
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'da-DK,da;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Connection' => 'keep-alive',
            'Referer' => 'https://thehub.io/jobs',
            'Upgrade-Insecure-Requests' => '1',
        ])->timeout(30)->get($url);

        if ($response->failed()) {
            return null;
        }

        $html = $response->body();
        return $this->parseJobDetails($html, $url);
    }

    /**
     * Parse job detaljer fra HTML
     */
    private function parseJobDetails(string $html, string $url): ?array
    {
        // Prøv først at finde JSON data i HTML
        $jsonData = $this->extractJsonData($html);
        if ($jsonData) {
            $parsed = $this->parseJobFromJson($jsonData, $url);
            if ($parsed) {
                // Hvis company ikke fundet i JSON, prøv at hente fra HTML
                if (empty($parsed['company'])) {
                    $dom = new DOMDocument();
                    libxml_use_internal_errors(true);
                    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
                    libxml_clear_errors();
                    $xpath = new DOMXPath($dom);
                    $parsed['company'] = $this->extractCompany($xpath);
                }
                return $parsed;
            }
        }

        // Fallback til HTML parsing
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        // Ekstraher title
        $titleNodes = $xpath->query("//h1 | //h2[contains(@class, 'title')] | //*[contains(@class, 'job-title')]");
        $title = null;
        foreach ($titleNodes as $node) {
            $text = trim($node->textContent);
            if (!empty($text) && strlen($text) > 5) {
                $title = $text;
                break;
            }
        }

        if (!$title) {
            // Fallback: prøv at finde i meta tags
            $metaTitle = $xpath->query("//meta[@property='og:title']/@content | //title");
            if ($metaTitle->length > 0) {
                $title = trim($metaTitle->item(0)->textContent ?? $metaTitle->item(0)->nodeValue ?? '');
            }
        }

        // Ekstraher description
        $descriptionNodes = $xpath->query("//*[contains(@class, 'description')] | //*[contains(@class, 'job-description')] | //*[contains(@class, 'content')]");
        $description = null;
        foreach ($descriptionNodes as $node) {
            $text = trim($node->textContent);
            if (strlen($text) > 100) {
                $description = $text;
                break;
            }
        }

        // Ekstraher salary information (prøv både HTML og description tekst)
        $salary = $this->extractSalary($xpath, $description);

        // Ekstraher minimum experience
        $minimumExperience = $this->extractMinimumExperience($xpath);

        // Ekstraher region
        $region = $this->extractRegion($description);

        // Ekstraher skills (tags/keywords)
        $skills = $this->extractSkills($xpath);

        // Ekstraher company navn
        $company = $this->extractCompany($xpath);

        if (!$title) {
            return null;
        }

        return [
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'source' => 'thehub.io',
            'salary_from' => $salary['from'] ?? null,
            'salary_to' => $salary['to'] ?? null,
            'minimum_experience' => $minimumExperience,
            'region_id' => $region?->id,
            'skills' => $skills,
            'company' => $company,
        ];
    }

    /**
     * Ekstraher salary information fra HTML og description tekst
     */
    private function extractSalary(DOMXPath $xpath, ?string $description = null): array
    {
        $salary = ['from' => null, 'to' => null];

        // Prøv først at finde salary i HTML nodes
        $salaryNodes = $xpath->query("
            //*[contains(@class, 'salary')] | 
            //*[contains(text(), 'kr.')] | 
            //*[contains(text(), 'DKK')] |
            //*[contains(text(), 'løn')] |
            //*[contains(text(), 'salary')]
        ");

        foreach ($salaryNodes as $node) {
            $text = trim($node->textContent);
            $parsed = $this->parseSalaryFromText($text);
            if ($parsed['from'] && $parsed['to']) {
                $salary = $parsed;
                break;
            }
        }

        // Hvis ikke fundet i HTML, prøv description teksten
        if (!$salary['from'] && $description) {
            $parsed = $this->parseSalaryFromText($description);
            if ($parsed['from'] && $parsed['to']) {
                $salary = $parsed;
            }
        }

        return $salary;
    }

    /**
     * Parse salary fra tekst (håndterer forskellige formater)
     */
    private function parseSalaryFromText(string $text): array
    {
        $salary = ['from' => null, 'to' => null];

        // Normaliser tekst - fjern unicode non-breaking spaces og ekstra whitespace
        // Non-breaking space (U+00A0) kan også være i teksten
        $text = str_replace("\xC2\xA0", ' ', $text); // UTF-8 encoded non-breaking space
        $text = str_replace("\xA0", ' ', $text); // ISO-8859-1 non-breaking space
        $text = preg_replace('/\s+/', ' ', $text);
        
        // VIGTIGT: Vi skal kun matche salary hvis det er eksplicit nævnt med DKK, kr, eller k
        // Tjek først om der overhovedet er salary keywords i teksten
        $hasSalaryKeywords = preg_match('/(?:DKK|kr\.?|salary|løn|k\s*(?:per\s+month|monthly|pm))/i', $text);
        if (!$hasSalaryKeywords) {
            return $salary; // Ingen salary keywords fundet, returner null
        }
        
        // Pattern 1: "DKK 45,000 - DKK 55,000" eller "DKK 45.000 - DKK 55.000"
        if (preg_match('/DKK\s+(\d{1,3}(?:[.,]\d{3})*)\s*[-–—]\s*DKK\s+(\d{1,3}(?:[.,]\d{3})*)/i', $text, $matches)) {
            $from = $this->normalizeSalary($matches[1]);
            $to = $this->normalizeSalary($matches[2]);
            
            if ($from && $to && $from <= $to) {
                $salary['from'] = $from;
                $salary['to'] = $to;
                return $salary;
            }
        }

        // Pattern 2a: "46.000 kr. - 52.000 kr." eller "45,000 DKK - 55,000 DKK" (kr./DKK efter begge tal)
        if (preg_match('/(\d{1,3}(?:[.,]\d{3})*)\s*(?:DKK|kr\.?)\s*[-–—]\s*(\d{1,3}(?:[.,]\d{3})*)\s*(?:DKK|kr\.?)/i', $text, $matches)) {
            $from = $this->normalizeSalary($matches[1]);
            $to = $this->normalizeSalary($matches[2]);
            
            if ($from && $to && $from <= $to) {
                $salary['from'] = $from;
                $salary['to'] = $to;
                return $salary;
            }
        }

        // Pattern 2c: "starts at 46,000 DKK ... up to 52,000 DKK" eller "fra 45.000 kr. til 55.000 kr." (tekst mellem tal)
        if (preg_match('/(?:starts?\s+at|fra|from)\s+(\d{1,3}(?:[.,]\d{3})*)\s*(?:DKK|kr\.?).*?(?:up\s+to|til|to)\s+(\d{1,3}(?:[.,]\d{3})*)\s*(?:DKK|kr\.?)/i', $text, $matches)) {
            $from = $this->normalizeSalary($matches[1]);
            $to = $this->normalizeSalary($matches[2]);
            
            if ($from && $to && $from <= $to) {
                $salary['from'] = $from;
                $salary['to'] = $to;
                return $salary;
            }
        }

        // Pattern 2b: "45,000 - 55,000 DKK" eller "45.000 - 55.000 kr." (kr./DKK kun efter sidste tal)
        if (preg_match('/(\d{1,3}(?:[.,]\d{3})*)\s*[-–—]\s*(\d{1,3}(?:[.,]\d{3})*)\s*(?:DKK|kr\.?)/i', $text, $matches)) {
            $from = $this->normalizeSalary($matches[1]);
            $to = $this->normalizeSalary($matches[2]);
            
            if ($from && $to && $from <= $to) {
                $salary['from'] = $from;
                $salary['to'] = $to;
                return $salary;
            }
        }

        // Pattern 3: "45-55k" eller "45k - 55k" (kræver k efter begge tal)
        if (preg_match('/(\d+)\s*k\s*[-–—]\s*(\d+)\s*k/i', $text, $matches)) {
            $from = $this->normalizeSalary($matches[1] . 'k');
            $to = $this->normalizeSalary($matches[2] . 'k');
            
            if ($from && $to && $from <= $to) {
                $salary['from'] = $from;
                $salary['to'] = $to;
                return $salary;
            }
        }

        // Pattern 4: "DKK 45,000" eller "45.000 kr." (kun én værdi)
        // VIGTIGT: Kun match hvis der IKKE er en bindestreg før eller efter værdien (for at undgå at matche før Pattern 1/2)
        // Tjek først om der er et interval pattern i teksten - hvis der er, skal Pattern 4 ikke matche
        $hasIntervalPattern = preg_match('/(?:DKK\s+)?(\d{1,3}(?:[.,]\d{3})*)\s*(?:DKK|kr\.?)?\s*[-–—]\s*(?:DKK\s+)?(\d{1,3}(?:[.,]\d{3})*)\s*(?:DKK|kr\.?)/i', $text);
        if (!$hasIntervalPattern) {
            if (preg_match('/(?:DKK\s+)?(\d{1,3}(?:[.,]\d{3})*)\s*(?:DKK|kr\.?)(?!\s*[-–—])/i', $text, $matches)) {
                $value = $this->normalizeSalary($matches[1]);
                if ($value) {
                    $salary['from'] = $value;
                    $salary['to'] = $value;
                    return $salary;
                }
            }
        }

        // Pattern 5: "45-55k per month" eller lignende
        if (preg_match('/(\d+)\s*[-–—]\s*(\d+)\s*k\s*(?:per\s+month|monthly|pm)/i', $text, $matches)) {
            $from = $this->normalizeSalary($matches[1] . 'k');
            $to = $this->normalizeSalary($matches[2] . 'k');
            
            if ($from && $to && $from <= $to) {
                $salary['from'] = $from;
                $salary['to'] = $to;
                return $salary;
            }
        }

        return $salary;
    }

    /**
     * Normaliser salary værdi til integer
     */
    private function normalizeSalary(string $value): ?int
    {
        // Trim whitespace
        $value = trim($value);
        
        // Hvis værdien ender med k eller K, gange med 1000
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*k$/i', $value, $matches)) {
            $numValue = str_replace(',', '', $matches[1]);
            $numValue = str_replace('.', '', $numValue);
            $intValue = (int) $numValue * 1000;
            
            // Valider at det er et realistisk beløb (mellem 20.000 og 2.000.000)
            if ($intValue >= 20000 && $intValue <= 2000000) {
                return $intValue;
            }
            return null;
        }

        // Fjern komma og punktum (tusind separatorer)
        $value = str_replace(',', '', $value);
        $value = str_replace('.', '', $value);

        // Konverter til integer
        $intValue = (int) $value;
        
        // Valider at det er et realistisk beløb (mellem 20.000 og 2.000.000)
        if ($intValue >= 20000 && $intValue <= 2000000) {
            return $intValue;
        }

        return null;
    }

    /**
     * Tjek om job har salary information
     */
    private function hasSalary(array $jobData): bool
    {
        // Kun accepter salary hvis begge værdier er sat og realistiske
        if (empty($jobData['salary_from']) || empty($jobData['salary_to'])) {
            return false;
        }
        
        // Valider at salary værdier er realistiske (mellem 20.000 og 2.000.000)
        $from = (int) $jobData['salary_from'];
        $to = (int) $jobData['salary_to'];
        
        if ($from < 20000 || $to < 20000 || $from > 2000000 || $to > 2000000) {
            return false;
        }
        
        if ($from > $to) {
            return false;
        }
        
        return true;
    }

    /**
     * Ekstraher minimum experience fra HTML
     */
    private function extractMinimumExperience(DOMXPath $xpath): ?int
    {
        $experienceNodes = $xpath->query("
            //*[contains(text(), 'erfaring')] | 
            //*[contains(text(), 'experience')] |
            //*[contains(text(), 'år')]
        ");

        foreach ($experienceNodes as $node) {
            $text = trim($node->textContent);
            
            // Prøv at finde tal i teksten (fx "3 års erfaring", "minimum 2 years")
            if (preg_match('/(\d+)\s*(?:år|years?|år\s*erfaring|years?\s*experience)/i', $text, $matches)) {
                $years = (int) $matches[1];
                if ($years >= 0 && $years <= 20) {
                    return $years;
                }
            }
        }

        return null;
    }

    /**
     * Ekstraher skills fra HTML
     */
    private function extractSkills(DOMXPath $xpath): array
    {
        $skills = [];

        // Prøv at finde tags eller keywords
        $tagNodes = $xpath->query("
            //*[contains(@class, 'tag')] | 
            //*[contains(@class, 'skill')] |
            //*[contains(@class, 'keyword')] |
            //*[contains(@class, 'badge')]
        ");

        foreach ($tagNodes as $node) {
            $text = trim($node->textContent);
            if (!empty($text) && strlen($text) < 50) {
                $skills[] = $text;
            }
        }

        return array_unique($skills);
    }

    /**
     * Ekstraher company navn fra HTML
     */
    private function extractCompany(DOMXPath $xpath): ?string
    {
        // Find alle links til /startups/ men ekskluder navigation links
        $links = $xpath->query("//a[starts-with(@href, '/startups/')]");

        foreach ($links as $link) {
            if (!($link instanceof \DOMElement)) {
                continue;
            }

            $href = $link->getAttribute('href');
            
            // Skip navigation links
            if ($href === '/startups/join' || $href === '/startups' || str_contains($href, '?')) {
                continue;
            }

            // Check om linket er i navigation eller footer
            $parent = $link;
            $isInNav = false;
            while ($parent && $parent->nodeName !== 'body') {
                $tagName = strtolower($parent->nodeName ?? '');
                $class = '';
                if ($parent instanceof \DOMElement) {
                    $class = strtolower($parent->getAttribute('class') ?? '');
                }
                if ($tagName === 'nav' || $tagName === 'header' || $tagName === 'footer' || 
                    str_contains($class, 'nav') || str_contains($class, 'footer') || str_contains($class, 'header')) {
                    $isInNav = true;
                    break;
                }
                $parent = $parent->parentNode;
            }

            if ($isInNav) {
                continue;
            }

            // Match /startups/[company-name] pattern
            if (preg_match('/^\/startups\/([^\/]+)$/', $href, $matches)) {
                $slug = $matches[1];
                
                // Prøv at få tekst fra linket
                $linkText = trim($link->textContent);
                
                // Hvis link teksten ikke er "See company profile" eller lignende, brug den
                $lowerText = strtolower($linkText);
                if (!empty($linkText) && 
                    !str_contains($lowerText, 'see') && 
                    !str_contains($lowerText, 'profile') &&
                    !str_contains($lowerText, 'read more') &&
                    strlen($linkText) < 50) {
                    return $linkText;
                }
                
                // Ellers konverter slug til title case
                $companyName = str_replace('-', ' ', $slug);
                $companyName = ucwords(strtolower($companyName));
                
                return $companyName;
            }
        }

        return null;
    }

    /**
     * Ekstraher region fra description tekst
     */
    private function extractRegion(?string $description): ?Region
    {
        if (!$description) {
            return null;
        }

        // Hent alle regioner fra databasen
        $regions = Region::all();
        
        // Normaliser tekst
        $text = strtolower($description);
        $text = str_replace("\xC2\xA0", ' ', $text); // UTF-8 encoded non-breaking space
        $text = str_replace("\xA0", ' ', $text); // ISO-8859-1 non-breaking space

        // By-navne mapping til regioner (baseret på JobTitleExtractor)
        $cityToRegion = [
            // Storkøbenhavn
            'københavn' => 'Storkøbenhavn',
            'copenhagen' => 'Storkøbenhavn',
            'frederiksberg' => 'Storkøbenhavn',
            'gentofte' => 'Storkøbenhavn',
            'glostrup' => 'Storkøbenhavn',
            'lyngby' => 'Storkøbenhavn',
            'rødovre' => 'Storkøbenhavn',
            'ballerup' => 'Storkøbenhavn',
            'brøndby' => 'Storkøbenhavn',
            'hvidovre' => 'Storkøbenhavn',
            'taastrup' => 'Storkøbenhavn',
            
            // Øvrige Sjælland & Øer
            'roskilde' => 'Øvrige Sjælland & Øer',
            'næstved' => 'Øvrige Sjælland & Øer',
            'køge' => 'Øvrige Sjælland & Øer',
            'slagelse' => 'Øvrige Sjælland & Øer',
            'nykøbing f' => 'Øvrige Sjælland & Øer',
            'nykøbing falster' => 'Øvrige Sjælland & Øer',
            
            // Fyn
            'odense' => 'Fyn',
            'svendborg' => 'Fyn',
            'nyborg' => 'Fyn',
            'middelfart' => 'Fyn',
            
            // Østjylland
            'aarhus' => 'Østjylland',
            'randers' => 'Østjylland',
            'horsens' => 'Østjylland',
            'skanderborg' => 'Østjylland',
            'silkeborg' => 'Østjylland',
            
            // Region Sydjylland
            'kolding' => 'Region Sydjylland',
            'esbjerg' => 'Region Sydjylland',
            'vejle' => 'Region Sydjylland',
            'fredericia' => 'Region Sydjylland',
            'aabenraa' => 'Region Sydjylland',
            
            // Midt-, Vest- & Nordjylland
            'aalborg' => 'Midt-, Vest- & Nordjylland',
            'viborg' => 'Midt-, Vest- & Nordjylland',
            'herning' => 'Midt-, Vest- & Nordjylland',
            'skive' => 'Midt-, Vest- & Nordjylland',
            'thisted' => 'Midt-, Vest- & Nordjylland',
            'hjørring' => 'Midt-, Vest- & Nordjylland',
        ];

        // Prøv at finde by-navne i teksten
        foreach ($cityToRegion as $city => $regionName) {
            if (str_contains($text, $city)) {
                $region = $regions->firstWhere('name', $regionName);
                if ($region) {
                    return $region;
                }
            }
        }

        // Prøv direkte match på region navne
        foreach ($regions as $region) {
            $regionNameLower = strtolower($region->name);
            if (str_contains($text, $regionNameLower)) {
                return $region;
            }
        }

        return null;
    }

    /**
     * Vis job data i konsollen
     */
    private function displayJobData(array $jobData): void
    {
        $this->line("  📋 Titel: " . $jobData['title']);
        
        if (!empty($jobData['company'])) {
            $this->line("  🏢 Virksomhed: " . $jobData['company']);
        }
        
        if (!empty($jobData['salary_from']) || !empty($jobData['salary_to'])) {
            $salaryStr = '';
            if ($jobData['salary_from']) {
                $salaryStr .= number_format($jobData['salary_from'], 0, ',', '.') . ' kr.';
            }
            if ($jobData['salary_from'] && $jobData['salary_to'] && $jobData['salary_from'] != $jobData['salary_to']) {
                $salaryStr .= ' - ';
            }
            if ($jobData['salary_to'] && $jobData['salary_from'] != $jobData['salary_to']) {
                $salaryStr .= number_format($jobData['salary_to'], 0, ',', '.') . ' kr.';
            }
            $this->line("  💰 Løn: " . $salaryStr);
        }

        if (!empty($jobData['minimum_experience'])) {
            $this->line("  ⏱️  Erfaring: " . $jobData['minimum_experience'] . " år");
        }

        if (!empty($jobData['region_id'])) {
            $region = Region::find($jobData['region_id']);
            if ($region) {
                $this->line("  📍 Region: " . $region->name);
            }
        }

        if (!empty($jobData['skills'])) {
            $this->line("  🏷️  Skills: " . implode(', ', array_slice($jobData['skills'], 0, 5)));
        }
    }

    /**
     * Gem job til database
     */
    private function saveJob(array $jobData): bool
    {
        try {
            if (JobPosting::where('url', $jobData['url'])->exists()) {
                return true;
            }

            $jobPosting = JobPosting::create(
                [
                    'title' => $jobData['title'],
                    'description' => $jobData['description'],
                    'job_title_id' => $jobData['job_title_id'] ?? null,
                    'region_id' => $jobData['region_id'] ?? null,
                    'salary_from' => $jobData['salary_from'],
                    'salary_to' => $jobData['salary_to'],
                    'url' => $jobData['url'],
                    'source' => $jobData['source'],
                    'minimum_experience' => $jobData['minimum_experience'],
                    'company' => $jobData['company'] ?? null,
                ]
            );

            // Attach skills hvis de findes
            if (!empty($jobData['skills'])) {
                $this->attachSkills($jobPosting, $jobData['skills']);
            }

            return true;

        } catch (\Exception $e) {
            $this->error("  ❌ Fejl ved gemning: " . $e->getMessage());
            Log::error('Fejl ved gemning af job', [
                'job_data' => $jobData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Prøv at matche job title med eksisterende job titles
     */
    private function matchJobTitle(string $title): ?JobTitle
    {
        // Normaliser title
        $normalizedTitle = strtolower(trim($title));

        // Prøv exact match først
        $jobTitle = JobTitle::whereRaw('LOWER(name) = ?', [$normalizedTitle])->first();
        if ($jobTitle) {
            return $jobTitle;
        }

        // Prøv partial match
        $jobTitles = JobTitle::all();
        foreach ($jobTitles as $jt) {
            $jtName = strtolower($jt->name);
            
            // Tjek om job title indeholder eller er indeholdt i titlen
            if (str_contains($normalizedTitle, $jtName) || str_contains($jtName, $normalizedTitle)) {
                return $jt;
            }
        }

        return null;
    }

    /**
     * Attach skills til job posting
     */
    private function attachSkills(JobPosting $jobPosting, array $skillNames): void
    {
        foreach ($skillNames as $skillName) {
            $skill = Skill::firstOrCreate(['name' => trim($skillName)]);
            $jobPosting->skills()->syncWithoutDetaching([$skill->id]);
        }
    }

    /**
     * Prøv at ekstrahere JSON data fra HTML
     */
    private function extractJsonData(string $html): ?array
    {
        // Prøv at finde JSON i script tags
        if (preg_match('/<script[^>]*id=["\']__NEXT_DATA__["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            $data = json_decode($matches[1], true);
            if ($data) {
                return $data;
            }
        }

        // Prøv generel JSON i script tags
        if (preg_match('/<script[^>]*type=["\']application\/json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            $data = json_decode($matches[1], true);
            if ($data) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Parse job data fra JSON struktur
     */
    private function parseJobFromJson(array $jsonData, string $url): ?array
    {
        // Prøv forskellige JSON strukturer
        $job = null;

        // Next.js struktur
        if (isset($jsonData['props']['pageProps']['job'])) {
            $job = $jsonData['props']['pageProps']['job'];
        } elseif (isset($jsonData['pageProps']['job'])) {
            $job = $jsonData['pageProps']['job'];
        } elseif (isset($jsonData['job'])) {
            $job = $jsonData['job'];
        }

        if (!$job || !is_array($job)) {
            return null;
        }

        $title = $job['title'] ?? $job['name'] ?? null;
        if (!$title) {
            return null;
        }

        $description = $job['description'] ?? $job['content'] ?? null;
        
        // Ekstraher salary
        $salary = ['from' => null, 'to' => null];
        if (isset($job['salary'])) {
            if (is_array($job['salary'])) {
                $salary['from'] = $job['salary']['from'] ?? $job['salary']['min'] ?? null;
                $salary['to'] = $job['salary']['to'] ?? $job['salary']['max'] ?? null;
            } elseif (is_string($job['salary'])) {
                // Parse string salary
                $parsed = $this->parseSalaryFromText($job['salary']);
                $salary = $parsed;
            }
        }

        // Hvis salary ikke fundet i JSON, prøv at parse fra description
        if (!$salary['from'] && $description) {
            $parsed = $this->parseSalaryFromText($description);
            if ($parsed['from'] && $parsed['to']) {
                $salary = $parsed;
            }
        }

        // Normaliser salary værdier
        if ($salary['from'] && is_string($salary['from'])) {
            $salary['from'] = $this->normalizeSalary($salary['from']);
        }
        if ($salary['to'] && is_string($salary['to'])) {
            $salary['to'] = $this->normalizeSalary($salary['to']);
        }

        // Ekstraher minimum experience
        $minimumExperience = $job['minimum_experience'] ?? $job['experience'] ?? $job['years_of_experience'] ?? null;
        if ($minimumExperience && is_string($minimumExperience)) {
            if (preg_match('/(\d+)/', $minimumExperience, $matches)) {
                $minimumExperience = (int) $matches[1];
            } else {
                $minimumExperience = null;
            }
        }

        // Ekstraher region
        $region = $this->extractRegion($description);

        // Ekstraher skills
        $skills = [];
        if (isset($job['skills']) && is_array($job['skills'])) {
            $skills = array_map(fn($s) => is_array($s) ? ($s['name'] ?? $s['title'] ?? '') : $s, $job['skills']);
        } elseif (isset($job['tags']) && is_array($job['tags'])) {
            $skills = array_map(fn($t) => is_array($t) ? ($t['name'] ?? $t['title'] ?? '') : $t, $job['tags']);
        }

        // Ekstraher company navn fra JSON eller HTML
        $company = null;
        if (isset($job['company'])) {
            if (is_array($job['company'])) {
                $company = $job['company']['name'] ?? $job['company']['title'] ?? null;
            } else {
                $company = $job['company'];
            }
        } elseif (isset($job['startup'])) {
            if (is_array($job['startup'])) {
                $company = $job['startup']['name'] ?? $job['startup']['title'] ?? null;
            } else {
                $company = $job['startup'];
            }
        }
        
        // Hvis company ikke fundet i JSON, prøv at parse fra HTML (hvis vi har HTML)
        // Dette håndteres i parseJobDetails metoden

        return [
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'source' => 'thehub.io',
            'salary_from' => $salary['from'],
            'salary_to' => $salary['to'],
            'minimum_experience' => $minimumExperience,
            'region_id' => $region?->id,
            'skills' => array_filter($skills),
            'company' => $company,
        ];
    }

    /**
     * Håndter import af et enkelt job via URL
     */
    private function handleSingleJob(string $url, bool $dryRun): int
    {
        // Normaliser URL
        $url = $this->normalizeJobUrl($url);

        $this->info("Importerer specifikt job fra: {$url}");
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN mode - jobbet vil ikke blive gemt');
        }

        try {
            $jobData = $this->fetchJobDetails($url);

            if (!$jobData) {
                $this->error('Kunne ikke hente job data');
                return Command::FAILURE;
            }

            // Vis job data
            $this->displayJobData($jobData);
            $this->newLine();

            // Tjek om salary er tilgængelig
            if (!$this->hasSalary($jobData)) {
                $this->warn('⚠️  Ingen salary information fundet');
                $this->warn('Dette job vil IKKE blive importeret (kun jobs med salary importeres)');
                return Command::SUCCESS;
            }

            if ($dryRun) {
                $this->comment('[DRY RUN] Jobbet ville blive gemt');
                return Command::SUCCESS;
            }

            // Gem jobbet
            $saved = $this->saveJob($jobData);
            
            if ($saved) {
                $this->info('✅ Job gemt succesfuldt');
                return Command::SUCCESS;
            } else {
                $this->warn('⚠️  Job blev ikke gemt (måske allerede eksisterer)');
                return Command::SUCCESS;
            }

        } catch (\Exception $e) {
            $this->error('Fejl: ' . $e->getMessage());
            Log::error('Fejl ved import af specifikt job', [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Normaliser job URL til korrekt format
     */
    private function normalizeJobUrl(string $url): string
    {
        // Fjern query params og fragments
        $url = strtok($url, '?');
        $url = strtok($url, '#');

        // Hvis URL ikke starter med http, antag det er en relativ URL
        if (!str_starts_with($url, 'http')) {
            if (str_starts_with($url, '/jobs/')) {
                $url = self::BASE_URL . $url;
            } elseif (str_starts_with($url, 'jobs/')) {
                $url = self::BASE_URL . '/' . $url;
            } else {
                // Antag det er et job ID
                $url = self::BASE_URL . '/jobs/' . $url;
            }
        }

        return $url;
    }
}
