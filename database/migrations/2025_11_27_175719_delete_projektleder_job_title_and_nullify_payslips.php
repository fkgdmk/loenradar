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
        // Find Projektleder job title
        $projektleder = DB::table('job_titles')
            ->where(function($query) {
                $query->where('name', 'Projektleder')
                      ->orWhere('name_en', 'Projektleder');
            })
            ->first();

        if ($projektleder) {
            // Sæt alle payslips med denne job_title_id til null
            DB::table('payslips')
                ->where('job_title_id', $projektleder->id)
                ->update(['job_title_id' => null]);

            // Slet også relationer fra job_postings hvis de findes
            DB::table('job_postings')
                ->where('job_title_id', $projektleder->id)
                ->update(['job_title_id' => null]);

            // Slet relationer fra job_title_prosa_category_mapping
            DB::table('job_title_prosa_category_mapping')
                ->where('job_title_id', $projektleder->id)
                ->delete();

            // Slet relationer fra job_title_skill
            DB::table('job_title_skill')
                ->where('job_title_id', $projektleder->id)
                ->delete();

            // Slet Projektleder job title
            DB::table('job_titles')
                ->where('id', $projektleder->id)
                ->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Genopret Projektleder job title hvis den ikke findes
        $projektleder = DB::table('job_titles')
            ->where(function($query) {
                $query->where('name', 'Projektleder')
                      ->orWhere('name_en', 'Projektleder');
            })
            ->first();

        if (!$projektleder) {
            DB::table('job_titles')->insert([
                'name' => 'Projektleder',
                'name_en' => 'Projektleder',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
