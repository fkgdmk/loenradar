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
        DB::table('job_titles')
            ->where('name_en', 'Software Developer')
            ->update(['name_en' => 'Software Developer / Engineer']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('job_titles')
            ->where('name_en', 'Software Developer / Engineer')
            ->update(['name_en' => 'Software Developer']);
    }
};
