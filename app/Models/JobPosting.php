<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class JobPosting extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'job_title_id',
        'region_id',
        'salary_from',
        'salary_to',
        'url',
        'source',
        'minimum_experience',
    ];

    /**
     * Get the job title for this job posting
     */
    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    /**
     * Get the region for this job posting
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Get the skills for this job posting
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'job_posting_skill', 'job_posting_id', 'skill_id');
    }
}
