<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Get the payslips for this region
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /**
     * Get the PROSA areas that include this region
     */
    public function prosaAreas(): BelongsToMany
    {
        return $this->belongsToMany(ProsaArea::class, 'prosa_area_regions', 'region_id', 'area_id');
    }
}
