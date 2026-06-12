<?php

namespace App\Console\Commands;

use App\Http\Controllers\DraftController;
use App\Models\Draft;
use Illuminate\Console\Command;

class PublishScheduledDrafts extends Command
{
    protected $signature = 'convoro:publish-scheduled';

    protected $description = 'Publish scheduled posts whose time has arrived.';

    public function handle(): int
    {
        $due = Draft::whereNotNull('scheduled_at')->where('scheduled_at', '<=', now())->get();
        $count = 0;
        foreach ($due as $draft) {
            try {
                if (DraftController::publishDraft($draft)) {
                    $count++;
                } else {
                    // Incomplete (no title/body) — drop the schedule, keep as a plain draft.
                    $draft->update(['scheduled_at' => null]);
                }
            } catch (\Throwable $e) {
                $this->error("Draft {$draft->id}: ".$e->getMessage());
            }
        }
        $this->info("Published {$count} scheduled post(s).");

        return self::SUCCESS;
    }
}
