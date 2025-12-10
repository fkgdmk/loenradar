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
        // Define comprehensive skills for each IT job title
        $jobTitleSkillMappings = [
            // 1: Softwareudvikler
            1 => [
                'JavaScript', 'TypeScript', 'Python', 'Java', 'C#', 'PHP', 'Ruby', 'Go', 'Rust', 'Swift', 'Kotlin', 'Dart', 'C', 'C++',
                'React', 'Vue.js', 'Angular', 'Next.js', 'Nuxt.js', 'Svelte', 'React Native', 'Flutter', 'Ionic', 'Expo',
                'HTML/CSS', 'Tailwind CSS', 'SASS/SCSS',
                'Node.js', 'Laravel', 'Django', 'Spring Boot', '.NET', 'Express.js', 'FastAPI', 'Ruby on Rails',
                'MySQL', 'PostgreSQL', 'MongoDB', 'Redis',
                'Git', 'GitHub Actions', 'Docker', 'Kubernetes', 'CI/CD', 'AWS', 'Azure',
                'Unit Testing', 'Integrationstest', 'E2E Testing', 'Performancetest', 'Penetrationtest', 'Jest', 'Cypress',
                'Figma', 'UI/UX Design',
                'Agile', 'Scrum', 'Jira', 'Confluence',
                'System Design', 'Microservices', 'API Design', 'Mentoring', 'Code Review', 'Pair Programming',
            ],
            
            // 2: Frontend Udvikler
            2 => [
                'JavaScript', 'TypeScript', 'Python', 'Java', 'C#', 'PHP', 'Ruby', 'Go', 'Rust', 'Swift', 'Kotlin', 'Dart', 'C', 'C++',
                'React', 'Vue.js', 'Angular', 'Next.js', 'Nuxt.js', 'Svelte', 'React Native', 'Flutter', 'Ionic', 'Expo',
                'HTML/CSS', 'Tailwind CSS', 'SASS/SCSS',
                'Node.js', 'Laravel', 'Django', 'Spring Boot', '.NET', 'Express.js', 'FastAPI', 'Ruby on Rails',
                'MySQL', 'PostgreSQL', 'MongoDB', 'Redis',
                'Git', 'GitHub Actions', 'Docker', 'Kubernetes', 'CI/CD', 'AWS', 'Azure',
                'Unit Testing', 'Integrationstest', 'E2E Testing', 'Performancetest', 'Penetrationtest', 'Jest', 'Cypress',
                'Figma', 'UI/UX Design',
                'Agile', 'Scrum', 'Jira', 'Confluence',
                'System Design', 'Microservices', 'API Design', 'Mentoring', 'Code Review', 'Pair Programming',
            ],
            
            // 3: Backend Udvikler
            3 => [
                'JavaScript', 'TypeScript', 'Python', 'Java', 'C#', 'PHP', 'Ruby', 'Go', 'Rust', 'Swift', 'Kotlin', 'Dart', 'C', 'C++',
                'React', 'Vue.js', 'Angular', 'Next.js', 'Nuxt.js', 'Svelte', 'React Native', 'Flutter', 'Ionic', 'Expo',
                'HTML/CSS', 'Tailwind CSS', 'SASS/SCSS',
                'Node.js', 'Laravel', 'Django', 'Spring Boot', '.NET', 'Express.js', 'FastAPI', 'Ruby on Rails',
                'MySQL', 'PostgreSQL', 'MongoDB', 'Redis',
                'Git', 'GitHub Actions', 'Docker', 'Kubernetes', 'CI/CD', 'AWS', 'Azure',
                'Unit Testing', 'Integrationstest', 'E2E Testing', 'Performancetest', 'Penetrationtest', 'Jest', 'Cypress',
                'Figma', 'UI/UX Design',
                'Agile', 'Scrum', 'Jira', 'Confluence',
                'System Design', 'Microservices', 'API Design', 'Mentoring', 'Code Review', 'Pair Programming',
            ],
            
            // 4: Full-stack Udvikler
            4 => [
                'JavaScript', 'TypeScript', 'Python', 'Java', 'C#', 'PHP', 'Ruby', 'Go', 'Rust', 'Swift', 'Kotlin', 'Dart', 'C', 'C++',
                'React', 'Vue.js', 'Angular', 'Next.js', 'Nuxt.js', 'Svelte', 'React Native', 'Flutter', 'Ionic', 'Expo',
                'HTML/CSS', 'Tailwind CSS', 'SASS/SCSS',
                'Node.js', 'Laravel', 'Django', 'Spring Boot', '.NET', 'Express.js', 'FastAPI', 'Ruby on Rails',
                'MySQL', 'PostgreSQL', 'MongoDB', 'Redis',
                'Git', 'GitHub Actions', 'Docker', 'Kubernetes', 'CI/CD', 'AWS', 'Azure',
                'Unit Testing', 'Integrationstest', 'E2E Testing', 'Performancetest', 'Penetrationtest', 'Jest', 'Cypress',
                'Figma', 'UI/UX Design',
                'Agile', 'Scrum', 'Jira', 'Confluence',
                'System Design', 'Microservices', 'API Design', 'Mentoring', 'Code Review', 'Pair Programming',
            ],
            
            // 5: Data Scientist
            5 => [
                'Python', 'R', 'SQL',
                'Dataanalyse', 'Machine Learning', 'Datavisualisering',
                'Tableau', 'Power BI', 'Excel', 'Excel Avanceret',
                'PostgreSQL', 'MongoDB', 'Elasticsearch',
                'AWS', 'Azure', 'GCP',
                'Git', 'Docker',
                'Jupyter', 'Pandas', 'NumPy', 'Scikit-learn', 'TensorFlow', 'PyTorch',
            ],
            
            // 6: Data Analyst
            6 => [
                'SQL', 'Python', 'R',
                'Dataanalyse', 'Datavisualisering', 'Machine Learning',
                'Tableau', 'Power BI', 'Excel', 'Excel Avanceret',
                'MySQL', 'PostgreSQL',
                'Google Analytics',
                'Git',
            ],
            
            // 7: Data Engineer
            7 => [
                'Python', 'Java', 'Scala', 'SQL',
                'PostgreSQL', 'MongoDB', 'Elasticsearch', 'Redis',
                'AWS', 'Azure', 'GCP',
                'Docker', 'Kubernetes', 'CI/CD',
                'Terraform', 'Ansible',
                'Git', 'GitHub Actions',
                'Apache Spark', 'Apache Kafka', 'Airflow',
            ],
            
            // 8: DevOps Engineer
            8 => [
                'Python', 'Bash', 'Go', 'PowerShell',
                'AWS', 'Azure', 'GCP', 'Cloudflare',
                'Docker', 'Kubernetes', 'Helm', 'CI/CD',
                'Terraform', 'Ansible', 'Chef', 'Puppet',
                'Git', 'GitHub Actions', 'Jenkins', 'GitLab CI', 'ArgoCD',
                'Linux', 'Windows Server',
                'ELK Stack', 'Prometheus', 'Grafana', 'Nagios', 'Datadog',
                'Istio', 'Service Mesh',
                'Networking', 'Load Balancing', 'VPN',
                'Vault',
            ],
            
            // 9: IT-Supporter
            9 => [
                'Windows', 'Linux', 'macOS',
                'Help Desk',
                'Active Directory', 'Office 365', 'Google Workspace',
                'Azure', 'GCP', 'AWS',
                'Networking', 'VPN', 'Firewall',
                'Hardware Support',
                'Java', 'Python', 'JavaScript', 'TypeScript', 'C', 'C++', 'PHP', 'C#'
            ],
            
            // 10: Systemadministrator
            10 => [
                'Linux', 'Windows Server',
                'AWS', 'Azure', 'GCP',
                'Docker', 'Kubernetes',
                'MySQL', 'PostgreSQL',
                'Overvågning', 'Backup & Recovery',
                'Active Directory', 'LDAP',
                'Networking', 'Firewall', 'VPN',
                'Git', 'CI/CD',
                'Ansible', 'Puppet',
            ],
            
            // 11: IT-Projektleder
            11 => [
                'Agile', 'Scrum', 'Kanban', 'Waterfall', 'Lean',
                'Jira', 'Confluence', 'Trello', 'Asana',
                'Projektstyring', 'Risikostyring',
                'Stakeholder Management',
                'Budgettering', 'Resource Management',
                'Change Management', 'Vendor Management',
                'Team Ledelse', 'Performance Management',
            ],
            
            // 12: Product Owner
            12 => [
                'Agile', 'Scrum', 'Kanban', 'Waterfall', 'Lean',
                'Jira', 'Confluence', 'Trello', 'Asana',
                'Projektstyring', 'Risikostyring',
                'Product Management',
                'User Stories', 'Backlog Management',
                'Stakeholder Management',
                'Budgettering', 'Resource Management',
                'Change Management', 'Vendor Management',
                'Team Ledelse', 'Performance Management',
                'Dataanalyse', 'Google Analytics',
            ],
            
            // 13: Scrum Master
            13 => [
                'Agile', 'Scrum', 'Kanban', 'Waterfall', 'Lean',
                'Jira', 'Confluence', 'Trello', 'Asana',
                'Projektstyring', 'Risikostyring',
                'Stakeholder Management',
                'Budgettering', 'Resource Management',
                'Change Management',
                'Facilitation', 'Coaching',
                'Team Development', 'Team Ledelse', 'Performance Management',
            ],
            
            // 14: QA Engineer / Software Tester
            14 => [
                'Unit Testing', 'Integrationstest', 'E2E Testing', 'Testautomatisering', 'Performancetest', 'Penetrationstest',
                'Jest', 'Cypress', 'Selenium', 'Playwright',
                'Python', 'JavaScript', 'Java',
                'Git', 'CI/CD',
                'Jira', 'Confluence',
                'Agile', 'Scrum',
                'Java', 'Python', 'JavaScript', 'TypeScript', 'C', 'C++', 'PHP', 'C#'
            ],
            
            // 15: IT-Sikkerhedsspecialist
            15 => [
                'Cybersikkerhed', 'Penetrationstest', 'Sikkerhedsanalyse',
                'AWS', 'Azure', 'GCP',
                'Linux', 'Windows Server',
                'Networking', 'Firewall', 'VPN',
                'SIEM', 'SOC',
                'Compliance', 'GDPR',
                'Java', 'Python', 'JavaScript', 'TypeScript', 'C', 'C++', 'PHP', 'C#'
            ],
            
            // 42: IT-Systemkonsulent
            42 => [
                'Windows', 'Linux',
                'AWS', 'Azure', 'GCP',
                'MySQL', 'PostgreSQL', 'SQL Server',
                'Docker', 'Kubernetes',
                'Networking',
                'Projektstyring', 'Agile', 'Scrum',
                'Jira', 'Confluence',
                'Java', 'Python', 'JavaScript', 'TypeScript', 'C', 'C++', 'PHP', 'C#'
            ],
            
            // 47: UX/UI Designer
            47 => [
                'Figma', 'Adobe XD', 'Sketch', 'InVision',
                'UI/UX Design', 'Prototyping',
                'User Research', 'Usability Testing',
                'HTML/CSS', 'JavaScript',
                'Design Systems',
                'Agile', 'Scrum', 'Jira',
            ],
            
            // 50: Teknisk Direktør (CTO)
            50 => [
                'Agile', 'Scrum', 'Kanban', 'Waterfall', 'Lean',
                'Jira', 'Confluence', 'Trello', 'Asana',
                'Projektstyring', 'Risikostyring',
                'Stakeholder Management',
                'Budgettering', 'Resource Management',
                'Change Management', 'Vendor Management',
                'Team Ledelse', 'Performance Management',
                'Strategi', 'Strategisk Planlægning',
                'Java', 'Python', 'JavaScript', 'TypeScript', 'C', 'C++', 'PHP', 'C#',
                'MySQL', 'PostgreSQL', 'MongoDB',
                'AWS', 'Azure', 'GCP',
                'Docker', 'Kubernetes', 'CI/CD',
                'Git',
            ],
            
            // 53: IT-Konsulent
            53 => [
                'JavaScript', 'TypeScript', 'Python', 'Java', 'C#', 'PHP', 'Ruby', 'Go', 'Rust', 'Swift', 'Kotlin', 'Dart', 'C', 'C++',
                'React', 'Vue.js', 'Angular', 'Next.js', 'Nuxt.js', 'Svelte', 'React Native', 'Flutter', 'Ionic', 'Expo',
                'HTML/CSS', 'Tailwind CSS', 'SASS/SCSS',
                'Node.js', 'Laravel', 'Django', 'Spring Boot', '.NET', 'Express.js', 'FastAPI', 'Ruby on Rails',
                'MySQL', 'PostgreSQL', 'MongoDB', 'Redis',
                'Git', 'GitHub Actions', 'Docker', 'Kubernetes', 'CI/CD', 'AWS', 'Azure',
                'Unit Testing', 'Integrationstest', 'E2E Testing', 'Performancetest', 'Penetrationtest', 'Jest', 'Cypress',
                'Figma', 'UI/UX Design',
                'Agile', 'Scrum', 'Jira', 'Confluence',
                'System Design', 'Microservices', 'API Design', 'Mentoring', 'Code Review', 'Pair Programming',
            ],
            
            // 55: IT-Arkitekt
            55 => [
                'JavaScript', 'TypeScript', 'Python', 'Java', 'C#', 'PHP', 'Ruby', 'Go', 'Rust', 'Swift', 'Kotlin', 'Dart', 'C', 'C++',
                'React', 'Vue.js', 'Angular', 'Next.js', 'Nuxt.js', 'Svelte', 'React Native', 'Flutter', 'Ionic', 'Expo',
                'HTML/CSS', 'Tailwind CSS', 'SASS/SCSS',
                'Node.js', 'Laravel', 'Django', 'Spring Boot', '.NET', 'Express.js', 'FastAPI', 'Ruby on Rails',
                'MySQL', 'PostgreSQL', 'MongoDB', 'Redis',
                'Git', 'GitHub Actions', 'Docker', 'Kubernetes', 'CI/CD', 'AWS', 'Azure',
                'Unit Testing', 'Integrationstest', 'E2E Testing', 'Performancetest', 'Penetrationtest', 'Jest', 'Cypress',
                'Figma', 'UI/UX Design',
                'Agile', 'Scrum', 'Jira', 'Confluence',
                'System Design', 'Microservices', 'API Design', 'Mentoring', 'Code Review', 'Pair Programming',
            ],
            
            // 56: Tech Lead
            56 => [
                'JavaScript', 'TypeScript', 'Python', 'Java', 'C#', 'PHP', 'Ruby', 'Go', 'Rust', 'Swift', 'Kotlin', 'Dart', 'C', 'C++',
                'React', 'Vue.js', 'Angular', 'Next.js', 'Nuxt.js', 'Svelte', 'React Native', 'Flutter', 'Ionic', 'Expo',
                'HTML/CSS', 'Tailwind CSS', 'SASS/SCSS',
                'Node.js', 'Laravel', 'Django', 'Spring Boot', '.NET', 'Express.js', 'FastAPI', 'Ruby on Rails',
                'MySQL', 'PostgreSQL', 'MongoDB', 'Redis',
                'Git', 'GitHub Actions', 'Docker', 'Kubernetes', 'CI/CD', 'AWS', 'Azure',
                'Unit Testing', 'Integrationstest', 'E2E Testing', 'Performancetest', 'Penetrationtest', 'Jest', 'Cypress',
                'Figma', 'UI/UX Design',
                'Agile', 'Scrum', 'Jira', 'Confluence',
                'System Design', 'Microservices', 'API Design', 'Mentoring', 'Code Review', 'Pair Programming', 'Team Ledelse', 'Performance Management',
            ],
            
            // 59: Teknisk Projektleder
            59 => [
                'Agile', 'Scrum', 'Kanban', 'Waterfall', 'Lean',
                'Jira', 'Confluence', 'Trello', 'Asana',
                'Projektstyring', 'Risikostyring',
                'Stakeholder Management',
                'Budgettering', 'Resource Management',
                'Change Management', 'Vendor Management',
                'Team Ledelse', 'Performance Management',
                'Java', 'Python', 'JavaScript', 'TypeScript', 'C', 'C++', 'PHP', 'C#',
                'React', 'Vue.js', 'Angular',
                'MySQL', 'PostgreSQL',
                'AWS', 'Azure',
                'Docker', 'Kubernetes', 'CI/CD',
                'Git',
            ],
            
            // 72: IT Direktør
            72 => [
                'Agile', 'Scrum', 'Kanban', 'Waterfall', 'Lean',
                'Jira', 'Confluence', 'Trello', 'Asana',
                'Projektstyring', 'Risikostyring',
                'Stakeholder Management',
                'Budgettering', 'Resource Management',
                'Change Management', 'Vendor Management',
                'Team Ledelse', 'Performance Management',
                'Strategi', 'Strategisk Planlægning',
                'JavaScript', 'TypeScript', 'Python', 'Java', 'C#',
                'React', 'Vue.js', 'Angular',
                'Node.js', 'Laravel', 'Django', 'Spring Boot', '.NET',
                'MySQL', 'PostgreSQL', 'MongoDB',
                'AWS', 'Azure', 'GCP',
                'Docker', 'Kubernetes', 'CI/CD',
                'Git',
            ],
            
            // 84: Engineering Manager
            84 => [
                'Agile', 'Scrum', 'Kanban', 'Waterfall', 'Lean',
                'Jira', 'Confluence', 'Trello', 'Asana',
                'Projektstyring', 'Risikostyring',
                'Stakeholder Management',
                'Budgettering', 'Resource Management',
                'Change Management', 'Vendor Management',
                'Team Ledelse', 'Performance Management',
                'Code Review', 'Mentoring',
                'Recruiting', 'Hiring',
                'JavaScript', 'TypeScript', 'Python', 'Java', 'C#',
                'React', 'Vue.js', 'Angular',
                'Node.js', 'Laravel', 'Django', 'Spring Boot', '.NET',
                'MySQL', 'PostgreSQL', 'MongoDB',
                'AWS', 'Azure', 'GCP',
                'Docker', 'Kubernetes', 'CI/CD',
                'Git', 'GitHub Actions',
                'Unit Testing', 'Integrationstest', 'E2E Testing',
            ],
        ];

        // Create all unique skills that don't exist yet
        $allSkills = [];
        foreach ($jobTitleSkillMappings as $skills) {
            $allSkills = array_merge($allSkills, $skills);
        }
        $allSkills = array_unique($allSkills);

        foreach ($allSkills as $skillName) {
            Skill::firstOrCreate(['name' => $skillName]);
        }

        // Associate skills with job titles
        foreach ($jobTitleSkillMappings as $jobTitleId => $skillNames) {
            $jobTitle = JobTitle::find($jobTitleId);
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
        // Get the job title IDs that were modified
        $jobTitleIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 42, 47, 50, 53, 55, 56, 59, 72, 84];
        
        // Remove all skill associations for these job titles
        foreach ($jobTitleIds as $jobTitleId) {
            $jobTitle = JobTitle::find($jobTitleId);
            if ($jobTitle) {
                $jobTitle->skills()->detach();
            }
        }
        
        // Note: We don't delete the skills themselves as they might be used by other job titles
        // If you want to delete specific skills that were only created for these roles,
        // you would need to track which skills were newly created vs. which already existed
    }
};
