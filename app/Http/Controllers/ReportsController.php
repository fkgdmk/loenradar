<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    /**
     * Display a listing of the reports for the authenticated user.
     */
    public function index(Request $request): Response
    {
        $reports = Report::where('user_id', $request->user()->id)
            ->with(['jobTitle', 'region', 'areaOfResponsibility'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($report) {
                return [
                    'id' => $report->id,
                    'job_title' => $report->jobTitle?->name,
                    'sub_job_title' => $report->sub_job_title,
                    'experience' => $report->experience,
                    'region' => $report->region?->name,
                    'area_of_responsibility' => $report->areaOfResponsibility?->name,
                    'lower_percentile' => $report->lower_percentile,
                    'median' => $report->median,
                    'upper_percentile' => $report->upper_percentile,
                    'conclusion' => $report->conclusion,
                    'created_at' => $report->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return Inertia::render('Reports', [
            'reports' => $reports,
        ]);
    }
}
