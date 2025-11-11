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
        Schema::table('payslips', function (Blueprint $table) {
            $table->foreignId('area_of_responsibility_id')
                ->nullable()
                ->after('job_title_id')
                ->constrained('area_of_responsibilities')
                ->nullOnDelete();
            
            $table->foreignId('region_id')
                ->nullable()
                ->after('area_of_responsibility_id')
                ->constrained('regions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropForeign(['area_of_responsibility_id']);
            $table->dropColumn('area_of_responsibility_id');
            
            $table->dropForeign(['region_id']);
            $table->dropColumn('region_id');
        });
    }
};
