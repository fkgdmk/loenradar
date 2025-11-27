<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Find Account Manager job title
        $accountManager = DB::table('job_titles')
            ->where('name_en', 'Account Manager')
            ->first();

        // Find Account Director / Client Director job title
        $accountDirector = DB::table('job_titles')
            ->where('name_en', 'Account Manager / Client Manager')
            ->first();

        if ($accountDirector && $accountManager) {
            // Flyt alle relationer fra Account Director / Client Director til Account Manager
            DB::table('payslips')
                ->where('job_title_id', $accountDirector->id)
                ->update(['job_title_id' => $accountManager->id]);

            DB::table('job_postings')
                ->where('job_title_id', $accountDirector->id)
                ->update(['job_title_id' => $accountManager->id]);

            // Opdater Account Manager til Account Director / Client Director
            DB::table('job_titles')
                ->where('id', $accountManager->id)
                ->update(['name_en' => 'Account Manager / Client Manager']);

            // Slet den gamle Account Director / Client Director
            DB::table('job_titles')
                ->where('id', $accountDirector->id)
                ->delete();
        } elseif ($accountDirector && !$accountManager) {
            // Hvis Account Manager ikke findes, opdater bare Account Director / Client Director
            // Dette burde ikke ske, men håndterer det alligevel
            DB::table('job_titles')
                ->where('id', $accountDirector->id)
                ->update(['name_en' => 'Account Manager / Client Manager']);
        } elseif (!$accountDirector && $accountManager) {
            // Hvis Account Director / Client Director ikke findes, opdater bare Account Manager
            DB::table('job_titles')
                ->where('id', $accountManager->id)
                ->update(['name_en' => 'Account Manager / Client Manager']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Find Account Director / Client Director job title (som nu er Account Manager)
        $accountDirector = DB::table('job_titles')
            ->where('name_en', 'Account Manager / Client Manager')
            ->first();

        if ($accountDirector) {
            // Opdater tilbage til Account Manager
            DB::table('job_titles')
                ->where('id', $accountDirector->id)
                ->update(['name_en' => 'Account Manager']);

            // Genopret Account Director / Client Director (hvis den blev slettet)
            // Vi kan ikke genoprette den præcist, da vi ikke har den originale name værdi
            // Men vi kan oprette en ny med samme name_en
            $existing = DB::table('job_titles')
                ->where('name_en', 'Account Manager / Client Manager')
                ->where('id', '!=', $accountDirector->id)
                ->first();

            if (!$existing) {
                DB::table('job_titles')->insert([
                    'name' => 'Account Manager / Client Manager',
                    'name_en' => 'Account Manager / Client Manager',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
