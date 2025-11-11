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
        $regions = [
            'Storkøbenhavn',
            'Øvrige Sjælland & Øer',
            'Fyn',
            'Østjylland',
            'Region Sydjylland',
            'Midt-, Vest- & Nordjylland',
        ];

        $now = now();
        
        foreach ($regions as $region) {
            DB::table('regions')->insertOrIgnore([
                'name' => $region,
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
        $regions = [
            'Storkøbenhavn',
            'Øvrige Sjælland & Øer',
            'Fyn',
            'Østjylland',
            'Region Sydjylland',
            'Midt-, Vest- & Nordjylland',
        ];

        DB::table('regions')->whereIn('name', $regions)->delete();
    }
};
