<?php

namespace App\Console\Commands;

use App\Models\Payslip;
use App\Services\PayslipDetailExtractor;
use Illuminate\Console\Command;

class ExtractPayslipDetails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payslips:extract-details {--limit= : Begræns antallet af payslips der behandles}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Udtrækker pension, tillæg og timer fra verificerede lønsedler der mangler disse data';

    /**
     * Execute the console command.
     */
    public function handle(PayslipDetailExtractor $extractor)
    {
        $query = Payslip::query()
            ->whereNotNull('verified_at')
            ->whereHas('media')
            ->whereNull('company_pension_dkk'); // Antager at hvis denne er null, så mangler vi nok data eller har ikke kørt før

        $count = $query->count();
        
        if ($count === 0) {
            $this->info('Ingen lønsedler fundet der mangler detaljer.');
            return;
        }

        $this->info("Fandt {$count} lønsedler der skal behandles.");

        if ($limit = $this->option('limit')) {
            $query->limit($limit);
            $this->info("Begrænser til {$limit} lønsedler.");
        }

        $payslips = $query->get();
        $bar = $this->output->createProgressBar($payslips->count());

        $bar->start();

        foreach ($payslips as $payslip) {
            try {
                $this->line("");
                $this->info("Behandler Payslip ID: {$payslip->id}");
                
                $details = $extractor->extractDetails($payslip);
                
                if ($details) {
                    $this->info("Succes! Data fundet:");
                    if (isset($details['company_pension_dkk'])) $this->line("- Firma Pension: {$details['company_pension_dkk']} DKK");
                    if (isset($details['company_pension_procent'])) $this->line("- Firma Pension %: {$details['company_pension_procent']}%");
                    if (isset($details['salary_supplement'])) $this->line("- Faste Tillæg: {$details['salary_supplement']} DKK");
                    if (isset($details['hours_monthly'])) $this->line("- Timer: {$details['hours_monthly']}");
                } else {
                    $this->warn("Ingen data fundet eller fejl i analyse.");
                }

            } catch (\Exception $e) {
                $this->error("Fejl ved behandling af Payslip ID {$payslip->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Færdig med udtrækning af detaljer.');
    }
}
