<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ProsaArea;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prosa_areas', function (Blueprint $table) {
            $table->id();
            $table->string('area_name', 255)->unique();
            $table->timestamps();
        });

        ProsaArea::create([
            'area_name' => 'Øst for Storebælt',
        ]);

        ProsaArea::create([
            'area_name' => 'Vest for Storebælt',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prosa_areas');
    }
};
