<?php

namespace App\Jobs;

use App\Support\Federation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sign and POST an ActivityPub activity to one or more remote inboxes. When
 * $inboxes is null it fans out to every follower (deduped by shared inbox).
 * Delivery errors are swallowed — a dead remote should never fail the job.
 */
class DeliverActivity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 30;

    /** @param  array<string,mixed>  $activity  @param  string[]|null  $inboxes */
    public function __construct(public array $activity, public ?array $inboxes = null) {}

    public function handle(): void
    {
        $inboxes = $this->inboxes;
        if ($inboxes === null) {
            $inboxes = DB::table('federation_followers')->get()
                ->map(fn ($f) => $f->shared_inbox ?: $f->inbox)
                ->filter()->unique()->values()->all();
        }
        if (! $inboxes) {
            return;
        }

        $body = json_encode($this->activity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        foreach ($inboxes as $inbox) {
            try {
                $headers = Federation::signHeaders('post', $inbox, $body);
                Http::withBody($body, Federation::CTYPE)->withHeaders($headers)->timeout(12)->post($inbox);
            } catch (\Throwable $e) {
                Log::debug('Federation delivery failed to '.$inbox.': '.$e->getMessage());
            }
        }
    }
}
