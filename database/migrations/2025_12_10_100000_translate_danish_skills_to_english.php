<?php

use App\Models\Skill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Mapping of Danish skills to English translations
        $translations = [
            // Data & Analytics
            'Dataanalyse' => 'Data Analysis',
            'Datavisualisering' => 'Data Visualization',
            
            // Testing & QA
            'Integrationstest' => 'Integration Testing',
            'Testautomatisering' => 'Test Automation',
            'Performancetest' => 'Performance Testing',
            'Penetrationstest' => 'Penetration Testing',
            
            // Project Management
            'Projektstyring' => 'Project Management',
            'Risikostyring' => 'Risk Management',
            'Team Ledelse' => 'Team Leadership',
            
            // Finance & Accounting
            'Finansiel analyse' => 'Financial Analysis',
            'Budgettering' => 'Budgeting',
            'Regnskab' => 'Accounting',
            'Excel Avanceret' => 'Advanced Excel',
            'Bogføring' => 'Bookkeeping',
            'Moms' => 'VAT',
            'Løn' => 'Payroll',
            'Årsrapporter' => 'Annual Reports',
            
            // HR
            'Rekruttering' => 'Recruitment',
            'HR Ledelse' => 'HR Management',
            
            // IT Support & Security
            'Fejlfinding' => 'Troubleshooting',
            'Overvågning' => 'Monitoring',
            'Cybersikkerhed' => 'Cybersecurity',
            'Sikkerhedsanalyse' => 'Security Analysis',
            
            // Sales & Marketing
            'Sociale medier' => 'Social Media',
            'Salg' => 'Sales',
            'Forhandling' => 'Negotiation',
            'Kunderelationer' => 'Customer Relations',
            'Kundehåndtering' => 'Customer Service',
        ];
        
        // Update each skill
        foreach ($translations as $danishName => $englishName) {
            $skill = Skill::where('name', $danishName)->first();
            
            if ($skill) {
                // Check if English version already exists
                $existingEnglish = Skill::where('name', $englishName)->first();
                
                if ($existingEnglish) {
                    // If English version exists, merge relationships and delete Danish version
                    // Get all job titles associated with Danish skill
                    $jobTitleRelations = DB::table('job_title_skill')
                        ->where('skill_id', $skill->id)
                        ->get();
                    
                    // Get all job postings associated with Danish skill
                    $jobPostingRelations = DB::table('job_posting_skill')
                        ->where('skill_id', $skill->id)
                        ->get();
                    
                    // Migrate job title relations
                    foreach ($jobTitleRelations as $relation) {
                        // Check if relation already exists for English skill
                        $existingRelation = DB::table('job_title_skill')
                            ->where('job_title_id', $relation->job_title_id)
                            ->where('skill_id', $existingEnglish->id)
                            ->first();
                        
                        if (!$existingRelation) {
                            // Create new relation with English skill
                            DB::table('job_title_skill')->insert([
                                'job_title_id' => $relation->job_title_id,
                                'skill_id' => $existingEnglish->id,
                            ]);
                        }
                        
                        // Delete old relation
                        DB::table('job_title_skill')
                            ->where('job_title_id', $relation->job_title_id)
                            ->where('skill_id', $skill->id)
                            ->delete();
                    }
                    
                    // Migrate job posting relations
                    foreach ($jobPostingRelations as $relation) {
                        // Check if relation already exists for English skill
                        $existingRelation = DB::table('job_posting_skill')
                            ->where('job_posting_id', $relation->job_posting_id)
                            ->where('skill_id', $existingEnglish->id)
                            ->first();
                        
                        if (!$existingRelation) {
                            // Create new relation with English skill
                            DB::table('job_posting_skill')->insert([
                                'job_posting_id' => $relation->job_posting_id,
                                'skill_id' => $existingEnglish->id,
                            ]);
                        }
                        
                        // Delete old relation
                        DB::table('job_posting_skill')
                            ->where('job_posting_id', $relation->job_posting_id)
                            ->where('skill_id', $skill->id)
                            ->delete();
                    }
                    
                    // Delete Danish skill
                    $skill->delete();
                } else {
                    // If English version doesn't exist, just rename
                    $skill->name = $englishName;
                    $skill->save();
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse mapping of English skills back to Danish
        $reverseTranslations = [
            'Data Analysis' => 'Dataanalyse',
            'Data Visualization' => 'Datavisualisering',
            'Integration Testing' => 'Integrationstest',
            'Test Automation' => 'Testautomatisering',
            'Performance Testing' => 'Performancetest',
            'Penetration Testing' => 'Penetrationstest',
            'Project Management' => 'Projektstyring',
            'Risk Management' => 'Risikostyring',
            'Team Leadership' => 'Team Ledelse',
            'Financial Analysis' => 'Finansiel analyse',
            'Budgeting' => 'Budgettering',
            'Accounting' => 'Regnskab',
            'Advanced Excel' => 'Excel Avanceret',
            'Bookkeeping' => 'Bogføring',
            'VAT' => 'Moms',
            'Payroll' => 'Løn',
            'Annual Reports' => 'Årsrapporter',
            'Recruitment' => 'Rekruttering',
            'HR Management' => 'HR Ledelse',
            'Troubleshooting' => 'Fejlfinding',
            'Monitoring' => 'Overvågning',
            'Cybersecurity' => 'Cybersikkerhed',
            'Security Analysis' => 'Sikkerhedsanalyse',
            'Social Media' => 'Sociale medier',
            'Sales' => 'Salg',
            'Negotiation' => 'Forhandling',
            'Customer Relations' => 'Kunderelationer',
            'Customer Service' => 'Kundehåndtering',
        ];
        
        foreach ($reverseTranslations as $englishName => $danishName) {
            $skill = Skill::where('name', $englishName)->first();
            
            if ($skill) {
                $skill->name = $danishName;
                $skill->save();
            }
        }
    }
};
