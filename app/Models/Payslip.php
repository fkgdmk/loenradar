<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'comments',
        'url',
        'job_title_id',
        'area_of_responsibility_id',
        'region_id',
        'sub_job_title',
        'source',
        'uploaded_at',
        'salary',
        'verified_at',
        'denied_at',
        'experience',
        'uploader_id',
        'responsibility_level_id',
        'team_size',
        'gender',
        'company_pension_dkk',
        'company_pension_procent',
        'salary_supplement',
        'hours_monthly',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'uploaded_at' => 'datetime',
        'verified_at' => 'datetime',
        'denied_at' => 'datetime',
        'comments' => 'array',
        'company_pension_procent' => 'decimal:2',
    ];

    protected $appends = [
        'total_salary_dkk',
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

    /**
     * Marker payslip som afvist
     */
    public function markAsDenied(): self
    {
        $this->update(['denied_at' => now()]);
        return $this;
    }

    /**
     * Tjek om payslip er afvist
     */
    public function isDenied(): bool
    {
        return $this->denied_at !== null;
    }

    /**
     * Get the job title for this payslip
     */
    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class, 'job_title_id');
    }

    /**
     * Get the area of responsibility for this payslip
     */
    public function areaOfResponsibility(): BelongsTo
    {
        return $this->belongsTo(AreaOfResponsibility::class, 'area_of_responsibility_id');
    }

    /**
     * Get the region for this payslip
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    /**
     * Get the reports for this payslip
     */
    public function reports(): BelongsToMany
    {
        return $this->belongsToMany(Report::class, 'payslip_report', 'payslip_id', 'report_id');
    }

    /**
     * Get the user who uploaded this payslip
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    /**
     * Get the responsibility level for this payslip
     */
    public function responsibilityLevel(): BelongsTo
    {
        return $this->belongsTo(ResponsibilityLevel::class, 'responsibility_level_id');
    }

    public function getTotalSalaryDkkAttribute(): int
    {
        return $this->salary + $this->company_pension_dkk;
    }
}
