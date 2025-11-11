<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AreaOfResponsibility extends Model
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
     * Get the payslips for this area of responsibility
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }
}
