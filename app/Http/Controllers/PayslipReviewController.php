<?php

namespace App\Http\Controllers;

use App\Models\AreaOfResponsibility;
use App\Models\JobTitle;
use App\Models\Payslip;
use App\Models\Region;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PayslipReviewController extends Controller
{
    /**
     * Vis næste payslip der skal gennemgås
     */
    public function index(Request $request)
    {
        // Hvis der er en specifik payslip ID i query, vis den
        $payslipId = $request->query('payslip');
        
        if ($payslipId) {
            $payslip = Payslip::with(['jobTitle', 'areaOfResponsibility', 'region', 'media'])
                ->find($payslipId);
        } else {
            // Find næste payslip der skal gennemgås
            $payslip = Payslip::whereNotNull('job_title_id')
                ->whereNotNull('salary')
                ->whereNull('verified_at')
                ->whereNull('denied_at')
                ->with(['jobTitle', 'areaOfResponsibility', 'region', 'media'])
                ->first();
        }

        // Tæl hvor mange der mangler at blive håndteret
        $pendingCount = Payslip::whereNotNull('job_title_id')
            ->whereNotNull('salary')
            ->whereNull('verified_at')
            ->whereNull('denied_at')
            ->count();

        // Hent alle job titles, regions og areas of responsibility til dropdowns
        $jobTitles = JobTitle::orderBy('name')->get()->map(fn($jt) => [
            'id' => $jt->id,
            'name' => $jt->name,
        ]);

        $regions = Region::orderBy('name')->get()->map(fn($r) => [
            'id' => $r->id,
            'name' => $r->name,
        ]);

        $areasOfResponsibility = AreaOfResponsibility::orderBy('name')->get()->map(fn($aor) => [
            'id' => $aor->id,
            'name' => $aor->name,
        ]);

        return Inertia::render('PayslipReview', [
            'payslip' => $payslip ? [
                'id' => $payslip->id,
                'title' => $payslip->title,
                'description' => $payslip->description,
                'comments' => $payslip->comments,
                'salary' => $payslip->salary,
                'sub_job_title' => $payslip->sub_job_title,
                'experience' => $payslip->experience,
                'job_title_id' => $payslip->job_title_id,
                'job_title' => $payslip->jobTitle?->name,
                'area_of_responsibility_id' => $payslip->area_of_responsibility_id,
                'area_of_responsibility' => $payslip->areaOfResponsibility?->name,
                'region_id' => $payslip->region_id,
                'region' => $payslip->region?->name,
                'media_url' => $payslip->getFirstMediaUrl('documents'),
                'url' => $payslip->url,
            ] : null,
            'pending_count' => $pendingCount,
            'job_titles' => $jobTitles,
            'regions' => $regions,
            'areas_of_responsibility' => $areasOfResponsibility,
        ]);
    }

    /**
     * Godkend payslip
     */
    public function approve(Payslip $payslip)
    {
        $payslip->markAsVerified();

        return redirect()->route('payslips.review.index');
    }

    /**
     * Afvis payslip
     */
    public function deny(Payslip $payslip)
    {
        $payslip->markAsDenied();

        return redirect()->route('payslips.review.index');
    }

    /**
     * Opdater erfaring på payslip
     */
    public function updateExperience(Request $request, Payslip $payslip)
    {
        $validated = $request->validate([
            'experience' => 'nullable|string|max:255',
        ]);

        $payslip->update([
            'experience' => $validated['experience'],
        ]);

        return redirect()->route('payslips.review.index', ['payslip' => $payslip->id]);
    }

    /**
     * Opdater job titel på payslip
     */
    public function updateJobTitle(Request $request, Payslip $payslip)
    {
        $validated = $request->validate([
            'job_title_id' => 'required|exists:job_titles,id',
        ]);

        $payslip->update([
            'job_title_id' => $validated['job_title_id'],
        ]);

        return redirect()->route('payslips.review.index', ['payslip' => $payslip->id]);
    }

    /**
     * Opdater region på payslip
     */
    public function updateRegion(Request $request, Payslip $payslip)
    {
        $validated = $request->validate([
            'region_id' => 'required|exists:regions,id',
        ]);

        $payslip->update([
            'region_id' => $validated['region_id'],
        ]);

        return redirect()->route('payslips.review.index', ['payslip' => $payslip->id]);
    }

    /**
     * Opdater løn på payslip
     */
    public function updateSalary(Request $request, Payslip $payslip)
    {
        $validated = $request->validate([
            'salary' => 'required|numeric|min:0',
        ]);

        $payslip->update([
            'salary' => $validated['salary'],
        ]);

        return redirect()->route('payslips.review.index', ['payslip' => $payslip->id]);
    }

    /**
     * Opdater ansvarsområde på payslip
     */
    public function updateAreaOfResponsibility(Request $request, Payslip $payslip)
    {
        $validated = $request->validate([
            'area_of_responsibility_id' => 'required|exists:area_of_responsibilities,id',
        ]);

        $payslip->update([
            'area_of_responsibility_id' => $validated['area_of_responsibility_id'],
        ]);

        return redirect()->route('payslips.review.index', ['payslip' => $payslip->id]);
    }
}
