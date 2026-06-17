<?php

namespace Convoro\Ext\Discord;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Re-runs role sync for every Discord-linked member (admin "Resync all"). */
class ResyncRolesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function handle(): void
    {
        if (! Extension::enabled() || ! DiscordApi::configured()) {
            return;
        }

        User::whereNotNull('discord_id')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                try {
                    RoleSync::sync($user);
                } catch (\Throwable) {
                    // Skip individual failures (e.g. member left the guild).
                }
            }
        });
    }
}
