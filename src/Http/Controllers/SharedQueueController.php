<?php

namespace Leafling\SharedQueue\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Leafling\SharedQueue\Models\JobTracker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class SharedQueueController extends Controller
{
    /**
     * Display a dashboard listing all import jobs.
     */
    public function dashboard(): View
    {
        // Display jobs, automatically filtered by the active site_code global scope
        $jobs = JobTracker::latest()->paginate(25);
        return view('shared-queue::dashboard', compact('jobs'));
    }

    /**
     * Get the JSON status of a specific job.
     */
    public function status(JobTracker $job): JsonResponse
    {
        if (in_array($job->status, ['completed', 'failed'])) {
            if ($job->status === 'completed') {
                session()->flash('message', $job->message);
            } else {
                session()->flash('error', $job->message);
            }
        }

        return response()->json([
            'id' => $job->id,
            'status' => $job->status,
            'current_step' => $job->current_step,
            'total_steps' => $job->total_steps,
            'message' => $job->message,
            'step_details' => $job->step_details,
            'updated_at' => $job->updated_at->toIso8601String(),
        ]);
    }

    /**
     * Manually reset/force-fail a stuck active job.
     */
    public function reset(JobTracker $job): RedirectResponse
    {
        if (in_array($job->status, ['pending', 'running'])) {
            $job->update([
                'status' => 'failed',
                'message' => 'Job was manually reset by an administrator.',
            ]);
            return redirect()->back()->with('message', 'Job status was reset successfully.');
        }

        return redirect()->back()->with('errorMessage', 'Job is not active.');
    }
}
