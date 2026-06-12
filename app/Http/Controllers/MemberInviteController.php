<?php

namespace App\Http\Controllers;

use App\Mail\InviteEmail;
use App\Models\Invite;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Member-facing invitations: every member gets a personal referral link and can
 * invite friends by email. Sign-ups through a member's link/code are attributed
 * back to them (users.invited_by) so they can see who they brought in.
 */
class MemberInviteController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(self::enabled(), 403);
        $user = $request->user();
        $code = self::referralCode($user);

        return Inertia::render('Invite', [
            'link' => self::registerBase().$code,
            'code' => $code,
            'joined' => User::where('invited_by', $user->id)->count(),
            'sent' => Invite::where('created_by', $user->id)->whereNotNull('email')->latest()->limit(50)->get()
                ->map(fn (Invite $i) => [
                    'email' => $i->email,
                    'joined' => $i->uses > 0,
                    'sentAt' => optional($i->created_at)->diffForHumans(),
                ]),
        ]);
    }

    /** Send email invitations to one or more friends. */
    public function send(Request $request): RedirectResponse
    {
        abort_unless(self::enabled(), 403);
        $data = $request->validate([
            'emails' => ['required', 'array', 'min:1', 'max:10'],
            'emails.*' => ['email', 'max:255'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $base = self::registerBase();
        $sent = 0;

        foreach (array_unique($data['emails']) as $email) {
            // Skip people who are already members.
            if (User::where('email', $email)->exists()) {
                continue;
            }
            $invite = Invite::create([
                'code' => Str::upper(Str::random(10)),
                'created_by' => $user->id,
                'email' => $email,
                'note' => 'Member invite',
                'max_uses' => 1,
                'expires_at' => now()->addDays(30),
            ]);
            Mail::to($email)->queue(new InviteEmail($user->name, $base.$invite->code, $data['message'] ?? null));
            $sent++;
        }

        return back()->with('status', trans_choice('{0}No new invites were sent.|{1}Invite sent.|[2,*]:count invites sent.', $sent, ['count' => $sent]));
    }

    /** Whether members are allowed to invite (admin toggle, on by default). */
    public static function enabled(): bool
    {
        return (bool) Settings::get('invites.members_enabled', true);
    }

    /** Get or create the member's reusable personal referral code. */
    private static function referralCode(User $user): string
    {
        $invite = Invite::where('created_by', $user->id)->where('note', 'referral')->first();
        if (! $invite) {
            $invite = Invite::create([
                'code' => Str::upper(Str::random(8)),
                'created_by' => $user->id,
                'email' => null,
                'note' => 'referral',
                'max_uses' => 1000000,
                'expires_at' => null,
            ]);
        }

        return $invite->code;
    }

    private static function registerBase(): string
    {
        $base = rtrim((string) (Settings::get('community_domain') ? 'https://'.Settings::get('community_domain') : config('app.url')), '/');

        return $base.'/register?invite=';
    }
}
