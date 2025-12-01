<?php

namespace App\Console\Commands;

use App\Models\Payslip;
use Illuminate\Console\Command;

class UpdatePayslipPension extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payslips:update-pension 
                            {--dry-run : Vis kun hvad der ville blive opdateret uden at gemme ændringer}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Opdaterer payslips: beregner company_pension_dkk fra company_pension_procent og normaliserer løn til 160 timer';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 DRY-RUN mode: Ingen ændringer vil blive gemt');
            $this->newLine();
        }

        // Opdater company_pension_dkk baseret på company_pension_procent
        $this->info('📊 Opdaterer company_pension_dkk fra company_pension_procent...');
        $this->updateCompanyPensionDKK($dryRun);

        $this->newLine();

        // Normaliser løn til 160 timer
        $this->info('⏰ Normaliserer løn til 160 timer...');
        $this->normalizeSalaryTo160Hours($dryRun);

        $this->newLine();
        $this->info('✅ Opdatering afsluttet!');

        return Command::SUCCESS;
    }

    /**
     * Opdaterer company_pension_dkk baseret på company_pension_procent
     */
    private function updateCompanyPensionDKK(bool $dryRun): void
    {
        $payslips = Payslip::query()
            ->whereNull('verified_at')
            ->whereNull('denied_at')
            ->whereNotNull('company_pension_procent')
            ->whereNull('company_pension_dkk')
            ->whereNotNull('salary')
            ->get();

        $count = $payslips->count();

        if ($count === 0) {
            $this->info('   Ingen payslips fundet der skal opdateres.');
            return;
        }

        $this->info("   Fundet {$count} payslip(s) der skal opdateres.");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $updated = 0;

        foreach ($payslips as $payslip) {
            $companyPensionDKK = (int) round($payslip->salary * ($payslip->company_pension_procent / 100));

            if (!$dryRun) {
                $payslip->update(['company_pension_dkk' => $companyPensionDKK]);
            }

            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($dryRun) {
            $this->info("   Ville opdatere {$updated} payslip(s).");
        } else {
            $this->info("   Opdateret {$updated} payslip(s).");
        }
    }

    /**
     * Normaliserer løn til 160 timer for payslips med hours_monthly < 160
     */
    private function normalizeSalaryTo160Hours(bool $dryRun): void
    {
        $payslips = Payslip::query()
            ->whereNotNull('hours_monthly')
            ->where('hours_monthly', '<', 160)
            ->whereNotNull('salary')
            ->where('hours_monthly', '>', 0)
            ->get();

        $count = $payslips->count();

        if ($count === 0) {
            $this->info('   Ingen payslips fundet der skal normaliseres.');
            return;
        }

        $this->info("   Fundet {$count} payslip(s) der skal normaliseres.");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $updated = 0;

        foreach ($payslips as $payslip) {
            $ratio = 160 / $payslip->hours_monthly;
            $newSalary = (int) round($payslip->salary * $ratio);

            $updates = [
                'salary' => $newSalary,
                'hours_monthly' => 160,
            ];

            // Opdater også company_pension_dkk hvis det er sat
            if ($payslip->company_pension_dkk !== null) {
                $newCompanyPensionDKK = (int) round($payslip->company_pension_dkk * $ratio);
                $updates['company_pension_dkk'] = $newCompanyPensionDKK;
            }

            if (!$dryRun) {
                $payslip->update($updates);
            }

            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($dryRun) {
            $this->info("   Ville opdatere {$updated} payslip(s).");
        } else {
            $this->info("   Opdateret {$updated} payslip(s).");
        }
    }
}

