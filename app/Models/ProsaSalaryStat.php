<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProsaSalaryStat extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'prosa_category_id',
        'prosa_area_id',
        'experience_from',
        'experience_to',
        'lower_quartile_salary',
        'median_salary',
        'upper_quartile_salary',
        'average_salary',
        'sample_size',
        'statistic_year',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'lower_quartile_salary' => 'decimal:2',
        'median_salary' => 'decimal:2',
        'upper_quartile_salary' => 'decimal:2',
        'average_salary' => 'decimal:2',
        'experience_from' => 'integer',
        'experience_to' => 'integer',
        'sample_size' => 'integer',
        'statistic_year' => 'integer',
    ];

    /**
     * Get the PROSA category for this salary stat
     */
    public function prosaCategory(): BelongsTo
    {
        return $this->belongsTo(ProsaJobCategory::class, 'prosa_category_id');
    }

    /**
     * Get the PROSA area for this salary stat
     */
    public function prosaArea(): BelongsTo
    {
        return $this->belongsTo(ProsaArea::class, 'prosa_area_id');
    }
}
