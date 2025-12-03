<?php

namespace App\Console\Commands;

use App\Models\JobPosting;
use App\Models\Skill;
use App\Services\ExtractJobPostingExperience;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class MatchJobPostingSkills extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'job-postings:match-skills
                            {--after-date= : Kun job postings oprettet efter denne dato (YYYY-MM-DD)}
                            {--id= : Match kun et specifikt job posting ID}
                            {--limit= : Maksimalt antal job postings der skal processeres}
                            {--estimate : Vis kun omkostningsestimat}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Match job posting descriptions med skills ved hjælp af OpenAI API';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $afterDate = $this->option('after-date');
        $specificId = $this->option('id');
        $limit = $this->option('limit');
        $estimate = $this->option('estimate');

        $this->info('🔍 Matcher job posting descriptions med skills ved hjælp af OpenAI...');
        $this->newLine();

        // Byg query
        $query = JobPosting::whereNotNull('description')
            ->whereNotNull('salary_from')
            ->whereDoesntHave('skills');

        if ($specificId) {
            $query->where('id', $specificId);
        }

        // Filtrer efter dato hvis angivet
        if ($afterDate) {
            try {
                $date = \Carbon\Carbon::parse($afterDate);
                $query->where('created_at', '>=', $date);
                $this->info("📅 Filtrerer job postings oprettet efter: {$date->format('Y-m-d H:i:s')}");
            } catch (\Exception $e) {
                $this->error("Ugyldig dato format: {$afterDate}. Brug YYYY-MM-DD");
                return Command::FAILURE;
            }
        }

        if ($limit) {
            $query->limit((int) $limit);
        }

        $jobPostings = $query->get();

        if ($jobPostings->isEmpty()) {
            $this->warn('Ingen job postings fundet');
            return Command::SUCCESS;
        }

        $this->info("Fandt {$jobPostings->count()} job posting(s) til matching");

        // Hent alle skills fra databasen
        $allSkills = Skill::orderBy('name')->pluck('name')->toArray();

        if (empty($allSkills)) {
            $this->error('Ingen skills fundet i databasen');
            return Command::FAILURE;
        }

        $this->info("Total antal skills i systemet: " . count($allSkills));
        $this->newLine();

        // Initialiser experience extractor service
        $experienceExtractor = new ExtractJobPostingExperience();

        // Vis omkostningsestimat
        $skillsCostEstimate = $this->estimateCost($jobPostings->count(), count($allSkills));
        $experienceCostEstimate = $experienceExtractor->estimateCost($jobPostings->count());
        $totalCostUsd = $skillsCostEstimate['estimated_cost_usd'] + $experienceCostEstimate['estimated_cost_usd'];
        $totalCostDkk = round($totalCostUsd * 7, 2);

        $this->info('💰 Omkostningsestimat:');
        $this->line("   Antal job postings: {$skillsCostEstimate['job_posting_count']}");
        $this->line("   Antal skills: {$skillsCostEstimate['skill_count']}");
        $this->line("   Estimeret pris (skills): \${$skillsCostEstimate['estimated_cost_usd']} USD (~{$skillsCostEstimate['estimated_cost_dkk']} DKK)");
        $this->line("   Estimeret pris (experience): \${$experienceCostEstimate['estimated_cost_usd']} USD (~{$experienceCostEstimate['estimated_cost_dkk']} DKK)");
        $this->line("   Total estimeret pris: \${$totalCostUsd} USD (~{$totalCostDkk} DKK)");
        $this->line("   Model: gpt-4o-mini");
        $this->newLine();

        if ($estimate) {
            $this->info('✓ Kun estimat - ingen matching udført');
            return Command::SUCCESS;
        }

        $this->newLine();

        // Match skills og extract experience
        $successCount = 0;
        $failCount = 0;
        $experienceUpdatedCount = 0;

        $progressBar = $this->output->createProgressBar($jobPostings->count());
        $progressBar->start();

        foreach ($jobPostings as $jobPosting) {
            try {
                // Match skills
                $matchedSkills = $this->matchSkillsWithOpenAI($jobPosting->description, $allSkills);

                if ($matchedSkills !== null) {
                    // Find skill IDs fra navne
                    $skillIds = [];
                    foreach ($matchedSkills as $skillName) {
                        $skill = Skill::where('name', $skillName)->first();
                        if ($skill) {
                            $skillIds[] = $skill->id;
                        }
                    }

                    // Sync skills til job posting (erstatter eksisterende)
                    $jobPosting->skills()->sync($skillIds);

                    $successCount++;
                } else {
                    $failCount++;
                }

                // Extract minimum experience
                $minimumExperience = $experienceExtractor->extractMinimumExperience($jobPosting->description);
                
                if ($minimumExperience !== null) {
                    $jobPosting->minimum_experience = $minimumExperience;
                    $jobPosting->save();
                    $experienceUpdatedCount++;
                }

                if ($matchedSkills !== null) {
                    $this->newLine();
                    $experienceInfo = $minimumExperience !== null ? " (experience: {$minimumExperience} år)" : "";
                    $this->line("✓ Job Posting #{$jobPosting->id}: Matched " . count($skillIds ?? []) . " skill(s){$experienceInfo}");
                } else {
                    $this->newLine();
                    $this->line("⚠ Job Posting #{$jobPosting->id}: Kunne ikke matche skills");
                }

            } catch (\Exception $e) {
                $failCount++;
                $this->newLine();
                $this->error("✗ Fejl ved matching for Job Posting #{$jobPosting->id}: {$e->getMessage()}");

                Log::error('Fejl ved skill matching', [
                    'job_posting_id' => $jobPosting->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $progressBar->advance();

            // Lille pause for at undgå rate limits
            usleep(500000); // 0.5 sekund
        }

        $progressBar->finish();
        $this->newLine(2);

        // Vis resultat
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('✅ Matching afsluttet!');
        $this->info('📊 Resultat:');
        $this->line("   • Total processeret: {$jobPostings->count()}");
        $this->line("   • Succesfulde (skills): {$successCount}");
        $this->line("   • Fejlede (skills): {$failCount}");
        $this->line("   • Experience opdateret: {$experienceUpdatedCount}");
        $this->line("   • Total estimeret omkostning: \${$totalCostUsd} USD");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        Log::info('Skill matching og experience extraction afsluttet', [
            'total' => $jobPostings->count(),
            'success' => $successCount,
            'failed' => $failCount,
            'experience_updated' => $experienceUpdatedCount,
            'estimated_cost_usd' => $totalCostUsd,
        ]);

        return Command::SUCCESS;
    }

    /**
     * Match skills med job posting description ved hjælp af OpenAI
     *
     * @param string $description
     * @param array $allSkills
     * @return array|null Array af skill navne der matcher, eller null hvis fejl
     */
    private function matchSkillsWithOpenAI(string $description, array $allSkills): ?array
    {
        if (empty($description)) {
            return null;
        }

        // Byg formateret liste af skills
        $skillsList = '- ' . implode("\n- ", $allSkills);

        // Truncate description hvis den er for lang (men øg limit til 4000 for bedre dækning)
        // 4000 karakterer er typisk nok til at dække hele beskrivelsen inkl. tech stack sektioner
        $descriptionPreview = mb_substr($description, 0, 4000);

        // Byg user prompt med fokus på tech stack sektioner
        $userPrompt = "Analyser følgende job posting beskrivelse og identificer hvilke skills der er relevante:\n\n";
        $userPrompt .= "Beskrivelse: {$descriptionPreview}\n\n";
        $userPrompt .= "FOKUS PÅ: Se efter sektioner som \"tech stack\", \"technologies\", \"skills required\", \"our stack\", \"tech stack\", \"technologies we use\", \"forventet tech stack\", osv.\n\n";
        $userPrompt .= "VIGTIGT: Ignorer skills der nævnes i negative kontekster, jokes eller eksempler på hvad de IKKE søger.\n\n";
        $userPrompt .= "Vælg KUN de skills fra listen nedenfor der eksplicit nævnes eller tydeligt er relevante for jobbet. ";
        $userPrompt .= "Hvis et skill nævnes sammen med et andet (fx \"PHP with Laravel\" eller \"Node/NestJS\"), inkluder begge. ";
        $userPrompt .= "Hvis ingen skills passer, returner en tom array.";

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Du er en ekspert i at identificere relevante IT-skills baseret på job posting beskrivelser.

KRITISKE REGLER:
1. Du må KUN returnere skills der findes PRÆCIST på listen nedenfor
2. Du må ALDRIG finde på nye skills eller gætte
3. Returner ALTID et JSON object: {\"skills\": [\"skill1\", \"skill2\", ...]} eller {\"skills\": []} hvis ingen matcher
4. VÆR KONSERVATIV: Det er bedre at returnere færre relevante skills end at inkludere irrelevante

HVOR SKAL DU SØGE EFTER SKILLS:
- FOKUS PÅ sektioner med overskrifter som: \"tech stack\", \"technologies\", \"our stack\", \"technologies we use\", \"forventet tech stack\", \"tech stack (ingen krav)\", \"our tech stack\", \"technologies\", \"skills required\", \"what you bring\", \"requirements\", osv.
- Disse sektioner indeholder typisk lister eller bullet points med faktiske teknologier
- Analyser HELE beskrivelsen grundigt - skills kan være nævnt både tidligt og sent i teksten

HVAD SKAL DU IGNORERE:
- Skills der nævnes i jokes eller humoristiske sammenhænge (fx \"Tænker du på actionfilmen fra 2012, når vi snakker om Django?\" → Django er IKKE relevant)
- Skills der nævnes som eksempler på hvad de IKKE søger (fx \"Så bliver du nok ikke X's udvikler\" → skills nævnt før dette er IKKE relevante)
- Skills der nævnes i negative kontekster (fx \"vi bruger IKKE X\" → X er IKKE relevant)
- Skills der kun nævnes i forbifarten uden kontekst

HVORDAN SKAL DU MATCHE SKILLS:
- Hvis et skill eksplicit nævnes i tech stack sektionen → inkluder det
- Hvis der står \"X with Y\" eller \"X/Y\" eller \"X eller Y\" → inkluder BÅDE X og Y hvis begge findes på listen
- Hvis der står \"X (Y)\" eller \"X using Y\" → inkluder BÅDE X og Y hvis begge findes på listen
- Hvis der står \"vi bruger X\" eller \"our stack includes X\" → inkluder X
- Hvis et skill kun nævnes i forbifarten uden at være i tech stack sektionen → overvej om det er relevant, men vær konservativ
- Hvis du er i tvivl om et skill er relevant → lad være med at inkludere det

EKSEMPLER:
- \"PHP with Laravel\" → inkluder både \"PHP\" og \"Laravel\"
- \"Node/NestJS\" → inkluder både \"Node.js\" og \"NestJS\" (hvis NestJS findes på listen)
- \"React/Next.js\" → inkluder både \"React\" og \"Next.js\"
- \"Python/FastAPI\" → inkluder både \"Python\" og \"FastAPI\"
- \"Tænker du på actionfilmen fra 2012, når vi snakker om Django?\" → Django er IKKE relevant (det er en joke)

TILLADTE SKILLS:
{$skillsList}",
                ],
                [
                    'role' => 'user',
                    'content' => $userPrompt,
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

        Log::info('OpenAI skill matching response', [
            'raw_response' => $content,
            'parsed_data' => $data,
        ]);

        if (!isset($data['skills']) || !is_array($data['skills'])) {
            Log::warning('Ugyldig response struktur fra OpenAI', ['response' => $data]);
            return null;
        }

        // Valider at alle skills findes i databasen
        $validSkills = [];
        foreach ($data['skills'] as $skillName) {
            if (in_array($skillName, $allSkills, true)) {
                $validSkills[] = $skillName;
            } else {
                Log::warning('OpenAI returnerede et skill der ikke findes i databasen', [
                    'skill' => $skillName,
                ]);
            }
        }

        return $validSkills;
    }

    /**
     * Beregn estimeret pris for skill matching
     */
    private function estimateCost(int $jobPostingCount, int $skillCount): array
    {
        // Estimer tokens per request
        // System prompt: ~600 tokens (længere prompt med flere instruktioner)
        // User prompt: ~150 tokens + description (max 4000 chars ≈ 1000 tokens) + skills list (skillCount * 5 tokens)
        // Response: ~15 tokens per skill matched (estimeret 5-15 skills per job)
        
        $avgSkillsPerJob = 10; // Estimeret gennemsnit (øget fra 7)
        $systemTokens = 600; // Øget fra 200 pga. længere prompt
        $userPromptBase = 150; // Øget fra 100 pga. flere instruktioner
        $descriptionTokens = 1000; // Øget fra 500 pga. 4000 char limit i stedet for 2000
        $skillsListTokens = $skillCount * 5; // Ca. 5 tokens per skill navn
        $tokensPerRequest = $systemTokens + $userPromptBase + $descriptionTokens + $skillsListTokens;
        $tokensPerResponse = $avgSkillsPerJob * 15; // Ca. 15 tokens per skill i response (øget fra 10)

        // Beregn total tokens
        $totalInputTokens = $jobPostingCount * $tokensPerRequest;
        $totalOutputTokens = $jobPostingCount * $tokensPerResponse;

        $inputCost = ($totalInputTokens / 1000000) * 0.15; // gpt-4o-mini pricing
        $outputCost = ($totalOutputTokens / 1000000) * 0.60;
        $totalCost = $inputCost + $outputCost;

        return [
            'job_posting_count' => $jobPostingCount,
            'skill_count' => $skillCount,
            'estimated_cost_usd' => round($totalCost, 4),
            'estimated_cost_dkk' => round($totalCost * 7, 2),
            'input_tokens' => $totalInputTokens,
            'output_tokens' => $totalOutputTokens,
        ];
    }
}

