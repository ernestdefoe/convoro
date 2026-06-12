<?php

namespace App\Http\Controllers;

use App\Mail\InviteEmail;
use App\Models\Invite;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InviteController extends Controller
{
    /** Member-facing invite manager: outstanding invites + shareable link. */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $invites = $user->invites()->latest()->get()->map(fn (Invite $i) => [
            'id' => $i->id,
            'code' => $i->code,
            'email' => $i->email,
            'isLink' => $i->isLink(),
            'uses' => $i->uses,
            'maxUses' => $i->max_uses,
            'url' => url('/join/'.$i->code),
            'expired' => $i->isExpired(),
            'exhausted' => $i->isExhausted(),
            'createdAt' => optional($i->created_at)->diffForHumans(),
        ])->all();

        return Inertia::render('Invites/Index', [
            'invites' => $invites,
            'enabled' => (bool) Settings::get('invites.enabled', true),
            'remaining' => $this->remainingQuota($user),
        ]);
    }

    /** Send an email invite to a specific address. */
    public function email(Request $request): RedirectResponse
    {
        $this->ensureEnabled();
        $user = $request->user();

        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);
        $email = strtolower($data['email']);

        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'That person is already a member.']);
        }
        if ($this->remainingQuota($user) < 1) {
            throw ValidationException::withMessages(['email' => "You've used all your invites for now."]);
        }

        // Reuse a still-valid invite this member already sent to the same address.
        $invite = $user->invites()->where('email', $email)->latest()->first();
        if (! $invite || ! $invite->isValid()) {
            $invite = $user->invites()->create([
                'code' => Invite::generateCode(),
                'email' => $email,
                'max_uses' => 1,
                'expires_at' => $this->expiry(),
            ]);
        }

        Mail::to($email)->queue(new InviteEmail($invite, $user->name));

        return back()->with('status', "Invite sent to {$email}.");
    }

    /** Create (or return the existing) shareable invite link for this member. */
    public function link(Request $request): RedirectResponse
    {
        $this->ensureEnabled();
        $user = $request->user();

        $link = $user->invites()->whereNull('email')->latest()->first();
        if (! $link || ! $link->isValid()) {
            $link = $user->invites()->create([
                'code' => Invite::generateCode(),
                'email' => null,
                'max_uses' => (int) Settings::get('invites.link_max_uses', 25),
                'expires_at' => $this->expiry(),
            ]);
        }

        return back()->with('status', 'Your invite link is ready.');
    }

    /** Revoke an invite the member owns. */
    public function destroy(Request $request, Invite $invite): RedirectResponse
    {
        abort_unless($invite->inviter_id === $request->user()->id, 403);
        $invite->delete();

        return back()->with('status', 'Invite revoked.');
    }

    /** Public landing for /join/{code}: validate and tee up registration. */
    public function show(Request $request, string $code): Response|RedirectResponse
    {
        $invite = Invite::with('inviter')->where('code', $code)->first();
        $valid = $invite && $invite->isValid();

        if ($request->user()) {
            // Already a member — nothing to redeem.
            return Inertia::render('Join', [
                'valid' => false,
                'reason' => 'already_member',
            ]);
        }

        if ($valid) {
            // Stash for the registration request to consume.
            $request->session()->put('invite_code', $code);
        }

        return Inertia::render('Join', [
            'valid' => $valid,
            'reason' => $valid ? null : ($invite ? 'used' : 'invalid'),
            'inviter' => $invite?->inviter?->name,
            'email' => $valid ? $invite->email : null,
            'code' => $valid ? $code : null,
        ]);
    }

    /** Invites left for this member (email invites count against the cap). */
    private function remainingQuota(User $user): int
    {
        $cap = (int) Settings::get('invites.max_per_user', 20);
        $used = $user->invites()->whereNotNull('email')->count();

        return max(0, $cap - $used);
    }

    private function expiry(): ?\Illuminate\Support\Carbon
    {
        $days = (int) Settings::get('invites.expiry_days', 30);

        return $days > 0 ? now()->addDays($days) : null;
    }

    private function ensureEnabled(): void
    {
        abort_unless((bool) Settings::get('invites.enabled', true), 403, 'Invites are disabled.');
    }
}
