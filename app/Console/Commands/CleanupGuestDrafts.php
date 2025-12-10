<?php

namespace App\Console\Commands;

use App\Models\Payslip;
use App\Models\Report;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupGuestDrafts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:cleanup-guest-drafts {--days=7 : Number of days to keep drafts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete old guest report drafts that were never finalized';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);

        $this->info("Cleaning up guest drafts older than {$days} days...");

        // Find old guest drafts (no user_id, status draft, older than cutoff)
        $drafts = Report::whereNull('user_id')
            ->where('status', ReportStatus::DRAFT)
            ->where('created_at', '<', $cutoffDate)
            ->get();

        $count = $drafts->count();

        if ($count === 0) {
            $this->info('No old guest drafts found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$count} old guest draft(s) to delete.");

        $deleted = 0;
        $errors = 0;

        foreach ($drafts as $draft) {
            DB::beginTransaction();
            try {
                // Get the uploaded payslip
                $payslip = $draft->uploadedPayslip;

                // Detach payslips from report
                $draft->payslips()->detach();

                // Delete the report
                $draft->delete();

                // Delete the orphaned payslip if it exists and has no other reports
                if ($payslip) {
                    // Delete media files
                    $payslip->clearMediaCollection('documents');
                    
                    // Delete the payslip
                    $payslip->delete();
                }

                DB::commit();
                $deleted++;
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Failed to delete draft ID {$draft->id}: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->info("Deleted {$deleted} guest draft(s).");
        
        if ($errors > 0) {
            $this->warn("{$errors} draft(s) could not be deleted.");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
