<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $levels = [
            'Medarbejder',
            'Specialist',
            'Faglig leder',
            'Personaleleder',
        ];

        $now = now();
        
        foreach ($levels as $level) {
            DB::table('responsibility_levels')->insertOrIgnore([
                'name' => $level,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $levels = [
            'Medarbejder',
            'Specialist',
            'Faglig leder',
            'Personaleleder',
        ];

        DB::table('responsibility_levels')->whereIn('name', $levels)->delete();
    }
};
