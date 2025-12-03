<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('prosa_job_categories', function (Blueprint $table) {
            $table->text('description')->nullable()->after('category_name');
        });

        $descriptions = [
            'Plan' => "Du hører hjemme her, hvis du arbejder med:\n• Arkitektur af systemer eller løsninger\n• Design (f.eks. systemdesign, UX/UI, løsningsdesign)\n• Teststrategier og planlægning\n• Analyse og kravspecifikation\n\nTypisk stilling: IT-arkitekt, forretningsanalytiker, UX-designer, testmanager.",

            'Build' => "Dette er dig, hvis du:\n• Udvikler software (backend, frontend, fullstack)\n• Arbejder med CI/CD, DevOps, automatisering\n• Tester software eller integrerer systemer\n• Arbejder med cybersikkerhed i kodebasen\n\nTypisk stilling: Softwareudvikler, DevOps engineer, systemudvikler, sikkerhedsudvikler.",

            'Run' => "Du passer her, hvis du:\n• Sørger for drift og vedligehold af systemer\n• Yder IT-support eller teknisk support\n• Arbejder med netværk, servere eller infrastruktur\n• Har ansvar for sikkerhed i driftsmiljøet\n\nTypisk stilling: IT-supporter, systemadministrator, driftsansvarlig, netværksspecialist.",

            'Enable' => "Du hører til her, hvis du:\n• Underviser eller formidler teknisk viden\n• Udarbejder dokumentation, guides, procedurer\n• Arbejder med strategi, politikker eller forretningsudvikling\n• Har ansvar for salg, kundeopfølgning eller rådgivning\n\nTypisk stilling: IT-konsulent, dokumentationsansvarlig, salgsingeniør, strategikonsulent.",

            'Management' => "Du hører hjemme her, hvis du:\n• Leder projekter eller teams\n• Har ansvar for personale eller ressourcestyring\n• Har budgetansvar eller organisatorisk ansvar\n\nTypisk stilling: Projektleder, teamleder, udviklingsleder.",
        ];

        foreach ($descriptions as $categoryName => $description) {
            DB::table('prosa_job_categories')
                ->where('category_name', $categoryName)
                ->update(['description' => $description]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prosa_job_categories', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};

