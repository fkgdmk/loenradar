<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Report extends Model
{
    /** @use HasFactory<\Database\Factories\ReportFactory> */
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'job_title_id',
        'sub_job_title',
        'experience',
        'area_of_responsibility_id',
        'region_id',
        'user_id',
        'uploaded_payslip_id',
        'status',
        'lower_percentile',
        'median',
        'upper_percentile',
        'conclusion',
        'filters',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'lower_percentile' => 'decimal:2',
        'median' => 'decimal:2',
        'upper_percentile' => 'decimal:2',
        'filters' => 'array',
    ];

    /**
     * Get the job title for this report
     */
    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class, 'job_title_id');
    }

    /**
     * Get the area of responsibility for this report
     */
    public function areaOfResponsibility(): BelongsTo
    {
        return $this->belongsTo(AreaOfResponsibility::class, 'area_of_responsibility_id');
    }

    /**
     * Get the region for this report
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    /**
     * Get the user for this report
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the payslips for this report
     */
    public function payslips(): BelongsToMany
    {
        return $this->belongsToMany(Payslip::class, 'payslip_report', 'report_id', 'payslip_id');
    }

    /**
     * Get the uploaded payslip for this report
     */
    public function uploadedPayslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class, 'uploaded_payslip_id');
    }

    /**
     * Get the job postings for this report
     */
    public function jobPostings(): BelongsToMany
    {
        return $this->belongsToMany(JobPosting::class, 'report_job_posting', 'report_id', 'job_posting_id')
            ->withPivot('match_score');
    }
}
