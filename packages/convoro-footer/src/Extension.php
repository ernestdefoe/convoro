<?php

namespace Convoro\Ext\Footer;

use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Footer Builder — first-party Convoro extension.
 *
 * A fully admin-configurable, theme-matched site footer: a brand column with
 * logo/blurb + social buttons, up to four link columns, a copyright bar and an
 * optional back-to-top button. Renders into the core `forum:footer` slot, so it
 * works on every page without touching core. Config lives in the Settings store.
 */
class Extension extends ServiceProvider
{
    private const KEY = 'ext.footer';

    private const SOCIALS = ['website', 'x', 'github', 'discord', 'youtube', 'facebook', 'instagram', 'linkedin', 'mastodon', 'twitch', 'tiktok', 'rss', 'email'];

    public function boot(): void
    {
        // Public: the footer config the frontend renders.
        Route::middleware('web')->get('/api/ext/footer/config', fn () => response()->json(self::config()));

        // Admin: editor page + load/save.
        Route::middleware(['web', 'auth', 'admin'])->prefix('admin/ext/footer')->group(function () {
            Route::get('/', fn () => response(self::adminPage()));
            Route::get('/config', fn () => response()->json(self::config()));
            Route::post('/', function (Request $request) {
                Settings::set(self::KEY, self::normalize((array) $request->input('config', [])));

                return response()->json(['ok' => true, 'config' => self::config()]);
            });
        });
    }

    /** Stored config, or sensible defaults seeded from the site identity. */
    private static function config(): array
    {
        $stored = Settings::get(self::KEY);
        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }

