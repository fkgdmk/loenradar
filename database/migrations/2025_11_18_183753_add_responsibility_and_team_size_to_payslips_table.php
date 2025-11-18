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
            $table->foreignId('responsibility_level_id')
                ->nullable()
                ->after('experience')
                ->constrained('responsibility_levels')
                ->nullOnDelete();
            $table->integer('team_size')->nullable()->after('responsibility_level_id');
            $table->string('gender')->nullable()->after('team_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropForeign(['responsibility_level_id']);
            $table->dropColumn(['responsibility_level_id', 'team_size', 'gender']);
        });
    }
};
