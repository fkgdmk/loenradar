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
        $now = now();

        DB::table('job_titles')->insertOrIgnore([
            'name' => 'Account Executive',
            'name_en' => 'Account Executive',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('job_titles')
            ->where('name', 'Account Executive')
            ->where('name_en', 'Account Executive')
            ->delete();
    }
};
