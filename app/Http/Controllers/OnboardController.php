<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\HostingMetrics;
use App\Support\Plans;
use App\Support\TenantProvisioner;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Get-started / order flow. A single sign-up creates a support-community account
 * (a central member) for everyone, and — for managed-hosting orders — also
 * provisions their hosted community and applies the plan. Self-hosters get the
 * same support account so they can ask for help + import their old forum.
 */
class OnboardController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('GetStarted', [
            'plans' => Plans::all(),
            'selectedPlan' => $request->query('plan'),
            'mode' => $request->query('mode') === 'self' ? 'self' : 'managed',
            'hostingAvailable' => (bool) config('convoro.hosting'),
            'communityName' => config('app.name', 'Convoro'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $managed = $request->input('mode') !== 'self';
        if ($managed && ! config('convoro.hosting')) {
            $managed = false; // hosting not offered here → self-host account only
        }

        $rules = [
            'name' => ['required', 'string', 'max:60', new \App\Rules\Username],
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];
        if ($managed) {
            $rules['plan'] = ['required', 'string'];
            $rules['community_name'] = ['required', 'string', 'min:2', 'max:60'];
        }
        $request->validate($rules);
        abort_if(\App\Models\IpBan::isBanned($request->ip()), 403, __('Sign-up is not available.'));

        // The support-community account — a central member, same for both paths.
        $user = User::create([
            'name' => \App\Support\Username::sanitize($request->name),
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'registration_ip' => $request->ip(),
            'last_ip' => $request->ip(),
        ]);
        event(new Registered($user));
        Auth::login($user);

        if (! $managed) {
            return redirect(route('forum.index', absolute: false))->with('status',
                __('Welcome! Your community account is ready. Download Convoro to self-host, then import your existing forum from Admin → Import.'));
        }

        // Managed hosting: provision the community and apply the plan.
        $plan = Plans::find($request->input('plan'));
        $tenant = TenantProvisioner::provision($user, $request->input('community_name'));
        if ($tenant->status === 'active') {
            $tenant->update([
                'kind' => 'tenant',
                'plan' => $plan['id'],
                'price_cents' => $plan['priceCents'],
                'billing_interval' => 'month',
                'billing_status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                'quota_storage_bytes' => $plan['storageBytes'],
                'quota_members' => 0,        // unlimited
                'quota_bandwidth_gb' => 0,   // unlimited
            ]);
            HostingMetrics::capture($tenant); // first usage sample
        }

        return redirect('/panel')->with('status', $tenant->status === 'active'
            ? __('“:name” is live — you’re the admin. Migrating? Import your existing forum from Admin → Import.', ['name' => $tenant->name])
            : __('Account created, but provisioning hit a snag: :err', ['err' => $tenant->error]));
    }
}
