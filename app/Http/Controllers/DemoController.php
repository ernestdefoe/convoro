<?php

namespace App\Http\Controllers;

use App\Jobs\ProvisionDemoJob;
use App\Mail\DemoRequestedAdminMail;
use App\Models\DemoWaitlist;
use App\Models\Tenant;
use App\Support\DemoProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

/** POC — public "request a demo" flow. */
class DemoController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('RequestDemo', [
            'activeCount' => DemoProvisioner::activeCount(),
            'maxActive' => DemoProvisioner::MAX_ACTIVE,
            'slotsFree' => DemoProvisioner::hasFreeSlot(),
            'waitlistCount' => DemoWaitlist::count(),
            'ttlDays' => DemoProvisioner::TTL_DAYS,
        ]);
    }

    public function request(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:160']]);
        $email = mb_strtolower(trim($data['email']));

        if (Tenant::query()->activeDemos()->where('email', $email)->exists()) {
            return back()->with('status', __('You already have an active demo — check your inbox for the link.'));
        }
        if (DemoWaitlist::where('email', $email)->exists()) {
            return back()->with('status', __('You’re already on the waitlist — we’ll email you when a slot opens up.'));
        }

        if (DemoProvisioner::hasFreeSlot()) {
            // Reserve the slot now (counts against the cap immediately), then
            // build it out on the queue — the email goes out when it's ready.
            $tenant = DemoProvisioner::reserve($email);
            ProvisionDemoJob::dispatch($tenant);
            $this->notifyAdmin($email, 'provisioned', DemoProvisioner::url($tenant));

            return back()->with('status', __('Your demo is being set up — we’ll email the login details to :email in a few minutes.', ['email' => $email]));
        }

        DemoWaitlist::create(['email' => $email]);
        $this->notifyAdmin($email, 'waitlisted', null);

        return back()->with('status', __('All demos are busy right now — you’re #:n on the waitlist. We’ll email you the moment one frees up.', ['n' => DemoWaitlist::count()]));
    }

    /** Heads-up to the team on every request (best-effort — never blocks the user). */
    private function notifyAdmin(string $requester, string $outcome, ?string $url): void
    {
        $to = config('convoro.demo_notify');
        if (! $to) {
            return;
        }
        try {
            Mail::to($to)->send(new DemoRequestedAdminMail(
                $requester, $outcome, $url, DemoProvisioner::activeCount(), DemoWaitlist::count(),
            ));
        } catch (\Throwable) {
            // notification is non-critical — swallow so the requester still succeeds
        }
    }
}
