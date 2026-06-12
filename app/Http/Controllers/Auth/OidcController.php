<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Oidc;
use App\Support\Settings;
use App\Support\Username;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** Generic OpenID Connect SSO — authorization-code login flow. */
class OidcController extends Controller
{
    /** Kick off the login: stash CSRF state, redirect to the provider. */
    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(Oidc::enabled(), 404);

        $state = Str::random(40);
        $nonce = Str::random(40);
        $request->session()->put('oidc.state', $state);
        $request->session()->put('oidc.nonce', $nonce);

        try {
            return redirect()->away(Oidc::authUrl($state, $nonce, $this->redirectUri()));
        } catch (\Throwable $e) {
            return redirect('/')->with('status', __('Single sign-on is unavailable right now.'));
        }
    }

    /** Handle the provider's callback: verify, fetch profile, log in / create. */
    public function callback(Request $request): RedirectResponse
    {
        abort_unless(Oidc::enabled(), 404);

        if ($request->query('error')) {
            return redirect('/')->with('status', __('Sign-in was cancelled.'));
        }

        // CSRF: the returned state must match what we stored.
        $state = (string) $request->query('state');
        if ($state === '' || $state !== $request->session()->pull('oidc.state')) {
            return redirect('/')->with('status', __('Sign-in could not be verified. Please try again.'));
        }
        $code = (string) $request->query('code');
        if ($code === '') {
            return redirect('/')->with('status', __('Sign-in failed.'));
        }

        try {
            $tokens = Oidc::exchange($code, $this->redirectUri());
            $profile = Oidc::profile($tokens);
        } catch (\Throwable $e) {
            return redirect('/')->with('status', __('We couldn’t complete sign-in: :msg', ['msg' => $e->getMessage()]));
        }

        $sub = (string) ($profile['sub'] ?? '');
        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        if ($email === '') {
            return redirect('/')->with('status', __('Your identity provider didn’t share an email address, which is required to sign in.'));
        }

        // Optional restriction to a single email domain.
        $domain = strtolower(trim((string) Settings::get('sso.allowed_domain', '')));
        if ($domain !== '' && ! str_ends_with($email, '@'.$domain)) {
            return redirect('/')->with('status', __('Only :domain accounts can sign in here.', ['domain' => $domain]));
        }

        $user = ($sub !== '' ? User::where('oidc_sub', $sub)->first() : null)
            ?? User::where('email', $email)->first();

        if (! $user) {
            if (! Settings::get('sso.auto_create', true)) {
                return redirect('/')->with('status', __('No account matches that sign-in. Ask an admin for an invitation.'));
            }
            $user = User::create([
                'name' => Username::sanitize((string) ($profile['name'] ?? $profile['preferred_username'] ?? Str::before($email, '@'))),
                'email' => $email,
                'password' => bcrypt(Str::random(40)),
                'registration_ip' => $request->ip(),
                'last_ip' => $request->ip(),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save(); // came from a verified IdP
        }

        // Link the stable subject id (so future logins survive an email change).
        if ($sub !== '' && $user->oidc_sub !== $sub) {
            $user->forceFill(['oidc_sub' => $sub])->save();
        }

        if ($user->isBanned()) {
            return redirect('/')->with('status', __('Your account is suspended.'));
        }

        Auth::login($user, true);

        return redirect()->intended(route('forum.index', absolute: false));
    }

    private function redirectUri(): string
    {
        return route('oidc.callback');
    }
}
