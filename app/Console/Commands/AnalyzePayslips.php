<?php

namespace App\Console\Commands;

use App\Models\Payslip;
use App\Services\PayslipAnalyzer;
use Illuminate\Console\Command;
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
                            {--force : Genanalyser payslips der allerede har en løn}
                            {--id= : Analyser kun et specifikt payslip ID}
                            {--estimate : Vis kun omkostningsestimat}';

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
        $limit = $this->option('limit');
        $force = $this->option('force');
        $specificId = $this->option('id');
        $estimate = $this->option('estimate');

        $this->info('🔍 Analyserer lønsedler med OpenAI Vision API...');
        $this->newLine();

        // Byg query
        $query = Payslip::whereNull('verified_at')->has('media');

        if ($specificId) {
            $query->where('id', $specificId);
        } elseif (!$force) {
            // Kun payslips uden løn
            $query->whereNull('salary');
        }

        if ($limit) {
            $query->limit((int) $limit);
        }

        $payslips = $query->get();

        if ($payslips->isEmpty()) {
            $this->warn('Ingen payslips fundet med billeder' . (!$force ? ' uden løn' : ''));
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

        // Spørg om bekræftelse
        // if (!$this->confirm('Vil du fortsætte med analysen?', true)) {
        //     $this->info('Analyse annulleret');
        //     return Command::SUCCESS;
        // }

        $this->newLine();

        // Analyser hver payslip
        $successCount = 0;
        $failCount = 0;
        $totalCost = 0;

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
