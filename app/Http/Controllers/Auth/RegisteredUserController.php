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
            'name' => ['required', 'string', 'max:60', new \App\Rules\Username],
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        abort_if(\App\Models\IpBan::isBanned($request->ip()), 403, __('Registration is not available.'));

        // Invite-only registration: require a valid invite code.
        $invite = null;
        if (\App\Support\Settings::get('invites.only', false)) {
            $invite = \App\Models\Invite::usable($request->input('invite'));
            if (! $invite) {
                throw ValidationException::withMessages([
                    'invite' => __('A valid invite code is required to join this community.'),
                ]);
            }
        }

        $user = User::create([
            'name' => \App\Support\Username::sanitize($request->name),
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'registration_ip' => $request->ip(),
            'last_ip' => $request->ip(),
        ]);

        $invite?->increment('uses');

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('forum.index', absolute: false));
    }
}
