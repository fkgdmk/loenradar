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
        $areas = [
            'Salg & Marketing',
            'IT & Teknologi',
            'Finans & Økonomi',
            'HR & Administration',
            'Produktion & Logistik',
            'Kundeservice & Support',
            'Andet',
        ];

        $now = now();
        
        foreach ($areas as $area) {
            DB::table('area_of_responsibilities')->insertOrIgnore([
                'name' => $area,
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
        $areas = [
            'Salg & Marketing',
            'IT & Teknologi',
            'Finans & Økonomi',
            'HR & Administration',
            'Produktion & Logistik',
            'Kundeservice & Support',
            'Andet',
        ];

        DB::table('area_of_responsibilities')->whereIn('name', $areas)->delete();
    }
};
