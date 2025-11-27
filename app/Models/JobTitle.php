<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobTitle extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'name_en',
        'is_active',
    ];

    /**
     * Get all payslips with this job title
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /**
     * Get the PROSA categories mapped to this job title
     */
    public function prosaCategories(): BelongsToMany
    {
        return $this->belongsToMany(ProsaJobCategory::class, 'job_title_prosa_category_mapping', 'job_title_id', 'prosa_category_id');
    }

    /**
     * Get the skills for this job title
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'job_title_skill', 'job_title_id', 'skill_id');
    }

    /**
     * Get all job postings with this job title
     */
    public function jobPostings(): HasMany
    {
        return $this->hasMany(JobPosting::class);
    }
}
