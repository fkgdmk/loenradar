<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ProsaJobCategory;
use App\Models\JobTitle;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('job_title_prosa_category_mapping', function (Blueprint $table) {
            $table->foreignId('job_title_id')->constrained('job_titles')->onDelete('cascade');
            $table->foreignId('prosa_category_id')->constrained('prosa_job_categories')->onDelete('cascade');
            $table->primary(['job_title_id', 'prosa_category_id']);
        });

        $plan = ProsaJobCategory::where('category_name', 'Plan')->first();
        $build = ProsaJobCategory::where('category_name', 'Build')->first();
        $run = ProsaJobCategory::where('category_name', 'Run')->first();
        $management = ProsaJobCategory::where('category_name', 'Management')->first();
        $enable = ProsaJobCategory::where('category_name', 'Enable')->first();

        $buildJobTitles = JobTitle::whereIn('name', ['Softwareudvikler', 'Frontend Udvikler', 'Backend Udvikler', 'Full-stack Udvikler', 'Data Engineer', 'QA Engineer / Software Tester', 'IT-Sikkerhedsspecialist'])->get();

        $planJobTitles = JobTitle::whereIn('name', ['IT-Arkitekt', 'Data Scientist', 'Data Analyst'])->get();

        $runJobTitles = JobTitle::whereIn('name', ['IT-Supporter', 'Systemadministrator'])->get();

        $managementJobTitles = JobTitle::whereIn('name', ['IT-Projektleder', 'Product Owner', 'Scrum Master'])->get();

        $enableJobTitles = JobTitle::whereIn('name', ['IT-Konsulent', 'IT-Systemkonsulent'])->get();

        foreach ($buildJobTitles as $jobTitle) {
            $jobTitle->prosaCategories()->attach($build->id);
        }
        foreach ($planJobTitles as $jobTitle) {
            $jobTitle->prosaCategories()->attach($plan->id);
        }
        foreach ($runJobTitles as $jobTitle) {
            $jobTitle->prosaCategories()->attach($run->id);
        }
        foreach ($managementJobTitles as $jobTitle) {
            $jobTitle->prosaCategories()->attach($management->id);
        }
        foreach ($enableJobTitles as $jobTitle) {
            $jobTitle->prosaCategories()->attach($enable->id);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_title_prosa_category_mapping');
    }
};
