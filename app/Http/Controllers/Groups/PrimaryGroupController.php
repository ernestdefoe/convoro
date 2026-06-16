<?php

namespace App\Http\Controllers\Groups;

use App\Http\Controllers\Controller;
use App\Models\SocialGroup;
use App\Models\SocialGroupPrimary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PrimaryGroupController extends Controller
{
    /** Choose (or clear) the group badge shown on your profile. */
    public function update(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $data = $request->validate([
            'group_id' => ['nullable', 'integer', 'exists:social_groups,id'],
        ]);

        $groupId = $data['group_id'] ?? null;
        if ($groupId) {
            $group = SocialGroup::find($groupId);
            abort_unless($group && $group->isActiveMember($actor), 403, __('Pick a group you belong to.'));
        }

        SocialGroupPrimary::updateOrCreate(
            ['user_id' => $actor->id],
            ['social_group_id' => $groupId],
        );

        return back()->with('status', __('Profile badge updated.'));
    }
}
