<?php

namespace App\Http\Controllers\Groups;

use App\Http\Controllers\Controller;
use App\Models\SocialGroup;
use App\Models\SocialGroupJoinRequest;
use App\Models\SocialGroupMember;
use App\Models\User;
use App\Notifications\GroupActivityNotification;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GroupMembershipController extends Controller
{
    public function join(Request $request, SocialGroup $group): RedirectResponse
    {
        $actor = $request->user();
        abort_if($actor->isBanned(), 403, __('Your account is suspended.'));
        abort_if($group->isBanned($actor), 403, __('You are banned from this group.'));
        abort_unless($group->isVisibleTo($actor), 404);

        if ($group->isActiveMember($actor)) {
            return back();
        }

        if ($group->membership_type === 'invite') {
            return back()->with('status', __('This group is invite-only.'));
        }

        if ($group->membership_type === 'approval') {
            SocialGroupJoinRequest::updateOrCreate(
                ['social_group_id' => $group->id, 'user_id' => $actor->id],
                ['status' => 'pending', 'message' => mb_substr(trim((string) $request->input('message', '')), 0, 500) ?: null],
            );
            // Tell the owner + moderators someone wants in.
            $this->notifyStaff($group, new GroupActivityNotification($group, $actor, 'join_request'));

            return back()->with('status', __('Your request to join was sent.'));
        }

        $this->addMember($group, $actor->id);

        return back()->with('status', __('You joined the group.'));
    }

    public function leave(Request $request, SocialGroup $group): RedirectResponse
    {
        $actor = $request->user();
        abort_if((int) $actor->id === (int) $group->user_id, 403, __('The owner cannot leave their own group.'));

        $member = $group->members()->where('user_id', $actor->id)->first();
        if ($member) {
            $wasActive = $member->banned_at === null;
            $member->delete();
            if ($wasActive) {
                $group->decrement('member_count');
            }
        }

        return back()->with('status', __('You left the group.'));
    }

    public function approve(Request $request, SocialGroup $group, SocialGroupJoinRequest $joinRequest): RedirectResponse
    {
        abort_unless($group->canModerate($request->user()), 403);
        abort_unless((int) $joinRequest->social_group_id === (int) $group->id, 404);

        if ($joinRequest->status === 'pending') {
            $this->addMember($group, $joinRequest->user_id);
            $joinRequest->update(['status' => 'approved']);
            if ($u = User::find($joinRequest->user_id)) {
                Notifier::send($u, new GroupActivityNotification($group, $request->user(), 'request_approved'));
            }
        }

        return back()->with('status', __('Request approved.'));
    }

    public function reject(Request $request, SocialGroup $group, SocialGroupJoinRequest $joinRequest): RedirectResponse
    {
        abort_unless($group->canModerate($request->user()), 403);
        abort_unless((int) $joinRequest->social_group_id === (int) $group->id, 404);
        $joinRequest->update(['status' => 'rejected']);

        return back()->with('status', __('Request declined.'));
    }

    public function invite(Request $request, SocialGroup $group): RedirectResponse
    {
        abort_unless($group->canModerate($request->user()), 403);
        $data = $request->validate(['user' => ['required', 'string', 'max:120']]);

        $target = User::where('name', $data['user'])->orWhere('email', $data['user'])->first();
        abort_unless($target, 422, __('No member found by that name.'));

        if (! $group->isActiveMember($target) && ! $group->isBanned($target)) {
            $this->addMember($group, $target->id);
            Notifier::send($target, new GroupActivityNotification($group, $request->user(), 'invited'));
        }

        return back()->with('status', __('Member added.'));
    }

    public function kick(Request $request, SocialGroup $group, SocialGroupMember $member): RedirectResponse
    {
        abort_unless($group->canModerate($request->user()), 403);
        abort_unless((int) $member->social_group_id === (int) $group->id, 404);
        abort_if($member->role === 'owner', 403, __('The owner cannot be removed.'));

        $wasActive = $member->banned_at === null;
        $member->delete();
        if ($wasActive) {
            $group->decrement('member_count');
        }

        return back()->with('status', __('Member removed.'));
    }

    public function ban(Request $request, SocialGroup $group, SocialGroupMember $member): RedirectResponse
    {
        abort_unless($group->canModerate($request->user()), 403);
        abort_unless((int) $member->social_group_id === (int) $group->id, 404);
        abort_if($member->role === 'owner', 403, __('The owner cannot be banned.'));

        if ($member->banned_at === null) {
            $member->update(['banned_at' => now(), 'role' => 'member']);
            $group->decrement('member_count');
        }

        return back()->with('status', __('Member banned.'));
    }

    public function unban(Request $request, SocialGroup $group, SocialGroupMember $member): RedirectResponse
    {
        abort_unless($group->canModerate($request->user()), 403);
        abort_unless((int) $member->social_group_id === (int) $group->id, 404);

        if ($member->banned_at !== null) {
            $member->update(['banned_at' => null]);
            $group->increment('member_count');
        }

        return back()->with('status', __('Member reinstated.'));
    }

    public function setRole(Request $request, SocialGroup $group, SocialGroupMember $member): RedirectResponse
    {
        abort_unless($group->canManage($request->user()), 403);
        abort_unless((int) $member->social_group_id === (int) $group->id, 404);
        abort_if($member->role === 'owner', 403);

        $data = $request->validate(['role' => ['required', 'in:moderator,member']]);
        $member->update(['role' => $data['role']]);

        return back()->with('status', __('Role updated.'));
    }

    // -- helpers ------------------------------------------------------------

    private function addMember(SocialGroup $group, int $userId): void
    {
        $member = $group->members()->where('user_id', $userId)->first();
        if ($member) {
            // Reinstate a previously-banned/left member.
            if ($member->banned_at !== null) {
                $member->update(['banned_at' => null]);
                $group->increment('member_count');
            }

            return;
        }
        $group->members()->create(['user_id' => $userId, 'role' => 'member', 'joined_at' => now()]);
        $group->increment('member_count');
    }

    private function notifyStaff(SocialGroup $group, $notification): void
    {
        $staffIds = $group->members()->whereNull('banned_at')
            ->whereIn('role', ['owner', 'moderator'])->pluck('user_id');
        User::whereIn('id', $staffIds)->get()->each(fn (User $u) => Notifier::send($u, $notification));
    }
}
