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
        // Find the Danish skill
        $danishSkill = Skill::where('name', 'Strategisk Planlægning')->first();
        
        if (!$danishSkill) {
            // Skill doesn't exist, nothing to do
            return;
        }
        
        // Find or create the English skill
        $englishSkill = Skill::firstOrCreate(
            ['name' => 'Strategic Planning'],
            ['name' => 'Strategic Planning']
        );
        
        // If they're the same skill, nothing to do
        if ($danishSkill->id === $englishSkill->id) {
            return;
        }
        
        // Get all job titles associated with Danish skill
        $jobTitleRelations = DB::table('job_title_skill')
            ->where('skill_id', $danishSkill->id)
            ->get();
        
        // Get all job postings associated with Danish skill
        $jobPostingRelations = DB::table('job_posting_skill')
            ->where('skill_id', $danishSkill->id)
            ->get();
        
        // Migrate job title relations
        foreach ($jobTitleRelations as $relation) {
            // Check if relation already exists for English skill
            $existingRelation = DB::table('job_title_skill')
                ->where('job_title_id', $relation->job_title_id)
                ->where('skill_id', $englishSkill->id)
                ->first();
            
            if (!$existingRelation) {
                // Create new relation with English skill
                DB::table('job_title_skill')->insert([
                    'job_title_id' => $relation->job_title_id,
                    'skill_id' => $englishSkill->id,
                ]);
            }
            
            // Delete old relation
            DB::table('job_title_skill')
                ->where('job_title_id', $relation->job_title_id)
                ->where('skill_id', $danishSkill->id)
                ->delete();
        }
        
        // Migrate job posting relations
        foreach ($jobPostingRelations as $relation) {
            // Check if relation already exists for English skill
            $existingRelation = DB::table('job_posting_skill')
                ->where('job_posting_id', $relation->job_posting_id)
                ->where('skill_id', $englishSkill->id)
                ->first();
            
            if (!$existingRelation) {
                // Create new relation with English skill
                DB::table('job_posting_skill')->insert([
                    'job_posting_id' => $relation->job_posting_id,
                    'skill_id' => $englishSkill->id,
                ]);
            }
            
            // Delete old relation
            DB::table('job_posting_skill')
                ->where('job_posting_id', $relation->job_posting_id)
                ->where('skill_id', $danishSkill->id)
                ->delete();
        }
        
        // Delete Danish skill
        $danishSkill->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Find the English skill
        $englishSkill = Skill::where('name', 'Strategic Planning')->first();
        
        if (!$englishSkill) {
            return;
        }
        
        // Create the Danish skill
        $danishSkill = Skill::firstOrCreate(
            ['name' => 'Strategisk Planlægning'],
            ['name' => 'Strategisk Planlægning']
        );
        
        // If they're the same skill, nothing to do
        if ($englishSkill->id === $danishSkill->id) {
            return;
        }
        
        // Get all job titles associated with English skill
        $jobTitleRelations = DB::table('job_title_skill')
            ->where('skill_id', $englishSkill->id)
            ->get();
        
        // Get all job postings associated with English skill
        $jobPostingRelations = DB::table('job_posting_skill')
            ->where('skill_id', $englishSkill->id)
            ->get();
        
        // Migrate job title relations back to Danish
        foreach ($jobTitleRelations as $relation) {
            // Check if relation already exists for Danish skill
            $existingRelation = DB::table('job_title_skill')
                ->where('job_title_id', $relation->job_title_id)
                ->where('skill_id', $danishSkill->id)
                ->first();
            
            if (!$existingRelation) {
                // Create new relation with Danish skill
                DB::table('job_title_skill')->insert([
                    'job_title_id' => $relation->job_title_id,
                    'skill_id' => $danishSkill->id,
                ]);
            }
        }
        
        // Migrate job posting relations back to Danish
        foreach ($jobPostingRelations as $relation) {
            // Check if relation already exists for Danish skill
            $existingRelation = DB::table('job_posting_skill')
                ->where('job_posting_id', $relation->job_posting_id)
                ->where('skill_id', $danishSkill->id)
                ->first();
            
            if (!$existingRelation) {
                // Create new relation with Danish skill
                DB::table('job_posting_skill')->insert([
                    'job_posting_id' => $relation->job_posting_id,
                    'skill_id' => $danishSkill->id,
                ]);
            }
        }
        
        // Note: We don't delete the English skill in down() as it might have been used elsewhere
    }
};
