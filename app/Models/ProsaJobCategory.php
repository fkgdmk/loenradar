<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProsaJobCategory extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'category_name',
        'description',
    ];

    /**
     * Get the job titles mapped to this PROSA category
     */
    public function jobTitles(): BelongsToMany
    {
        return $this->belongsToMany(JobTitle::class, 'job_title_prosa_category_mapping', 'prosa_category_id', 'job_title_id');
    }

    /**
     * Get the salary stats for this PROSA category
     */
    public function salaryStats(): HasMany
    {
        return $this->hasMany(ProsaSalaryStat::class, 'prosa_category_id');
    }
}
