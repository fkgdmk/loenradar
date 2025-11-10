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
            // Fjern den gamle job_titel kolonne
            // $table->dropColumn('job_title');
            
            // Tilføj foreign key til job_titels
            $table->foreignId('job_title_id')
                ->nullable()
                ->after('url')
                ->constrained('job_titles')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            // Fjern foreign key
            $table->dropForeign(['job_title_id']);
            $table->dropColumn('job_title_id');
            
            // Gendan job_titel kolonne
        });
    }
};
