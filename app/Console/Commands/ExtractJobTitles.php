<?php

namespace App\Console\Commands;

use App\Models\Payslip;
use App\Services\JobTitleExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExtractJobTitles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payslips:extract-job-titles 
                            {--limit= : Antal payslips der skal processeres}
                            {--force : Genekstrahér job titler der allerede har en}
                            {--id= : Ekstrahér kun et specifikt payslip ID}
                            {--estimate : Vis kun omkostningsestimat}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ekstrahér job titler fra payslips ved hjælp af OpenAI';

    /**
     * Execute the console command.
     */
    public function handle(JobTitleExtractor $extractor): int
    {
        $limit = $this->option('limit');
        $force = $this->option('force');
        $specificId = $this->option('id');
        $estimate = $this->option('estimate');

        $this->info('🔍 Ekstraherer job titler fra payslips med OpenAI...');
        $this->newLine();

        // Byg query
        $query = Payslip::query();

        if ($specificId) {
            $query->where('id', $specificId);
        } elseif (!$force) {
            // Kun payslips uden job titel
            $query->whereNull('job_title_id');
        }

        // Kun payslips med titel eller beskrivelse
        $query->where(function ($q) {
            $q->whereNotNull('title')
              ->orWhereNotNull('description');
        });

        if ($limit) {
            $query->limit((int) $limit);
        }

        $payslips = $query->get();

        if ($payslips->isEmpty()) {
            $this->warn('Ingen payslips fundet' . (!$force ? ' uden job titel' : ''));
            return Command::SUCCESS;
        }

        $this->info("Fandt {$payslips->count()} payslip(s) til ekstraktion");

        // Vis omkostningsestimat
        $costEstimate = $extractor->estimateCost($payslips->count());

        $this->newLine();
        $this->info('💰 Omkostningsestimat:');
        $this->line("   Antal payslips: {$costEstimate['payslip_count']}");
        $this->line("   Estimeret pris: \${$costEstimate['estimated_cost_usd']} USD (~{$costEstimate['estimated_cost_dkk']} DKK)");
        $this->line("   Model: gpt-4o-mini");
        $this->newLine();

        if ($estimate) {
            $this->info('✓ Kun estimat - ingen ekstraktion udført');
            return Command::SUCCESS;
        }

        $this->newLine();

        // Ekstrahér job titler
        $successCount = 0;
        $failCount = 0;

        $progressBar = $this->output->createProgressBar($payslips->count());
        $progressBar->start();

        foreach ($payslips as $payslip) {
            try {
                $jobTitel = $extractor->extractJobTitle($payslip);

                if ($jobTitel) {
                    $successCount++;
                    $this->newLine();
                    $this->line("✓ Payslip #{$payslip->id}: {$payslip->title}");
                    $this->line("  Job titel: {$jobTitel->name}");
                } else {
                    $failCount++;
                    $this->newLine();
                    $this->line("⚠ Payslip #{$payslip->id}: Kunne ikke ekstrahere job titel");
                }

            } catch (\Exception $e) {
                $failCount++;
                $this->newLine();
                $this->error("✗ Fejl ved ekstraktion for Payslip #{$payslip->id}: {$e->getMessage()}");

                Log::error('Fejl ved job titel ekstraktion', [
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
        $this->info('✅ Ekstraktion afsluttet!');
        $this->info('📊 Resultat:');
        $this->line("   • Total processeret: {$payslips->count()}");
        $this->line("   • Succesfulde: {$successCount}");
        $this->line("   • Fejlede: {$failCount}");
        $this->line("   • Estimeret omkostning: \${$costEstimate['estimated_cost_usd']} USD");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        Log::info('Job titel ekstraktion afsluttet', [
            'total' => $payslips->count(),
            'success' => $successCount,
            'failed' => $failCount,
            'estimated_cost_usd' => $costEstimate['estimated_cost_usd'],
        ]);

        return Command::SUCCESS;
    }
}
