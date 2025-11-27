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
            $table->integer('company_pension_dkk')->nullable()->after('salary');
            $table->decimal('company_pension_procent', 5, 2)->nullable()->after('company_pension_dkk');
            $table->integer('salary_supplement')->nullable()->after('company_pension_procent');
            $table->integer('hours_monthly')->nullable()->after('salary_supplement');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn([
                'company_pension_dkk',
                'company_pension_procent',
                'salary_supplement',
                'hours_monthly',
            ]);
        });
    }
};
