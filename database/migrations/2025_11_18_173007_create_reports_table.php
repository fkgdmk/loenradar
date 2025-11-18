<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_title_id')->constrained('job_titles')->cascadeOnDelete();
            $table->string('sub_job_title')->nullable();
            $table->integer('experience')->nullable();
            $table->foreignId('area_of_responsibility_id')->nullable()->constrained('area_of_responsibilities')->nullOnDelete();
            $table->foreignId('region_id')->constrained('regions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('lower_percentile', 10, 2)->nullable();
            $table->decimal('median', 10, 2)->nullable();
            $table->decimal('upper_percentile', 10, 2)->nullable();
            $table->text('conclusion')->nullable();
            $table->json('filters')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
