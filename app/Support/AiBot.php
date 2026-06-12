<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/** The assistant account that authors AI posts (auto-answers). */
class AiBot
{
    public static function name(): string
    {
        return (string) (Settings::get('ai.bot_name') ?: 'Convoro Assistant');
    }

    /** Find or create the assistant user; returns its id. */
    public static function userId(): int
    {
        $id = (int) Settings::get('ai.bot_user_id', 0);
        if ($id && User::whereKey($id)->exists()) {
            return $id;
        }

        $user = User::firstOrCreate(
            ['email' => 'assistant@convoro.local'],
            ['name' => self::name(), 'password' => bcrypt(Str::random(32))],
        );
        Settings::set('ai.bot_user_id', $user->id);

        return $user->id;
    }
}
