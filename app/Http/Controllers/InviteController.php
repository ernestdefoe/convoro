<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** Admin: invite codes + invite-only registration. */
class InviteController extends Controller
{
    public function index(Request $request): Response
    {
        $base = rtrim((string) (Settings::get('community_domain') ? 'https://'.Settings::get('community_domain') : config('app.url')), '/');

        return Inertia::render('Admin/Invites', [
            'inviteOnly' => (bool) Settings::get('invites.only', false),
            'registerBase' => $base.'/register?invite=',
            'invites' => Invite::with('creator')->latest()->limit(100)->get()->map(fn (Invite $i) => [
                'id' => $i->id,
                'code' => $i->code,
                'note' => $i->note,
                'email' => $i->email,
                'uses' => $i->uses,
                'maxUses' => $i->max_uses,
                'expiresAt' => optional($i->expires_at)->toDateString(),
                'usable' => $i->isUsable(),
                'by' => $i->creator?->name,
                'created' => optional($i->created_at)->diffForHumans(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'max_uses' => ['required', 'integer', 'min:1', 'max:10000'],
            'expires_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        Invite::create([
            'code' => Str::upper(Str::random(10)),
            'created_by' => $request->user()->id,
            'email' => $data['email'] ?? null,
            'note' => $data['note'] ?? null,
            'max_uses' => $data['max_uses'],
            'expires_at' => ! empty($data['expires_days']) ? now()->addDays((int) $data['expires_days']) : null,
        ]);

        return back()->with('status', __('Invite created.'));
    }

    public function destroy(Invite $invite): RedirectResponse
    {
        $invite->delete();

        return back()->with('status', __('Invite revoked.'));
    }

    public function setOnly(Request $request): RedirectResponse
    {
        Settings::set('invites.only', $request->boolean('invite_only'));

        return back()->with('status', __('Saved.'));
    }
}
