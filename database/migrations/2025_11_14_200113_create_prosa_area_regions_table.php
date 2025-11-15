<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ProsaArea;
use App\Models\Region;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prosa_area_regions', function (Blueprint $table) {
            $table->foreignId('area_id')->constrained('prosa_areas')->onDelete('cascade');
            $table->foreignId('region_id')->constrained('regions')->onDelete('cascade');
            $table->primary(['area_id', 'region_id']);
        });
        
        $østForStorebælt = ProsaArea::where('area_name', 'Øst for Storebælt')->first();
        $vestForStorebælt = ProsaArea::where('area_name', 'Vest for Storebælt')->first();

        $sjællandOgFyn = ['Storkøbenhavn', 'Øvrige Sjælland & Øer', 'Fyn'];

        $regions = Region::whereIn('name', $sjællandOgFyn)->get();
        foreach ($regions as $region) {
            $østForStorebælt->regions()->attach($region->id);
        }

        $regions = Region::whereNotIn('name', $sjællandOgFyn)->get();
        foreach ($regions as $region) {
            $vestForStorebælt->regions()->attach($region->id);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prosa_area_regions');
    }
};
