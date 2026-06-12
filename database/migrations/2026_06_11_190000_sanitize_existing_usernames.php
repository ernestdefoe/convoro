<?php

use App\Support\Username;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-off cleanup of display names already in the database. Names that are
 * merely messy (stray control chars, odd whitespace, non-NFC) are sanitized in
 * place; names with no visible substance — e.g. a lone combining diamond
 * (U+20DF) — are replaced with "Member {id}". Pairs with the new validation +
 * model mutator so the situation can't recur.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->select('id', 'name')->orderBy('id')->chunkById(200, function ($users) {
            foreach ($users as $u) {
                $clean = Username::isValid($u->name)
                    ? Username::sanitize($u->name)
                    : (string) __('Member :id', ['id' => $u->id]);

                if ($clean !== (string) $u->name) {
                    DB::table('users')->where('id', $u->id)->update(['name' => $clean]);
                }
            }
        });
    }

    public function down(): void
    {
        // Irreversible: original (broken) names are intentionally not restored.
    }
};
