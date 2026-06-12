<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /** A signed-in member flags a post or user for staff review. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:post,user'],
            'id' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        // One open report per member per item — don't let the queue flood.
        $exists = Report::where('reportable_type', $data['type'])
            ->where('reportable_id', $data['id'])
            ->where('reporter_id', $request->user()->id)
            ->where('status', 'open')
            ->exists();

        if (! $exists) {
            Report::create([
                'reportable_type' => $data['type'],
                'reportable_id' => $data['id'],
                'reporter_id' => $request->user()->id,
                'reason' => $data['reason'] ?? null,
                'status' => 'open',
            ]);
        }

        return back()->with('status', __('Thanks — our moderators will take a look.'));
    }
}
