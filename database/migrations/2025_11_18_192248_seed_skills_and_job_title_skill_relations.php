<?php

use App\Models\JobTitle;
use App\Models\Skill;
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
        $skills = [
            // Programming Languages
            'JavaScript',
            'TypeScript',
            'Python',
            'Java',
            'C#',
            'PHP',
            'Ruby',
            'Go',
            'Rust',
            'Swift',
            'Kotlin',
            
            // Frontend Technologies
            'React',
            'Vue.js',
            'Angular',
            'Next.js',
            'Nuxt.js',
            'Svelte',
            'HTML/CSS',
            'Tailwind CSS',
            'SASS/SCSS',
            
            // Backend Technologies
            'Node.js',
            'Laravel',
            'Django',
            'Spring Boot',
            '.NET',
            'Express.js',
            'FastAPI',
            'Ruby on Rails',
            
            // Databases
            'MySQL',
            'PostgreSQL',
            'MongoDB',
            'Redis',
            'Elasticsearch',
            'SQL Server',
            'Oracle',
            
            // Cloud & DevOps
            'AWS',
            'Azure',
            'GCP',
            'Docker',
            'Kubernetes',
            'CI/CD',
            'Terraform',
            'Ansible',
            'Git',
            'GitHub Actions',
            
            // Data & Analytics
            'SQL',
            'Dataanalyse',
            'Machine Learning',
            'Datavisualisering',
            'Tableau',
            'Power BI',
            'Excel',
            
            // Testing & QA
            'Unit Testing',
            'Integrationstest',
            'E2E Testing',
            'Testautomatisering',
            'Jest',
            'Cypress',
            
            // Design & UX
            'Figma',
            'Adobe XD',
            'Sketch',
            'UI/UX Design',
            'Prototyping',
            
            // Project Management
            'Agile',
            'Scrum',
            'Kanban',
            'Jira',
            'Confluence',
            'Projektstyring',
            
            // Marketing & Sales
            'SEO',
            'Google Analytics',
            'Facebook Ads',
            'Google Ads',
            'Content Marketing',
            'Email Marketing',
            'CRM',
            'Salesforce',
            
            // Finance & Accounting
            'Finansiel analyse',
            'Budgettering',
            'Regnskab',
            'SAP',
            'Excel Avanceret',
            
            // HR
            'Rekruttering',
            'HR Ledelse',
            'Performance Management',
            'Talent Management',
            
            // Additional skills referenced in mappings
            'Windows',
            'Linux',
            'Fejlfinding',
            'Help Desk',
            'Overvågning',
            'Cybersikkerhed',
            'Penetrationstest',
            'Sikkerhedsanalyse',
            'Sociale medier',
            'Salg',
            'Forhandling',
            'Account Management',
            'Kunderelationer',
            'Product Management',
        ];
        
        // Opret skills
        foreach ($skills as $skillName) {
            Skill::firstOrCreate(['name' => $skillName]);
        }

        // Knyt skills til relevante job titles
        $skillMappings = [
            'Softwareudvikler' => ['JavaScript', 'TypeScript', 'Python', 'Java', 'C#', 'PHP', 'React', 'Vue.js', 'Node.js', 'Git', 'Unit Testing'],
            'Frontend Udvikler' => ['JavaScript', 'TypeScript', 'React', 'Vue.js', 'Angular', 'HTML/CSS', 'Tailwind CSS', 'SASS/SCSS', 'Git'],
            'Backend Udvikler' => ['Python', 'Java', 'C#', 'PHP', 'Node.js', 'Laravel', 'Django', 'Spring Boot', 'MySQL', 'PostgreSQL', 'Git'],
            'Full-stack Udvikler' => ['JavaScript', 'TypeScript', 'React', 'Vue.js', 'Node.js', 'Laravel', 'Django', 'MySQL', 'PostgreSQL', 'Git', 'Docker'],
            'Data Scientist' => ['Python', 'SQL', 'Dataanalyse', 'Machine Learning', 'Datavisualisering', 'Tableau', 'Power BI'],
            'Data Analyst' => ['SQL', 'Dataanalyse', 'Datavisualisering', 'Tableau', 'Power BI', 'Excel', 'Python'],
            'Data Engineer' => ['Python', 'SQL', 'PostgreSQL', 'MongoDB', 'AWS', 'Docker', 'Kubernetes'],
            'DevOps Engineer' => ['AWS', 'Azure', 'Docker', 'Kubernetes', 'CI/CD', 'Terraform', 'Ansible', 'Git', 'GitHub Actions'],
            'IT-Supporter' => ['Windows', 'Linux', 'Fejlfinding', 'Help Desk'],
            'Systemadministrator' => ['Linux', 'Windows Server', 'AWS', 'Azure', 'Docker', 'Kubernetes', 'Overvågning'],
            'IT-Projektleder' => ['Agile', 'Scrum', 'Kanban', 'Jira', 'Confluence', 'Projektstyring'],
            'Product Owner' => ['Agile', 'Scrum', 'Jira', 'Confluence', 'Product Management'],
            'Scrum Master' => ['Agile', 'Scrum', 'Kanban', 'Jira', 'Confluence'],
            'QA Engineer / Software Tester' => ['Unit Testing', 'Integrationstest', 'E2E Testing', 'Testautomatisering', 'Jest', 'Cypress'],
            'IT-Sikkerhedsspecialist' => ['Cybersikkerhed', 'Penetrationstest', 'Sikkerhedsanalyse', 'AWS', 'Azure'],
            'UX/UI Designer' => ['Figma', 'Adobe XD', 'Sketch', 'UI/UX Design', 'Prototyping'],
            'Marketingkoordinator' => ['SEO', 'Google Analytics', 'Content Marketing', 'Email Marketing', 'Sociale medier'],
            'Digital Marketing Specialist' => ['SEO', 'Google Analytics', 'Facebook Ads', 'Google Ads', 'Content Marketing', 'Email Marketing'],
            'Salgskonsulent' => ['CRM', 'Salesforce', 'Salg', 'Forhandling'],
            'Account Manager' => ['CRM', 'Salesforce', 'Account Management', 'Kunderelationer'],
            'Controller' => ['Finansiel analyse', 'Budgettering', 'Excel Avanceret', 'SAP', 'Regnskab'],
            'HR-Partner / HR-Konsulent' => ['Rekruttering', 'HR Ledelse', 'Performance Management', 'Talent Management'],
        ];

        foreach ($skillMappings as $jobTitleName => $skillNames) {
            $jobTitle = JobTitle::where('name', $jobTitleName)->first();
            if ($jobTitle) {
                $skillIds = Skill::whereIn('name', $skillNames)->pluck('id');
                $jobTitle->skills()->syncWithoutDetaching($skillIds);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Fjern alle skills og relationer
        Skill::truncate();
        \Illuminate\Support\Facades\DB::table('job_title_skill')->truncate();
    }
};
