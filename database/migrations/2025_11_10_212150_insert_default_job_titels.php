<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $jobTitles = [
            // IT & Software
            'Softwareudvikler',
            'Frontend Udvikler',
            'Backend Udvikler',
            'Full-stack Udvikler',
            'Data Scientist',
            'Data Analyst',
            'Data Engineer',
            'DevOps Engineer',
            'IT-Supporter',
            'Systemadministrator',
            'IT-Projektleder',
            'Product Owner',
            'Scrum Master',
            'QA Engineer / Software Tester',
            'IT-Sikkerhedsspecialist',
            
            // Salg & Marketing
            'Salgskonsulent',
            'Account Manager',
            'Key Account Manager',
            'Marketingkoordinator',
            'Digital Marketing Specialist',
            'SoMe Manager',
            'E-commerce Manager',
            'Content Manager',
            'Brand Manager',
            'Salgschef',
            'Marketingchef',
            
            // Økonomi & Regnskab
            'Bogholder',
            'Lønbogholder',
            'Controller',
            'Business Controller',
            'Financial Controller',
            'Økonomiassistent',
            'Regnskabschef',
            
            // HR & Administration
            'HR-Partner / HR-Konsulent',
            'Rekrutteringskonsulent',
            'Administrativ Medarbejder',
            'Executive Assistant / PA',
            
            // Ledelse & Management
            'Projektleder',
            'Product Manager',
            'Afdelingsleder',
            'HR Chef',
            'IT-Systemkonsulent',
            'Manager',
            'Teamleder / Team Lead'
        ];

        $now = now();
        
        foreach ($jobTitles as $title) {
            DB::table('job_titles')->insertOrIgnore([
                'name' => $title,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $jobTitles = [
            'Softwareudvikler',
            'Frontend Udvikler',
            'Backend Udvikler',
            'Full-stack Udvikler',
            'Data Scientist',
            'Data Analyst',
            'Data Engineer',
            'DevOps Engineer',
            'IT-Supporter',
            'Systemadministrator',
            'IT-Projektleder',
            'Product Owner',
            'Scrum Master',
            'QA Engineer / Software Tester',
            'IT-Sikkerhedsspecialist',
            'Salgskonsulent',
            'Account Manager',
            'Key Account Manager',
            'Marketingkoordinator',
            'Digital Marketing Specialist',
            'SoMe Manager',
            'E-commerce Manager',
            'Content Manager',
            'Brand Manager',
            'Salgschef',
            'Marketingchef',
            'Bogholder',
            'Lønbogholder',
            'Controller',
            'Business Controller',
            'Financial Controller',
            'Økonomiassistent',
            'Regnskabschef',
            'HR-Partner / HR-Konsulent',
            'Rekrutteringskonsulent',
            'Administrativ Medarbejder',
            'Executive Assistant / PA',
            'Projektleder',
            'Product Manager',
            'Afdelingsleder',
            'HR Chef',
            'IT-Systemkonsulent',
            'Manager',
            'Teamleder / Team Lead'
        ];

        DB::table('job_titles')->whereIn('name', $jobTitles)->delete();
    }
};
