<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        abort_if(\App\Models\IpBan::isBanned($request->ip()), 403, 'Registration is not available.');

        // Resolve an invite (from the Join page field or the stashed session code).
        $invite = $this->resolveInvite($request);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'registration_ip' => $request->ip(),
            'last_ip' => $request->ip(),
            'invited_by' => $invite?->inviter_id,
        ]);

        if ($invite) {
            $invite->redeem();
            $request->session()->forget('invite_code');
            \App\Support\Notifier::send(
                $invite->inviter,
                new \App\Notifications\InviteAcceptedNotification($user)
            );
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('forum.index', absolute: false));
    }

    /** A still-valid invite that accepts this email, or null. */
    private function resolveInvite(Request $request): ?\App\Models\Invite
    {
        $code = $request->input('invite') ?: $request->session()->get('invite_code');
        if (! $code) {
            return null;
        }

        $invite = \App\Models\Invite::with('inviter')->where('code', $code)->first();

        return $invite && $invite->isValid() && $invite->acceptsEmail($request->input('email', ''))
            ? $invite
            : null;
    }
}
