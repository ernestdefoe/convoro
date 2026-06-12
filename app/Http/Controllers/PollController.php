<?php

namespace App\Http\Controllers;

use App\Models\Poll;
use App\Support\Present;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PollController extends Controller
{
    /** Cast (or change) a member's vote, then return the fresh results. */
    public function vote(Request $request, Poll $poll): JsonResponse
    {
        abort_if($poll->isClosed(), 422, __('This poll has closed.'));

        $optionIds = $poll->options()->pluck('id')->all();
        $max = max(1, (int) $poll->max_choices);
        $data = $request->validate([
            'options' => ['required', 'array', 'min:1', 'max:'.max(1, count($optionIds))],
            'options.*' => ['integer', Rule::in($optionIds)],
        ]);

        $chosen = array_slice(array_values(array_unique($data['options'])), 0, $max);

        DB::transaction(function () use ($poll, $request, $chosen) {
            // Re-voting replaces the member's previous choices.
            DB::table('poll_votes')->where('poll_id', $poll->id)->where('user_id', $request->user()->id)->delete();
            $now = now();
            foreach ($chosen as $oid) {
                DB::table('poll_votes')->insert([
                    'poll_id' => $poll->id, 'option_id' => $oid, 'user_id' => $request->user()->id,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        });

        return response()->json(['poll' => Present::poll($poll->fresh(), $request->user()->id)]);
    }
}
