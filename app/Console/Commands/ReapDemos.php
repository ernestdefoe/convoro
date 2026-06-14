<?php

namespace App\Console\Commands;

use App\Jobs\ProvisionDemoJob;
use App\Mail\DemoExpiringMail;
use App\Models\DemoWaitlist;
use App\Models\Tenant;
use App\Support\DemoProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * POC — on-demand demo lifecycle. Run on a schedule to:
 *   1. send a 24h-before-expiry reminder to active demos,
 *   2. destroy demos past their expiry, and
 *   3. promote waitlisted requesters into any slots that freed up.
 */
class ReapDemos extends Command
{
    protected $signature = 'convoro:reap-demos';

    protected $description = 'Send demo expiry reminders, destroy expired demos, and promote the waitlist.';

    public function handle(): int
    {
        $this->remind();
        $this->reap();
        $this->promote();

        return self::SUCCESS;
    }

    /** 24h-before-expiry nudge (once per demo). */
    private function remind(): void
    {
        $soon = Tenant::query()->activeDemos()
            ->whereNull('reminded_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDay())
            ->get();

        foreach ($soon as $demo) {
            try {
                if ($demo->email) {
                    Mail::to($demo->email)->send(new DemoExpiringMail(
                        DemoProvisioner::url($demo), $demo->expires_at->toDayDateTimeString(),
                    ));
                }
                $demo->update(['reminded_at' => now()]);
            } catch (\Throwable $e) {
                $this->error("Reminder for demo {$demo->id}: ".$e->getMessage());
            }
        }
        $this->info("Sent {$soon->count()} expiry reminder(s).");
    }

    /** Destroy demos that have passed their TTL, plus any that failed to build. */
    private function reap(): void
    {
        $expired = Tenant::query()->where('kind', 'demo')
            ->where(function ($q) {
                $q->whereNotNull('expires_at')->where('expires_at', '<=', now());
            })
            ->orWhere(function ($q) {
                $q->where('kind', 'demo')->where('status', 'failed');
            })
            ->get();

        foreach ($expired as $demo) {
            try {
                DemoProvisioner::destroy($demo);
            } catch (\Throwable $e) {
                $this->error("Destroying demo {$demo->id}: ".$e->getMessage());
            }
        }
        $this->info("Destroyed {$expired->count()} expired demo(s).");
    }

    /** Fill freed slots from the waitlist (oldest first). */
    private function promote(): void
    {
        $promoted = 0;
        while (DemoProvisioner::hasFreeSlot()) {
            $next = DemoWaitlist::orderBy('id')->first();
            if (! $next) {
                break;
            }
            try {
                // Reserve the slot + queue the build; the job emails them when
                // it's ready. Removing the waitlist row now frees the queue head.
                $tenant = DemoProvisioner::reserve($next->email);
                ProvisionDemoJob::dispatch($tenant);
                $next->delete();
                $promoted++;
            } catch (\Throwable $e) {
                $this->error("Promoting {$next->email}: ".$e->getMessage());
                break;
            }
        }
        $this->info("Promoted {$promoted} waitlisted requester(s).");
    }
}
