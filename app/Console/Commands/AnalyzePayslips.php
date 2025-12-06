<?php

namespace App\Console\Commands;

use App\Models\Payslip;
use App\Services\PayslipAnalyzer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class AnalyzePayslips extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payslips:analyze 
                            {--limit= : Antal payslips der skal analyseres}
                            {--id= : Analyser kun et specifikt payslip ID}
                            {--estimate : Vis kun omkostningsestimat}
                            {--fetch-reddit : Hent Reddit posts først, derefter kør hele pipelinen}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyser lønsedler med OpenAI og udtræk grundløn (basisløn uden tillæg)';

    /**
     * Execute the console command.
     */
    public function handle(PayslipAnalyzer $analyzer): int
    {
        $fetchReddit = $this->option('fetch-reddit');

        // Hvis --fetch-reddit er sat, kør hele pipelinen
        if ($fetchReddit) {
            return $this->runFullPipeline($analyzer);
        }

        // Ellers kør kun analyse
        return $this->runAnalysis($analyzer);
    }

    /**
     * Kør hele pipelinen: Fetch Reddit -> Extract Job Titles -> Analyze -> Extract Details
     */
    private function runFullPipeline(PayslipAnalyzer $analyzer): int
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('🚀 Starter fuld payslip pipeline...');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        // Trin 1: Hent Reddit posts
        $this->info('📥 TRIN 1/4: Henter Reddit posts...');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        
        $exitCode = Artisan::call('reddit:fetch-posts', [
            '--bulk' => true,
            '--bulk-limit' => $this->option('limit') ?? 200, 
            '--save' => true,
        ], $this->output);

        if ($exitCode !== Command::SUCCESS) {
            $this->error('❌ Fejl ved hentning af Reddit posts');
            return $exitCode;
        }
        
        $this->newLine();
        $this->info('✅ Reddit posts hentet succesfuldt');
        $this->newLine(2);

        // Trin 2: Ekstrahér job titler
        $this->info('📝 TRIN 2/4: Ekstraherer job titler...');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        
        $exitCode = Artisan::call('payslips:extract-job-titles', [], $this->output);

        if ($exitCode !== Command::SUCCESS) {
            $this->error('❌ Fejl ved ekstraktion af job titler');
            return $exitCode;
        }
        
        $this->newLine();
        $this->info('✅ Job titler ekstraheret succesfuldt');
        $this->newLine(2);

        // Trin 3: Analyser payslips (den eksisterende logik)
        $this->info('🔍 TRIN 3/4: Analyserer lønsedler...');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        
        $exitCode = $this->runAnalysis($analyzer);

        if ($exitCode !== Command::SUCCESS) {
            $this->error('❌ Fejl ved analyse af payslips');
            return $exitCode;
        }
        
        $this->newLine();
        $this->info('✅ Lønsedler analyseret succesfuldt');
        $this->newLine(2);

        // Trin 4: Ekstrahér payslip detaljer
        $this->info('📊 TRIN 4/4: Ekstraherer payslip detaljer...');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        
        $exitCode = Artisan::call('payslips:extract-details', [], $this->output);

        if ($exitCode !== Command::SUCCESS) {
            $this->error('❌ Fejl ved ekstraktion af payslip detaljer');
            return $exitCode;
        }
        
        $this->newLine(2);
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('🎉 FULD PIPELINE AFSLUTTET SUCCESFULDT!');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        Log::info('Fuld payslip pipeline afsluttet');

        return Command::SUCCESS;
    }

    /**
     * Kør kun analyse af payslips
     */
    private function runAnalysis(PayslipAnalyzer $analyzer): int
    {
        $limit = $this->option('limit');
        $specificId = $this->option('id');
        $estimate = $this->option('estimate');

        $this->info('🔍 Analyserer lønsedler med OpenAI Vision API...');
        $this->newLine();

        // Byg query
        $query = Payslip::whereNull('verified_at')->whereNull('denied_at')->whereNotNull('job_title_id')->has('media');

        if ($specificId) {
            $query->where('id', $specificId);
        }

        if ($limit) {
            $query->limit((int) $limit);
        }

        $payslips = $query->get();

        if ($payslips->isEmpty()) {
            $this->warn('Ingen payslips fundet med job titel og billeder');
            return Command::SUCCESS;
        }

        $this->info("Fandt {$payslips->count()} payslip(s) til analyse");

        // Vis omkostningsestimat
        $costEstimate = $analyzer->estimateCost($payslips->count());
        
        $this->newLine();
        $this->info('💰 Omkostningsestimat:');
        $this->line("   Antal billeder: {$costEstimate['image_count']}");
        $this->line("   Estimeret pris: \${$costEstimate['estimated_cost_usd']} USD (~{$costEstimate['estimated_cost_dkk']} DKK)");
        $this->line("   Model: gpt-4o-mini (billigste vision model)");
        $this->newLine();

        if ($estimate) {
            $this->info('✓ Kun estimat - ingen analyse udført');
            return Command::SUCCESS;
        }

        $this->newLine();

        // Analyser hver payslip
        $successCount = 0;
        $failCount = 0;

        $progressBar = $this->output->createProgressBar($payslips->count());
        $progressBar->start();

        foreach ($payslips as $payslip) {
            try {
                $salary = $analyzer->analyzeSalary($payslip);

                if ($salary) {
                    $successCount++;
                    $this->newLine();
                    $this->line("✓ Payslip #{$payslip->id}: {$payslip->title}");
                    $this->line("  Løn fundet: " . number_format($salary, 2, ',', '.') . " DKK");
                } else {
                    $failCount++;
                    $this->newLine();
                    $this->line("⚠ Payslip #{$payslip->id}: Kunne ikke finde løn");
                }

            } catch (\Exception $e) {
                $failCount++;
                $this->newLine();
                $this->error("✗ Fejl ved analyse af Payslip #{$payslip->id}: {$e->getMessage()}");
                
                Log::error('Fejl ved payslip analyse', [
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
        $this->line("   • Fejlede: {$failCount}");
        $this->line("   • Estimeret omkostning: \${$costEstimate['estimated_cost_usd']} USD");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        Log::info('Payslip analyse afsluttet', [
            'total' => $payslips->count(),
            'success' => $successCount,
            'failed' => $failCount,
            'estimated_cost_usd' => $costEstimate['estimated_cost_usd'],
        ]);

        return Command::SUCCESS;
    }
}
