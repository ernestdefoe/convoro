<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\WebPushAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Best-effort browser push. Sends the WebPushAlert synchronously inside the job
 * and SWALLOWS any delivery error (stale/corrupt subscription, timeout, 4xx/5xx
 * from the push service) so it never lands in failed_jobs — the same alert is
 * always delivered in-app and by email.
 */
class SendWebPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 20;

    public function __construct(public int $userId, public array $payload) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }
        try {
            $user->notifyNow(new WebPushAlert($this->payload));
        } catch (\Throwable $e) {
            Log::debug('Web push skipped: '.$e->getMessage());
        }
    }
}
