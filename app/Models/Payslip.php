<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Payslip extends Model implements HasMedia
{
    use InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'url',
        'job_titel',
        'source',
        'uploaded_at',
        'salary',
        'verified_at',
        'experience',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'uploaded_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /**
     * Register media collections
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents')
            ->useDisk('public');
    }

    /**
     * Marker payslip som verificeret
     */
    public function markAsVerified(): self
    {
        $this->update(['verified_at' => now()]);
        return $this;
    }

    /**
     * Fjern verificering
     */
    public function unverify(): self
    {
        $this->update(['verified_at' => null]);
        return $this;
    }

    /**
     * Tjek om payslip er verificeret
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
