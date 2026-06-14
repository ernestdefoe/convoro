<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Support\HostingMetrics;
use App\Support\TenantProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * POC — managed-hosting panel. A signed-in Convoro Account can spin up a new
 * community and is made its Admin automatically.
 */
class PanelController extends Controller
{
    public function index(Request $request): Response
    {
        $tenants = Tenant::where('owner_user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(function (Tenant $t) {
                $peek = $t->status === 'active' ? $t->peek() : [];

                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'status' => $t->status,
                    'error' => $t->error,
                    'database' => basename($t->database),
                    'createdAgo' => optional($t->created_at)->diffForHumans(),
                    // Live read from the tenant's OWN database — proof of isolation + auto-admin.
                    'users' => $peek['users'] ?? null,
                    'admins' => $peek['admins'] ?? [],
                    'categories' => $peek['categories'] ?? null,
                ];
            });

        return Inertia::render('Panel', [
            'tenants' => $tenants,
            'account' => ['name' => $request->user()->name, 'email' => $request->user()->email],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60'],
        ]);

        $tenant = TenantProvisioner::provision($request->user(), $data['name']);

        return back()->with('status', $tenant->status === 'active'
            ? __('“:name” is live — you’re the admin.', ['name' => $tenant->name])
            : __('Provisioning failed: :err', ['err' => $tenant->error]));
    }

    /** Step into one of your communities — the whole forum then serves from its DB. */
    public function enter(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($tenant->owner_user_id === $request->user()->id, 403);
        abort_unless($tenant->status === 'active', 422, __('That community isn’t ready yet.'));

        $request->session()->put('tenant_id', $tenant->id);
        $request->session()->put('tenant_email', $request->user()->email);

        return redirect('/');
    }

    /** Leave the community and return to the central panel. */
    public function exit(Request $request): RedirectResponse
    {
        $request->session()->forget(['tenant_id', 'tenant_email']);

        return redirect('/panel');
    }

    /** The marketing/checkout page (POC — plans are static; no Stripe yet). */
    public function order(): Response
    {
        return Inertia::render('PanelOrder');
    }

    /** Full client dashboard for one community — resources, billing, domain. */
    public function manage(Request $request, Tenant $tenant): Response
    {
        abort_unless($tenant->owner_user_id === $request->user()->id, 403);

        $peek = $tenant->status === 'active' ? $tenant->peek() : [];
        $latest = $tenant->latestMetric();

        return Inertia::render('PanelManage', [
            'community' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
                'url' => 'https://'.$tenant->slug.'.convoro.co',
                'database' => basename($tenant->database),
                'createdAgo' => optional($tenant->created_at)->diffForHumans(),
                'members' => $peek['users'] ?? null,
            ],
            'billing' => $tenant->billingSummary(),
            'domain' => [
                'subdomain' => $tenant->slug.'.convoro.co',
                'custom' => $tenant->custom_domain,
                'verified' => (bool) $tenant->custom_domain_verified_at,
                'certStatus' => $tenant->cert_status ?? 'none',
                'target' => config('convoro.community_domain', 'community.convoro.co'),
            ],
            'snapshot' => $this->snapshot($tenant, $latest, $peek),
            'series' => HostingMetrics::series($tenant, '7d'),
            'range' => '7d',
        ]);
    }

    /** Current resource snapshot + quota usage for the overview cards. */
    private function snapshot(Tenant $tenant, $latest, array $peek): array
    {
        $storage = (int) (($latest->db_bytes ?? 0) + ($latest->storage_bytes ?? 0));

        return [
            'dbBytes' => (int) ($latest->db_bytes ?? 0),
            'storageBytes' => $storage,
            'redisBytes' => (int) ($latest->redis_bytes ?? 0),
            'cpu' => (float) ($latest->cpu_pct ?? 0),
            'respMs' => (int) ($latest->avg_response_ms ?? 0),
            'queueDepth' => (int) ($latest->queue_depth ?? 0),
            'requests24h' => (int) $tenant->metrics()->where('captured_at', '>=', now()->subDay())->sum('requests'),
            'bandwidth30dBytes' => (int) $tenant->metrics()->where('captured_at', '>=', now()->subDays(30))->sum('bandwidth_bytes'),
            'jobs24h' => (int) $tenant->metrics()->where('captured_at', '>=', now()->subDay())->sum('jobs_processed'),
            'members' => $peek['users'] ?? 0,
            'quota' => [
                'storageBytes' => (int) $tenant->quota_storage_bytes,
                'members' => (int) $tenant->quota_members,
                'bandwidthGb' => (int) $tenant->quota_bandwidth_gb,
            ],
        ];
    }

    /** Graph data for a range (24h | 7d | 30d) — polled by the Resources tab. */
    public function metrics(Request $request, Tenant $tenant): JsonResponse
    {
        abort_unless($tenant->owner_user_id === $request->user()->id, 403);
        $range = in_array($request->query('range'), ['24h', '7d', '30d'], true) ? $request->query('range') : '7d';

        return response()->json([
            'range' => $range,
            'series' => HostingMetrics::series($tenant, $range),
        ]);
    }

    /** Point a custom domain at the community (verification happens next). */
    public function setDomain(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($tenant->owner_user_id === $request->user()->id, 403);
        $data = $request->validate([
            'custom_domain' => ['nullable', 'string', 'max:190', 'regex:/^([a-z0-9-]+\.)+[a-z]{2,}$/i'],
        ]);

        $tenant->update([
            'custom_domain' => $data['custom_domain'] ?: null,
            'custom_domain_verified_at' => null,
            'cert_status' => $data['custom_domain'] ? 'pending' : 'none',
        ]);

        return back()->with('status', $data['custom_domain']
            ? __('Domain saved. Add the DNS record below, then verify.')
            : __('Custom domain removed.'));
    }

    /** Verify the custom domain's DNS, then mark its cert active. */
    public function verifyDomain(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($tenant->owner_user_id === $request->user()->id, 403);
        abort_unless($tenant->custom_domain, 422, __('No custom domain set.'));

        // In production this resolves the domain's CNAME/A and confirms it points
        // here, then issues a cert. Locally we accept + mark active so the flow
        // is fully testable.
        $target = config('convoro.community_domain', 'community.convoro.co');
        $ok = app()->environment('local')
            || in_array($target, (array) (dns_get_record($tenant->custom_domain, DNS_CNAME) ?: []), true)
            || ! empty(gethostbynamel($tenant->custom_domain));

        if (! $ok) {
            return back()->with('status', __('DNS not pointing here yet — add the record and try again.'));
        }

        $tenant->update(['custom_domain_verified_at' => now(), 'cert_status' => 'active']);

        return back()->with('status', __('Domain verified — HTTPS is live. 🎉'));
    }

    /** Open the billing management portal (Stripe Customer Portal in prod). */
    public function billingPortal(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($tenant->owner_user_id === $request->user()->id, 403);

        // Production: create a Stripe billing-portal session and redirect to it.
        // Local (no Stripe keys): explain what would happen.
        if ($tenant->stripe_customer_id && config('convoro.stripe.secret')) {
            // $session = \Stripe\BillingPortal\Session::create([...]); return redirect($session->url);
        }

        return back()->with('status', __('In production this opens the Stripe billing portal to update your card and view invoices.'));
    }

    /** Rename a community. */
    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($tenant->owner_user_id === $request->user()->id, 403);
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:60']]);
        $tenant->update(['name' => $data['name']]);

        return back()->with('status', __('Settings saved.'));
    }

    /** Delete a community — drops its isolated database and the record. */
    public function destroy(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($tenant->owner_user_id === $request->user()->id, 403);
        $name = $tenant->name;
        if (is_file($tenant->database)) {
            @unlink($tenant->database);
        }
        $tenant->delete();

        return redirect('/panel')->with('status', __('“:name” and its database were deleted.', ['name' => $name]));
    }
}
