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
        Schema::table('job_titles', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
            $table->boolean('is_active')->default(true)->after('name_en');
        });

        // Opdater eksisterende jobtitler med engelske oversættelser
        $translations = [
            // IT & Software
            'Softwareudvikler' => 'Software Developer',
            'Frontend Udvikler' => 'Frontend Developer',
            'Backend Udvikler' => 'Backend Developer',
            'Full-stack Udvikler' => 'Full-stack Developer',
            'Data Scientist' => 'Data Scientist',
            'Data Analyst' => 'Data Analyst',
            'Data Engineer' => 'Data Engineer',
            'DevOps Engineer' => 'DevOps Engineer',
            'IT-Supporter' => 'IT Support',
            'Systemadministrator' => 'System Administrator',
            'IT-Projektleder' => 'IT Project Manager',
            'Product Owner' => 'Product Owner',
            'Scrum Master' => 'Scrum Master',
            'QA Engineer / Software Tester' => 'QA Engineer / Software Tester',
            'IT-Sikkerhedsspecialist' => 'IT Security Specialist',
            
            // Salg & Marketing
            'Salgskonsulent' => 'Sales Consultant',
            'Account Manager' => 'Account Manager',
            'Key Account Manager' => 'Key Account Manager',
            'Marketingkoordinator' => 'Marketing Coordinator',
            'Digital Marketing Specialist' => 'Digital Marketing Specialist',
            'SoMe Manager' => 'Social Media Manager',
            'E-commerce Manager' => 'E-commerce Manager',
            'Content Manager' => 'Content Manager',
            'Brand Manager' => 'Brand Manager',
            'Salgschef' => 'Sales Manager',
            'Marketingchef' => 'Marketing Manager',
            
            // Økonomi & Regnskab
            'Bogholder' => 'Accountant',
            'Lønbogholder' => 'Payroll Accountant',
            'Controller' => 'Controller',
            'Business Controller' => 'Business Controller',
            'Financial Controller' => 'Financial Controller',
            'Økonomiassistent' => 'Finance Assistant',
            'Regnskabschef' => 'Chief Accountant',
            
            // HR & Administration
            'HR-Partner / HR-Konsulent' => 'HR Partner',
            'Rekrutteringskonsulent' => 'Recruitment Consultant',
            'Administrativ Medarbejder' => 'Administrative Employee',
            'Executive Assistant / PA' => 'Executive Assistant / PA',
            
            // Ledelse & Management
            'Projektleder' => 'Project Manager',
            'Product Manager' => 'Product Manager',
            'Afdelingsleder' => 'Department Manager',
            'HR Chef' => 'HR Manager',
            'IT-Systemkonsulent' => 'IT Systems Consultant',
            'Manager' => 'Manager',
            'Teamleder / Team Lead' => 'Team Lead',
            'Director' => 'Director',
            'Finans analytiker' => 'Financial Analyst',
            'UX/UI Designer' => 'UX/UI Designer',
            'Adm. Direktør (CEO)' => 'Chief Executive Officer (CEO)',
            'Økonomi Direktør (CFO)' => 'Chief Financial Officer (CFO)',
            'Teknisk Direktør (CTO)' => 'Chief Technology Officer (CTO)',
            'Drift Direktør (COO)' => 'Chief Operating Officer (COO)',
            'Driftleder' => 'Operations Manager',
            'IT-Konsulent' => 'IT Consultant',
            'Maskinmester' => 'Machine Operator',
            'IT-Arkitekt' => 'IT Architect',
            'Tech Lead' => 'Tech Lead',
        ];

        // Opdater eksisterende rækker
        foreach ($translations as $danishName => $englishName) {
            DB::table('job_titles')
                ->where('name', $danishName)
                ->update(['name_en' => $englishName]);
        }

        // Tilføj nye jobtitler
        $newJobTitles = [
            // Project Managers
            ['name' => 'Marketing Projektleder', 'name_en' => 'Marketing Project Manager'],
            ['name' => 'HR Projektleder', 'name_en' => 'HR Project Manager'],
            ['name' => 'Teknisk Projektleder', 'name_en' => 'Technical Project Manager'],
            ['name' => 'Digital Projektleder', 'name_en' => 'Digital Project Manager'],
            ['name' => 'Kommerciel Projektleder', 'name_en' => 'Commercial Project Manager'],
            ['name' => 'Byggeprojektleder', 'name_en' => 'Construction Project Manager'],
            ['name' => 'Finans Projektleder', 'name_en' => 'Finance Project Manager'],
            
            // Managers
            ['name' => 'Customer Success Manager', 'name_en' => 'Customer Success Manager'],
            
            // Directors
            ['name' => 'Kreativ Direktør', 'name_en' => 'Creative Director'],
            ['name' => 'Kunst Direktør', 'name_en' => 'Art Director'],
            ['name' => 'Account Direktør / Kunde Direktør', 'name_en' => 'Account Director / Client Director'],
            ['name' => 'Marketing Direktør', 'name_en' => 'Marketing Director'],
            ['name' => 'Kommerciel Direktør', 'name_en' => 'Commercial Director'],
            ['name' => 'Salgs Direktør', 'name_en' => 'Sales Director'],
            ['name' => 'Finans Direktør', 'name_en' => 'Finance Director'],
            ['name' => 'IT Direktør', 'name_en' => 'IT Director'],
            ['name' => 'HR Direktør', 'name_en' => 'HR Director'],
            ['name' => 'Produkt Direktør', 'name_en' => 'Product Director'],
            ['name' => 'Customer Success Direktør', 'name_en' => 'Customer Success Director'],
            ['name' => 'E-handel Direktør', 'name_en' => 'E-commerce Director'],
            ['name' => 'Juridisk Direktør', 'name_en' => 'Legal Director'],
            
            // Partners & Counsel
            ['name' => 'HR Partner', 'name_en' => 'HR Partner'],
            ['name' => 'Juridisk Rådgiver', 'name_en' => 'Legal Counsel'],
            
            // Consultants
            ['name' => 'Management Konsulent', 'name_en' => 'Management Consultant'],
            ['name' => 'Marketing Konsulent', 'name_en' => 'Marketing Consultant'],
        ];

        $now = now();
        foreach ($newJobTitles as $jobTitle) {
            DB::table('job_titles')->insertOrIgnore([
                'name' => $jobTitle['name'],
                'name_en' => $jobTitle['name_en'],
                'is_active' => true,
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
        Schema::table('job_titles', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'is_active']);
        });
    }
};
