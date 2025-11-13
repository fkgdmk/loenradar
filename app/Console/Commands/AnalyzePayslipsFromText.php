<?php

namespace App\Console\Commands;

use App\Models\Payslip;
use App\Services\PayslipAnalyzer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AnalyzePayslipsFromText extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payslips:analyze-text 
                            {--limit= : Antal payslips der skal analyseres}
                            {--id= : Analyser kun et specifikt payslip ID}
                            {--estimate : Vis kun omkostningsestimat}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyser payslips uden billeder og udtræk løn og erfaring fra titel, beskrivelse eller kommentarer';

    /**
     * Execute the console command.
     */
    public function handle(PayslipAnalyzer $analyzer): int
    {
        $limit = $this->option('limit');
        $specificId = $this->option('id');
        $estimate = $this->option('estimate');

        $this->info('🔍 Analyserer payslips fra tekst med OpenAI...');
        $this->newLine();

        // Byg query - find payslips uden media (ingen billeder)
        $query = Payslip::whereNull('denied_at')
            ->whereNotNull('job_title_id')
            ->whereNull('salary')
            ->whereDoesntHave('media')
            ->where(function ($q) {
                $q->whereNotNull('title')
                  ->orWhereNotNull('description')
                  ->orWhereNotNull('comments');
            });

        if ($specificId) {
            $query->where('id', $specificId);
        } else {
            // Kun payslips uden løn (medmindre --id er angivet)
            $query->whereNull('salary');
        }

        if ($limit) {
            $query->limit((int) $limit);
        }

        $payslips = $query->get();

        if ($payslips->isEmpty()) {
            $this->warn('Ingen payslips fundet uden billeder med tekst');
            return Command::SUCCESS;
        }

        $this->info("Fandt {$payslips->count()} payslip(s) til analyse");

        // Vis omkostningsestimat
        $costEstimate = $analyzer->estimateTextAnalysisCost($payslips->count());
        
        $this->newLine();
        $this->info('💰 Omkostningsestimat:');
        $this->line("   Antal payslips: {$costEstimate['payslip_count']}");
        $this->line("   Estimeret pris: \${$costEstimate['estimated_cost_usd']} USD (~{$costEstimate['estimated_cost_dkk']} DKK)");
        $this->line("   Model: gpt-4o-mini");
        $this->newLine();

        if ($estimate) {
            $this->info('✓ Kun estimat - ingen analyse udført');
            return Command::SUCCESS;
        }

        $this->newLine();

        // Analyser hver payslip
        $successCount = 0;
        $failCount = 0;
        $salaryFoundCount = 0;
        $experienceFoundCount = 0;

        $progressBar = $this->output->createProgressBar($payslips->count());
        $progressBar->start();

        foreach ($payslips as $payslip) {
            try {
                $extractedData = $analyzer->analyzeSalaryFromText($payslip);

                if ($extractedData) {
                    $successCount++;
                    
                    $this->newLine();
                    $this->line("✓ Payslip #{$payslip->id}: {$payslip->title}");
                    
                    if (isset($extractedData['salary']) && $extractedData['salary'] !== null) {
                        $salaryFoundCount++;
                        $this->line("  Løn fundet: " . number_format($extractedData['salary'], 2, ',', '.') . " DKK");
                    }
                    
                    if (isset($extractedData['experience']) && $extractedData['experience'] !== null) {
                        $experienceFoundCount++;
                        $this->line("  Erfaring fundet: {$extractedData['experience']} år");
                    }
                } else {
                    $failCount++;
                    $this->newLine();
                    $this->line("⚠ Payslip #{$payslip->id}: Kunne ikke finde løn eller erfaring i teksten");
                }

            } catch (\Exception $e) {
                $failCount++;
                $this->newLine();
                $this->error("✗ Fejl ved analyse af Payslip #{$payslip->id}: {$e->getMessage()}");
                
                Log::error('Fejl ved tekstanalyse af payslip', [
                    'payslip_id' => $payslip->id,
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
        $this->info('✅ Analyse afsluttet!');
        $this->info('📊 Resultat:');
        $this->line("   • Total analyseret: {$payslips->count()}");
        $this->line("   • Succesfulde: {$successCount}");
        $this->line("   • Løn fundet: {$salaryFoundCount}");
        $this->line("   • Erfaring fundet: {$experienceFoundCount}");
        $this->line("   • Fejlede: {$failCount}");
        $this->line("   • Estimeret omkostning: \${$costEstimate['estimated_cost_usd']} USD");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        Log::info('Payslip tekstanalyse afsluttet', [
            'total' => $payslips->count(),
            'success' => $successCount,
            'salary_found' => $salaryFoundCount,
            'experience_found' => $experienceFoundCount,
            'failed' => $failCount,
            'estimated_cost_usd' => $costEstimate['estimated_cost_usd'],
        ]);

        return Command::SUCCESS;
    }
}

