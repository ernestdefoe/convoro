<?php

use App\Models\Product;
use App\Support\CoverImage;
use Illuminate\Database\Migrations\Migration;

/**
 * Directory listing for the free Role-Play extension as a NON-bundled, on-demand
 * extension installed from its own GitHub repo. Upserts by package; free +
 * published; idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('products')) {
            return;
        }

        $p = Product::firstOrNew(['package' => 'convoro-roleplay']);
        $p->fill([
            'slug' => 'convoro-roleplay',
            'name' => 'Role-Play',
            'type' => 'extension',
            'tagline' => 'Turn your community into a role-play forum — characters, post-as-character, dice and sheets.',
            'description' => "Everything a role-play community needs, built for Convoro. Members create multiple characters — name, claim, bio, avatar and a hero image — and post as them, so a thread reads as the characters, not the accounts.\n\n- Designate role-play categories, or flag individual topics. The character picker, dice roller and an \"In character\" badge appear only in role-play threads; regular topics stay completely normal.\n- A dice roller in the composer (and `[[2d6+3]]` syntax) — rolls are evaluated server-side once and logged.\n- Staff-defined character sheets, shown as a table on each profile.\n- A character directory at /characters with search, sort, face/canon claims (with duplicate flagging), and per-character thread trackers.\n- Optional character-approval queue and a per-member character cap.\n\nInstalled on demand from the Marketplace — not bundled. Free and open-source.",
            'version' => '1.0.0',
            'source' => 'github',
            'repo' => 'ernestdefoe/convoro-roleplay',
            'download_url' => 'https://github.com/ernestdefoe/convoro-roleplay/archive/refs/tags/v1.0.0.zip',
            'ref' => 'v1.0.0',
        ]);
        $p->price_cents = 0;
        $p->published = true;
        $p->status = 'approved';
        $p->save();

        if (empty($p->image)) {
            try {
                CoverImage::generate($p);
            } catch (\Throwable) {
                // best-effort
            }
        }
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('products')) {
            Product::where('package', 'convoro-roleplay')->delete();
        }
    }
};