        return is_array($stored) && $stored ? $stored : self::defaults();
    }

    private static function defaults(): array
    {
        $name = (string) Settings::get('site.name', 'Convoro');

        return [
            'enabled' => true,
            'accent' => true,
            'backToTop' => true,
            'info' => [
                'title' => $name,
                'logo' => (string) Settings::get('site.logo', ''),
                'text' => 'A modern community built on Convoro.',
                'socials' => [],
            ],
            'columns' => [
                ['title' => 'Community', 'links' => [
                    ['label' => 'Discussions', 'url' => '/'],
                    ['label' => 'Members', 'url' => '/members'],
                    ['label' => 'Leaderboard', 'url' => '/leaderboard'],
                ]],
                ['title' => 'Resources', 'links' => [
                    ['label' => 'Docs', 'url' => '/docs'],
                    ['label' => 'Search', 'url' => '/search'],
                ]],
            ],
            'copyright' => '© {year} '.$name.'. All rights reserved.',
        ];
    }

    /** Clamp + sanitise admin-supplied config into a safe, bounded shape. */
    private static function normalize(array $cfg): array
    {
        $str = fn ($v, int $n) => Str::limit(trim((string) ($v ?? '')), $n, '');
        $url = function ($v) {
            $v = trim((string) ($v ?? ''));

            return preg_match('/^\s*(javascript|data|vbscript):/i', $v) ? '#' : Str::limit($v, 400, '');
        };

        $info = (array) ($cfg['info'] ?? []);
        $socials = [];
        foreach (array_slice((array) ($info['socials'] ?? []), 0, 8) as $s) {
            $s = (array) $s;
            $icon = in_array($s['icon'] ?? '', self::SOCIALS, true) ? $s['icon'] : 'website';
            $u = $url($s['url'] ?? '');
            if ($u !== '' && $u !== '#') {
                $socials[] = ['icon' => $icon, 'url' => $u];
            }
        }

        $columns = [];
        foreach (array_slice((array) ($cfg['columns'] ?? []), 0, 4) as $col) {
            $col = (array) $col;
            $links = [];
            foreach (array_slice((array) ($col['links'] ?? []), 0, 6) as $l) {
                $l = (array) $l;
                $label = $str($l['label'] ?? '', 60);
                if ($label !== '') {
                    $links[] = ['label' => $label, 'url' => $url($l['url'] ?? '#')];
                }
            }
            $columns[] = ['title' => $str($col['title'] ?? '', 60), 'links' => $links];
        }

        return [
            'enabled' => (bool) ($cfg['enabled'] ?? true),
            'accent' => (bool) ($cfg['accent'] ?? true),
            'backToTop' => (bool) ($cfg['backToTop'] ?? true),
            'info' => [
                'title' => $str($info['title'] ?? '', 60),
                'logo' => $url($info['logo'] ?? ''),
                'text' => $str($info['text'] ?? '', 400),
                'socials' => $socials,
            ],
            'columns' => $columns,
            'copyright' => $str($cfg['copyright'] ?? '', 200),
        ];
    }

    /** Self-contained admin editor (Convoro-dark, vanilla JS, live preview). */
    private static function adminPage(): string
    {
        $csrf = csrf_token();
        // Safe to embed inside a <script> raw-text block: <, >, &, ', " are \u-escaped.
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $config = json_encode(self::config(), $flags);
        $socials = json_encode(self::SOCIALS, $flags);

        return <<<HTML
<!DOCTYPE html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{$csrf}"><title>Footer Builder · Convoro</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,sans-serif;background:#0f1120;color:#e6e8f5}
a{color:#8b8bf0}h1{font-size:24px;margin:0 0 4px}.sub{color:#9aa0b8;margin:0 0 22px;font-size:14px}
.layout{max-width:1100px;margin:0 auto;padding:36px 20px;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:24px}
@media(max-width:860px){.layout{grid-template-columns:1fr}}
.top{display:flex;align-items:center;gap:12px;max-width:1100px;margin:0 auto;padding:24px 20px 0}.top .sp{flex:1}
.card{background:#14172a;border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:18px;margin-bottom:16px}
.card h2{font-size:13px;text-transform:uppercase;letter-spacing:.06em;color:#9aa0b8;margin:0 0 14px}
label{display:block;font-size:12.5px;color:#aeb3cc;margin:10px 0 5px}
input,textarea,select{width:100%;background:#0f1120;border:1px solid rgba(255,255,255,.12);border-radius:9px;color:#e6e8f5;padding:9px 11px;font:inherit;font-size:14px}
textarea{resize:vertical}
.sw{display:flex;align-items:center;gap:9px;margin:8px 0;font-size:14px;color:#c7cbe0;cursor:pointer}
.sw input{width:auto}
.btn{border:0;border-radius:9px;padding:9px 16px;font-weight:700;font-size:14px;cursor:pointer;background:#5b5bd6;color:#fff}
.btn.ghost{background:rgba(255,255,255,.06);color:#c7cbe0}.btn.sm{padding:6px 11px;font-size:12.5px}
.btn.danger{background:transparent;color:#f87171;padding:6px 9px}
.row{display:flex;gap:8px;align-items:center;margin-bottom:8px}.row>*{margin:0}
.col{border:1px solid rgba(255,255,255,.08);border-radius:11px;padding:13px;margin-bottom:12px;background:#10132400}
.col .chead{display:flex;gap:8px;align-items:center;margin-bottom:8px}
.muted{color:#71768f;font-size:12px}
/* preview */
.pv-wrap{position:sticky;top:24px}
.pv{--c-primary:91 91 214;--c-accent:139 92 246;--c-bg:243 244 249;--c-surface:255 255 255;--c-surface-2:248 249 252;--c-border:230 232 240;--c-text:27 32 48;--c-text-2:74 81 104;--c-muted:138 144 166;background:rgb(var(--c-bg));border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:26px 24px;overflow:hidden}
.pv.dark{--c-bg:16 18 30;--c-surface:22 25 41;--c-surface-2:28 32 52;--c-border:42 47 70;--c-text:233 235 243;--c-text-2:174 180 208;--c-muted:120 127 152}
.pv-bar{display:flex;gap:8px;margin-bottom:10px}
.tag{font-size:11px;color:#9aa0b8}
</style></head><body>
<div class="top"><div><h1>Footer Builder</h1><p class="sub">Design a site-wide footer — brand, links and socials. Matches your active theme.</p></div>
<div class="sp"></div><span id="status" class="tag"></span><a href="/admin/marketplace" class="btn ghost">← Marketplace</a><button class="btn" id="save">Save footer</button></div>
<div class="layout">
<div id="editor"></div>
<div class="pv-wrap">
  <div class="card"><h2>Live preview</h2>
  <div class="pv-bar"><button class="btn ghost sm" id="lightBtn">Light</button><button class="btn ghost sm" id="darkBtn">Dark</button></div>
  <div class="pv" id="preview"></div></div>
</div>
</div>
<script type="application/json" id="x-cfg">{$config}</script>
<script type="application/json" id="x-soc">{$socials}</script>
<script>
var CFG = JSON.parse(document.getElementById('x-cfg').textContent);
var SOCIALS = JSON.parse(document.getElementById('x-soc').textContent);
var csrf = document.querySelector('meta[name=csrf-token]').content;
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m];});}
function safeUrl(u){u=String(u==null?'':u).trim();return /^\s*(javascript|data|vbscript):/i.test(u)?'#':(u||'#');}

var ICONS = window.__CVF_ICONS || {};
// minimal icon set for preview (mirrors forum.js)
ICONS = {
 website:'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm6.93 6h-2.95a15.6 15.6 0 0 0-1.38-3.56A8.03 8.03 0 0 1 18.93 8ZM12 4c.83 1.2 1.48 2.54 1.91 3.96h-3.82A13.7 13.7 0 0 1 12 4ZM4.26 14a7.96 7.96 0 0 1 0-4h3.38a16.6 16.6 0 0 0 0 4H4.26Zm.81 2h2.95c.34 1.27.81 2.48 1.38 3.56A8.03 8.03 0 0 1 5.07 16Zm2.95-8H5.07a8.03 8.03 0 0 1 4.33-3.56A15.6 15.6 0 0 0 8.02 8ZM12 20c-.83-1.2-1.48-2.54-1.91-3.96h3.82A13.7 13.7 0 0 1 12 20Zm2.34-6H9.66a14.7 14.7 0 0 1 0-4h4.68a14.7 14.7 0 0 1 0 4Zm.26 5.56c.57-1.08 1.04-2.29 1.38-3.56h2.95a8.03 8.03 0 0 1-4.33 3.56ZM16.36 14a16.6 16.6 0 0 0 0-4h3.38a7.96 7.96 0 0 1 0 4h-3.38Z',
 x:'M18.24 2H21.5l-7.5 8.57L22.5 22h-6.9l-5.4-7.06L4 22H.74l8.02-9.17L1.5 2h7.06l4.88 6.45L18.24 2Zm-1.2 18h1.82L7.05 3.9H5.1L17.03 20Z',
 github:'M12 2C6.48 2 2 6.58 2 12.25c0 4.53 2.87 8.37 6.84 9.73.5.1.68-.22.68-.49v-1.7c-2.78.62-3.37-1.36-3.37-1.36-.45-1.18-1.11-1.5-1.11-1.5-.91-.63.07-.62.07-.62 1 .07 1.53 1.06 1.53 1.06.9 1.57 2.36 1.12 2.94.86.09-.66.35-1.12.63-1.38-2.22-.26-4.55-1.14-4.55-5.07 0-1.12.39-2.03 1.03-2.75-.1-.26-.45-1.3.1-2.7 0 0 .84-.28 2.75 1.05A9.3 9.3 0 0 1 12 6.84c.85 0 1.71.12 2.51.34 1.91-1.33 2.75-1.05 2.75-1.05.55 1.4.2 2.44.1 2.7.64.72 1.03 1.63 1.03 2.75 0 3.94-2.34 4.8-4.57 5.06.36.32.68.94.68 1.9v2.82c0 .27.18.6.69.49A10.02 10.02 0 0 0 22 12.25C22 6.58 17.52 2 12 2Z',
 discord:'M20.32 4.37A19.8 19.8 0 0 0 15.45 3a13.6 13.6 0 0 0-.62 1.27 18.3 18.3 0 0 0-5.66 0A13.6 13.6 0 0 0 8.55 3a19.7 19.7 0 0 0-4.88 1.37C.56 8.98-.28 13.48.14 17.91a19.9 19.9 0 0 0 6.07 3.06c.49-.67.93-1.38 1.3-2.13-.71-.27-1.4-.6-2.04-.99.17-.13.34-.26.5-.4a14.2 14.2 0 0 0 12.06 0c.16.14.33.27.5.4-.65.39-1.34.72-2.05.99.37.75.81 1.46 1.3 2.13a19.9 19.9 0 0 0 6.07-3.06c.5-5.18-.84-9.64-3.53-13.54ZM8.02 15.18c-1.18 0-2.15-1.09-2.15-2.43 0-1.34.95-2.43 2.15-2.43 1.2 0 2.17 1.1 2.15 2.43 0 1.34-.95 2.43-2.15 2.43Zm7.96 0c-1.18 0-2.15-1.09-2.15-2.43 0-1.34.95-2.43 2.15-2.43 1.2 0 2.17 1.1 2.15 2.43 0 1.34-.95 2.43-2.15 2.43Z',
 youtube:'M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.4.6A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.6 9.4.6 9.4.6s7.5 0 9.4-.6a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8ZM9.6 15.6V8.4l6.3 3.6-6.3 3.6Z',
 facebook:'M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07Z',
 instagram:'M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 0 1-1.38-.9 3.7 3.7 0 0 1-.9-1.38c-.16-.42-.36-1.06-.41-2.23C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16ZM12 5.84A6.16 6.16 0 1 0 12 18.16 6.16 6.16 0 0 0 12 5.84Zm0 10.16A4 4 0 1 1 12 8a4 4 0 0 1 0 8Zm7.85-10.4a1.44 1.44 0 1 1-2.88 0 1.44 1.44 0 0 1 2.88 0Z',
 linkedin:'M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.63-1.85 3.36-1.85 3.6 0 4.27 2.37 4.27 5.45v6.29ZM5.34 7.43a2.06 2.06 0 1 1 0-4.13 2.06 2.06 0 0 1 0 4.13ZM7.12 20.45H3.56V9h3.56v11.45ZM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.73C24 .77 23.2 0 22.22 0Z',
 mastodon:'M23.27 5.33c-.36-2.65-2.7-4.74-5.47-5.15-.47-.07-2.24-.18-6.01-.18h-.09c-3.77 0-4.58.11-5.05.18C3.95.58 1.49 2.46.9 5.14.61 6.46.58 7.93.63 9.27c.08 1.92.1 3.84.27 5.75.12 1.27.34 2.53.65 3.77.59 2.29 2.85 4.2 5.06 4.97 2.37.81 4.92.94 7.36.39.27-.06.53-.13.8-.21.6-.19 1.3-.4 1.81-.78a.06.06 0 0 0 .03-.05v-1.86a.05.05 0 0 0-.07-.05c-1.55.37-3.13.55-4.72.55-2.74 0-3.48-1.3-3.69-1.84a5.7 5.7 0 0 1-.32-1.45.05.05 0 0 1 .06-.06c1.52.37 3.08.55 4.64.55.38 0 .75 0 1.13-.01 1.57-.04 3.22-.12 4.76-.42l.11-.03c2.43-.47 4.75-1.93 4.99-5.64.01-.15.03-1.53.03-1.68.01-.51.17-3.62-.02-5.53ZM19.4 14.5h-2.4V8.6c0-1.24-.52-1.87-1.58-1.87-1.16 0-1.74.75-1.74 2.24v3.24h-2.39V8.97c0-1.49-.58-2.24-1.74-2.24-1.05 0-1.58.63-1.58 1.87v5.9H5.56V8.42c0-1.24.32-2.22.95-2.95.65-.72 1.5-1.1 2.56-1.1 1.22 0 2.15.47 2.76 1.4l.6 1 .6-1c.61-.93 1.54-1.4 2.76-1.4 1.05 0 1.9.38 2.56 1.1.63.73.95 1.71.95 2.95v6.08Z',
 twitch:'M2.15 0 .55 4.17v17.34h5.92V24h3.32l2.48-2.49h4.8L23.45 16V0H2.15Zm19.07 14.93-3.32 3.32h-5.07l-2.48 2.48v-2.48H5.92V2.21h15.3v12.72ZM17.9 6.34v6.16h-2.21V6.34h2.21Zm-5.53 0v6.16h-2.21V6.34h2.21Z',
 tiktok:'M16.6 5.82A4.28 4.28 0 0 1 15.54 3h-3.09v12.4a2.59 2.59 0 0 1-2.59 2.5 2.59 2.59 0 0 1-2.59-2.59 2.59 2.59 0 0 1 3.4-2.46V9.7a5.66 5.66 0 0 0-.81-.06A5.69 5.69 0 0 0 4.18 15.3a5.69 5.69 0 0 0 11.38 0V9.01a7.35 7.35 0 0 0 4.29 1.37V7.3a4.28 4.28 0 0 1-3.25-1.48Z',
 rss:'M6.18 17.82a2.18 2.18 0 1 1-4.36 0 2.18 2.18 0 0 1 4.36 0ZM2 8.36v2.91c5.39 0 9.77 4.38 9.77 9.77h2.91c0-7-5.68-12.68-12.68-12.68Zm0-5.45v2.91c8.39 0 15.23 6.83 15.23 15.23h2.91C20.14 11.45 12.05 3.36 2 2.91Z',
 email:'M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm9 7 8-5H4l8 5Zm0 2L4 9v8h16V9l-8 5Z'
};

function renderPreview(){
  var year=new Date().getFullYear();
  var copy=String(CFG.copyright||'').replace(/\{year\}/g,year);
  var info=CFG.info||{};
  var head=info.logo?'<img style="height:30px" src="'+esc(safeUrl(info.logo))+'" alt="">':'<h3 style="font-size:18px;font-weight:800;margin:0;color:rgb(var(--c-text))">'+esc(info.title||'')+'</h3>';
  var desc=info.text?'<p style="color:rgb(var(--c-muted));font-size:13.5px;line-height:1.55;margin:10px 0 0">'+esc(info.text)+'</p>':'';
  var soc=(info.socials||[]).map(function(s){if(!s||!s.url)return'';var ic=ICONS[s.icon]||ICONS.website;return '<a style="width:34px;height:34px;border-radius:10px;display:grid;place-items:center;background:rgb(var(--c-surface-2));color:rgb(var(--c-text-2))"><svg viewBox="0 0 24 24" width="17" height="17" style="fill:currentColor"><path d="'+ic+'"/></svg></a>';}).join('');
  if(soc)soc='<div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap">'+soc+'</div>';
  var brand='<div style="flex:1 1 220px;max-width:340px">'+head+desc+soc+'</div>';
  var cols=(CFG.columns||[]).map(function(col){if(!col)return'';var links=(col.links||[]).map(function(l){if(!l||!l.label)return'';return '<li style="margin-bottom:9px"><a style="color:rgb(var(--c-muted));font-size:13.5px;text-decoration:none">'+esc(l.label)+'</a></li>';}).join('');if(!links)return'';return '<div style="min-width:110px"><h4 style="font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:rgb(var(--c-text-2));margin:0 0 13px">'+esc(col.title||'')+'</h4><ul style="list-style:none;margin:0;padding:0">'+links+'</ul></div>';}).join('');
  if(cols)cols='<div style="display:flex;flex-wrap:wrap;gap:36px">'+cols+'</div>';
  var html='';
  if(CFG.accent!==false)html+='<div style="height:3px;border-radius:2px;margin-bottom:24px;background:linear-gradient(90deg,rgb(var(--c-primary)),rgb(var(--c-accent)))"></div>';
  html+='<div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:32px;padding-bottom:24px;text-align:left">'+brand+cols+'</div>';
  if(copy)html+='<div style="border-top:1px solid rgb(var(--c-border));padding-top:18px;text-align:center;color:rgb(var(--c-muted));font-size:13px">'+esc(copy)+'</div>';
  document.getElementById('preview').innerHTML=html;
}

function field(label,val,oninput,type){
  var wrap=document.createElement('div');
  var l=document.createElement('label');l.textContent=label;wrap.appendChild(l);
  var i=document.createElement(type==='area'?'textarea':'input');
  if(type==='area')i.rows=3;i.value=val||'';
  i.addEventListener('input',function(){oninput(i.value);renderPreview();});
  wrap.appendChild(i);return wrap;
}
function sw(label,val,onchange){
  var l=document.createElement('label');l.className='sw';
  var i=document.createElement('input');i.type='checkbox';i.checked=!!val;
  i.addEventListener('change',function(){onchange(i.checked);renderPreview();});
  l.appendChild(i);l.appendChild(document.createTextNode(label));return l;
}

function build(){
  var ed=document.getElementById('editor');ed.innerHTML='';
  CFG.info=CFG.info||{};CFG.columns=CFG.columns||[];CFG.info.socials=CFG.info.socials||[];

  // Display card
  var c0=document.createElement('div');c0.className='card';c0.innerHTML='<h2>Display</h2>';
  c0.appendChild(sw('Show footer',CFG.enabled!==false,function(v){CFG.enabled=v;}));
  c0.appendChild(sw('Accent gradient line',CFG.accent!==false,function(v){CFG.accent=v;}));
  c0.appendChild(sw('Back-to-top button',CFG.backToTop!==false,function(v){CFG.backToTop=v;}));
  ed.appendChild(c0);

  // Brand card
  var c1=document.createElement('div');c1.className='card';c1.innerHTML='<h2>Brand</h2>';
  c1.appendChild(field('Title (used when no logo)',CFG.info.title,function(v){CFG.info.title=v;}));
  c1.appendChild(field('Logo URL (optional)',CFG.info.logo,function(v){CFG.info.logo=v;}));
  c1.appendChild(field('Description',CFG.info.text,function(v){CFG.info.text=v;},'area'));
  var sh=document.createElement('div');sh.innerHTML='<label>Social links</label>';
  CFG.info.socials.forEach(function(s,idx){
    var r=document.createElement('div');r.className='row';
    var sel=document.createElement('select');SOCIALS.forEach(function(o){var op=document.createElement('option');op.value=o;op.textContent=o;if(o===s.icon)op.selected=true;sel.appendChild(op);});
    sel.style.maxWidth='140px';sel.addEventListener('change',function(){s.icon=sel.value;renderPreview();});
    var u=document.createElement('input');u.placeholder='https://…';u.value=s.url||'';u.addEventListener('input',function(){s.url=u.value;renderPreview();});
    var rm=document.createElement('button');rm.className='btn danger';rm.textContent='✕';rm.addEventListener('click',function(){CFG.info.socials.splice(idx,1);build();renderPreview();});
    r.appendChild(sel);r.appendChild(u);r.appendChild(rm);sh.appendChild(r);
  });
  var addS=document.createElement('button');addS.className='btn ghost sm';addS.textContent='+ Add social';
  addS.addEventListener('click',function(){if(CFG.info.socials.length>=8)return;CFG.info.socials.push({icon:'website',url:''});build();});
  sh.appendChild(addS);c1.appendChild(sh);ed.appendChild(c1);

  // Columns card
  var c2=document.createElement('div');c2.className='card';c2.innerHTML='<h2>Link columns</h2>';
  CFG.columns.forEach(function(col,ci){
    col.links=col.links||[];
    var box=document.createElement('div');box.className='col';
    var ch=document.createElement('div');ch.className='chead';
    var t=document.createElement('input');t.placeholder='Column title';t.value=col.title||'';t.addEventListener('input',function(){col.title=t.value;renderPreview();});
    var rmc=document.createElement('button');rmc.className='btn danger';rmc.textContent='✕';rmc.addEventListener('click',function(){CFG.columns.splice(ci,1);build();renderPreview();});
    ch.appendChild(t);ch.appendChild(rmc);box.appendChild(ch);
    col.links.forEach(function(l,li){
      var r=document.createElement('div');r.className='row';
      var lb=document.createElement('input');lb.placeholder='Label';lb.value=l.label||'';lb.addEventListener('input',function(){l.label=lb.value;renderPreview();});
      var u=document.createElement('input');u.placeholder='/path or https://…';u.value=l.url||'';u.addEventListener('input',function(){l.url=u.value;renderPreview();});
      var rm=document.createElement('button');rm.className='btn danger';rm.textContent='✕';rm.addEventListener('click',function(){col.links.splice(li,1);build();renderPreview();});
      r.appendChild(lb);r.appendChild(u);r.appendChild(rm);box.appendChild(r);
    });
    var addL=document.createElement('button');addL.className='btn ghost sm';addL.textContent='+ Add link';
    addL.addEventListener('click',function(){if(col.links.length>=6)return;col.links.push({label:'',url:''});build();});
    box.appendChild(addL);c2.appendChild(box);
  });
  var addC=document.createElement('button');addC.className='btn ghost';addC.textContent='+ Add column';
  addC.addEventListener('click',function(){if(CFG.columns.length>=4)return;CFG.columns.push({title:'',links:[]});build();});
  c2.appendChild(addC);ed.appendChild(c2);

  // Bottom card
  var c3=document.createElement('div');c3.className='card';c3.innerHTML='<h2>Copyright</h2>';
  c3.appendChild(field('Bottom-bar text (use {year} for the current year)',CFG.copyright,function(v){CFG.copyright=v;}));
  ed.appendChild(c3);

  // Any structural change (add/remove rows) rebuilds + autosaves.
  if(ready) scheduleSave();
}

// ── Save + autosave ─────────────────────────────────────────────────────────
var ready=false, saving=false, pending=false, saveTimer=null;
function setStatus(t,clearAfter){var st=document.getElementById('status');st.textContent=t;if(clearAfter){setTimeout(function(){if(st.textContent===t)st.textContent='';},clearAfter);}}
function doSave(){
  saving=true;setStatus('Saving…');
  fetch('/admin/ext/footer',{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({config:CFG})})
   .then(function(r){return r.ok?r.json():null;})
   .then(function(d){saving=false;if(d&&d.ok){setStatus('All changes saved ✓',2500);}else{setStatus('Couldn’t save — retry');}if(pending){pending=false;doSave();}})
   .catch(function(){saving=false;setStatus('Couldn’t save — retry');});
}
function scheduleSave(){setStatus('Saving…');clearTimeout(saveTimer);saveTimer=setTimeout(function(){if(saving){pending=true;}else{doSave();}},900);}

document.getElementById('lightBtn').addEventListener('click',function(){document.getElementById('preview').classList.remove('dark');});
document.getElementById('darkBtn').addEventListener('click',function(){document.getElementById('preview').classList.add('dark');});
// Explicit save (immediate), plus debounced autosave on any field edit.
document.getElementById('save').addEventListener('click',function(){clearTimeout(saveTimer);doSave();});
var edEl=document.getElementById('editor');
edEl.addEventListener('input',function(){if(ready)scheduleSave();});
edEl.addEventListener('change',function(){if(ready)scheduleSave();});

build();renderPreview();
ready=true;
</script>
</body></html>
HTML;
    }
}
