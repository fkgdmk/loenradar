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
        // Find Director job title
        $director = DB::table('job_titles')
            ->where(function($query) {
                $query->where('name', 'Director')
                      ->orWhere('name_en', 'Director');
            })
            ->first();

        if ($director) {
            // Sæt alle payslips med denne job_title_id til null
            DB::table('payslips')
                ->where('job_title_id', $director->id)
                ->update(['job_title_id' => null]);

            // Slet også relationer fra job_postings hvis de findes
            DB::table('job_postings')
                ->where('job_title_id', $director->id)
                ->update(['job_title_id' => null]);

            // Slet relationer fra job_title_prosa_category_mapping
            DB::table('job_title_prosa_category_mapping')
                ->where('job_title_id', $director->id)
                ->delete();

            // Slet relationer fra job_title_skill
            DB::table('job_title_skill')
                ->where('job_title_id', $director->id)
                ->delete();

            // Slet Director job title
            DB::table('job_titles')
                ->where('id', $director->id)
                ->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Genopret Director job title hvis den ikke findes
        $director = DB::table('job_titles')
            ->where(function($query) {
                $query->where('name', 'Director')
                      ->orWhere('name_en', 'Director');
            })
            ->first();

        if (!$director) {
            DB::table('job_titles')->insert([
                'name' => 'Director',
                'name_en' => 'Director',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
