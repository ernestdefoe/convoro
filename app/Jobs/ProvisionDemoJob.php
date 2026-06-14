<?php

namespace App\Jobs;

use App\Mail\DemoReadyMail;
use App\Models\Tenant;
use App\Support\DemoProvisioner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Builds out a reserved demo (migrate + seed in a subprocess) and emails the
 * requester their login. Runs on the queue because the build takes ~20–30s.
 */
class ProvisionDemoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 240;

    public int $tries = 1;

    public function __construct(public Tenant $tenant) {}

    public function handle(): void
    {
        // Skip if the demo was torn down (e.g. expired) before we got to it.
        if (! $this->tenant->exists || $this->tenant->status === 'failed') {
            return;
        }

        $creds = DemoProvisioner::build($this->tenant);
        if (! $creds['ok']) {
            return; // status is now 'failed'; the reaper will sweep it.
        }

        Mail::to($this->tenant->email)->send(new DemoReadyMail(
            $creds['url'], $creds['login'], $creds['password'],
            $this->tenant->expires_at->toDayDateTimeString(),
        ));
    }

    public function failed(\Throwable $e): void
    {
        $this->tenant->update(['status' => 'failed']);
    }
}
