<?php

namespace App\Http\Responses;

use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class RegisterResponse implements RegisterResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        // Check if there's a guest report token in session
        $guestToken = $request->session()->get('guest_report_token');
        $redirectUrl = config('fortify.home', '/dashboard');
        
        if ($guestToken) {
            // Find the draft report by guest token
            $report = Report::where('guest_token', $guestToken)
                ->where('status', 'draft')
                ->first();
            
            if ($report) {
                // Redirect authenticated users to /reports/create with report_id
                $redirectUrl = route('reports.create', ['report_id' => $report->id]);
            }
        }

        return $request->wantsJson()
            ? new JsonResponse(['redirect' => $redirectUrl], 201)
            : redirect()->intended($redirectUrl);
    }
}
