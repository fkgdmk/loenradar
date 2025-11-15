<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ProsaJobCategory;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prosa_job_categories', function (Blueprint $table) {
            $table->id();
            $table->string('category_name', 255)->unique();
            $table->timestamps();
        });

        ProsaJobCategory::create([
            'category_name' => 'Plan',
        ]);

        ProsaJobCategory::create([
            'category_name' => 'Build',
        ]);

        ProsaJobCategory::create([
            'category_name' => 'Run',
        ]);

        ProsaJobCategory::create([
            'category_name' => 'Management',
        ]);

        ProsaJobCategory::create([
            'category_name' => 'Enable',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prosa_job_categories');
    }
};
