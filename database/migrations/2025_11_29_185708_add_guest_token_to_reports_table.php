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
        Schema::table('reports', function (Blueprint $table) {
            // Add guest_token for identifying guest reports
            $table->uuid('guest_token')->nullable()->after('user_id')->index();
            
            // Make user_id nullable for guest reports
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('guest_token');
            // Note: We don't change user_id back to non-nullable as it might break existing data
        });
    }
};
