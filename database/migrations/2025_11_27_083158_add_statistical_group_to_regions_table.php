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
        Schema::table('regions', function (Blueprint $table) {
            $table->string('statistical_group', 50)->nullable()->after('name');
        });

        // Gruppe 1: Hovedstaden (Den dyre zone)
        DB::table('regions')
            ->where('name', 'Storkøbenhavn')
            ->update(['statistical_group' => 'Hovedstaden']);

        // Gruppe 2: Sjælland (Pendler zonen)
        DB::table('regions')
            ->where('name', 'Øvrige Sjælland & Øer')
            ->update(['statistical_group' => 'Sjælland & Øer']);

        // Gruppe 3: Vestdanmark (Den store samle-kasse)
        DB::table('regions')
            ->whereIn('name', [
                'Østjylland',
                'Midt-, Vest- & Nordjylland',
                'Region Sydjylland',
                'Fyn',
            ])
            ->update(['statistical_group' => 'Vestdanmark (Jylland & Fyn)']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->dropColumn('statistical_group');
        });
    }
};
