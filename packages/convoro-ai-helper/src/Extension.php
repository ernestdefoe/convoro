<?php

namespace Convoro\Ext\AiHelper;

use App\Models\Post;
use App\Models\User;
use App\Support\Content;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

/**
 * AI Helper — premium Convoro extension.
 *
 * A multi-LLM (Anthropic / OpenAI-compatible) support assistant. When a member
 * posts — mentioning the bot, in a chosen support category, or anywhere — it
 * replies automatically as a bot account. Ships a usage + cost dashboard and a
 * monthly budget cap. The actual model call runs on the queue so posting stays
 * instant.
 */
class Extension extends ServiceProvider
{
    public function boot(): void
    {
        // Auto-reply: cheap trigger check inline, model call on the queue.
        Post::created(function (Post $post) {
            try {
                if (self::shouldTrigger($post)) {
                    $id = $post->id;
                    dispatch(function () use ($id) {
                        \Convoro\Ext\AiHelper\Extension::generateReply($id);
                    })->afterCommit();
                }
            } catch (\Throwable) {
                // never let the assistant break posting
            }
        });

        Route::middleware(['web', 'auth', 'admin'])->prefix('admin/ext/ai-helper')->group(function () {
            Route::get('/', fn () => response(self::adminPage()));
            Route::post('/', function (Request $request) {
                $data = $request->validate([
                    'enabled' => ['boolean'],
                    'provider' => ['required', 'in:anthropic,openai'],
                    'api_key' => ['nullable', 'string', 'max:300'],
                    'base_url' => ['nullable', 'string', 'max:300'],
                    'model' => ['required', 'string', 'max:120'],
                    'trigger' => ['required', 'in:mention,category,all'],
                    'category_id' => ['nullable', 'integer'],
                    'bot_name' => ['required', 'string', 'max:60'],
                    'system_prompt' => ['nullable', 'string', 'max:4000'],
                    'max_tokens' => ['required', 'integer', 'min:64', 'max:4096'],
                    'monthly_budget' => ['nullable', 'numeric', 'min:0', 'max:100000'],
                    'price_in' => ['nullable', 'numeric', 'min:0'],
                    'price_out' => ['nullable', 'numeric', 'min:0'],
                ]);

                Settings::setMany([
                    'ai.enabled' => $request->boolean('enabled'),
                    'ai.provider' => $data['provider'],
                    'ai.base_url' => $data['base_url'] ?? '',
                    'ai.model' => $data['model'],
                    'ai.trigger' => $data['trigger'],
                    'ai.category_id' => $data['category_id'] ?: null,
                    'ai.bot_name' => $data['bot_name'],
                    'ai.system_prompt' => $data['system_prompt'] ?? '',
                    'ai.max_tokens' => (int) $data['max_tokens'],
                    'ai.monthly_budget_cents' => (int) round(((float) ($data['monthly_budget'] ?? 0)) * 100),
                    'ai.price_in' => (float) ($data['price_in'] ?? 0),
                    'ai.price_out' => (float) ($data['price_out'] ?? 0),
                ]);
                // Only overwrite the key when a new one is supplied.
                if (! empty($data['api_key'])) {
                    Settings::set('ai.api_key', $data['api_key']);
                }

                return response()->json(['ok' => true]);
            });
        });
    }

    /* ---------------------------------------------------------------------- */

    private static function cfg(string $key, mixed $default = null): mixed
    {
        return Settings::get('ai.'.$key, $default);
    }

    private static function botUser(): User
    {
        $id = self::cfg('bot_user_id');
        if ($id && ($u = User::find($id))) {
            return $u;
        }
        $u = User::firstOrCreate(
            ['email' => 'assistant@convoro.local'],
            ['name' => self::cfg('bot_name', 'Assistant'), 'password' => bcrypt(bin2hex(random_bytes(16))), 'is_admin' => false],
        );
        Settings::set('ai.bot_user_id', $u->id);

        return $u;
    }

    private static function shouldTrigger(Post $post): bool
    {
        if (! self::cfg('enabled') || ! self::cfg('api_key')) {
            return false;
        }
        if ((int) $post->user_id === (int) self::cfg('bot_user_id')) {
            return false; // never reply to itself
        }

        $topic = $post->topic;
        if (! $topic) {
            return false;
        }

        return match ((string) self::cfg('trigger', 'mention')) {
            'all' => true,
            'category' => self::cfg('category_id') && (int) $topic->category_id === (int) self::cfg('category_id'),
            default => stripos(strip_tags((string) $post->body_html), '@'.self::cfg('bot_name', 'Assistant')) !== false,
        };
    }

    /** Queued: build context, call the model, post the reply, record usage. */
    public static function generateReply(int $postId): void
    {
        $post = Post::with('topic')->find($postId);
        if (! $post || ! $post->topic) {
            return;
        }
        if (! self::cfg('enabled') || ! self::cfg('api_key')) {
            return;
        }

        // Budget guard.
        $budget = (int) self::cfg('monthly_budget_cents', 0);
        if ($budget > 0 && self::monthSpentCents() >= $budget) {
            return;
        }

        $bot = self::botUser();
        $topic = $post->topic;

        // Build a transcript from the last several posts.
        $recent = Post::where('topic_id', $topic->id)->orderBy('id')->get(['user_id', 'body_html'])->take(-10);
        $lines = ["Conversation in the topic “{$topic->title}”:"];
        foreach ($recent as $p) {
            $who = (int) $p->user_id === (int) $bot->id ? self::cfg('bot_name', 'Assistant') : ('User '.$p->user_id);
            $lines[] = $who.': '.trim(strip_tags((string) $p->body_html));
        }
        $lines[] = "\nReply helpfully and concisely as ".self::cfg('bot_name', 'Assistant').'.';
        $transcript = implode("\n", $lines);

        $system = (string) (self::cfg('system_prompt') ?: 'You are a friendly, concise community support assistant. Answer the member\'s question clearly. If you are unsure, say so and suggest where to find help.');

        try {
            $result = self::callLlm($system, $transcript);
        } catch (\Throwable $e) {
            return; // fail silently; admin sees no usage row
        }
        if (! $result || trim($result['text']) === '') {
            return;
        }

        $html = Content::clean(self::textToHtml($result['text']));
        $reply = Post::create([
            'topic_id' => $topic->id,
            'user_id' => $bot->id,
            'is_first' => false,
            'body_html' => $html,
        ]);
        $topic->increment('reply_count');
        $topic->forceFill(['last_post_at' => now()])->save();

        self::recordUsage($result, $topic->id);

        // Best-effort live broadcast so open viewers see the answer appear.
        try {
            $reply->load(['user', 'reactions']);
            broadcast(new \App\Events\PostCreated(\App\Support\Present::post($reply, null), $topic->id));
        } catch (\Throwable) {
        }
    }

    /** @return array{text:string,in:int,out:int}|null */
    private static function callLlm(string $system, string $userContent): ?array
    {
        $provider = (string) self::cfg('provider', 'anthropic');
        $key = (string) self::cfg('api_key');
        $model = (string) self::cfg('model', 'claude-3-5-haiku-latest');
        $maxTokens = (int) self::cfg('max_tokens', 600);

        if ($provider === 'anthropic') {
            $base = rtrim((string) (self::cfg('base_url') ?: 'https://api.anthropic.com'), '/');
            $res = Http::timeout(60)->withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
            ])->post($base.'/v1/messages', [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $userContent]],
            ]);
            if (! $res->successful()) {
                return null;
            }
            $j = $res->json();

            return [
                'text' => (string) ($j['content'][0]['text'] ?? ''),
                'in' => (int) ($j['usage']['input_tokens'] ?? 0),
                'out' => (int) ($j['usage']['output_tokens'] ?? 0),
            ];
        }

        // OpenAI-compatible (OpenAI, OpenRouter, local servers, …).
        $base = rtrim((string) (self::cfg('base_url') ?: 'https://api.openai.com'), '/');
        $res = Http::timeout(60)->withToken($key)->post($base.'/v1/chat/completions', [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $userContent],
            ],
        ]);
        if (! $res->successful()) {
            return null;
        }
        $j = $res->json();

        return [
            'text' => (string) ($j['choices'][0]['message']['content'] ?? ''),
            'in' => (int) ($j['usage']['prompt_tokens'] ?? 0),
            'out' => (int) ($j['usage']['completion_tokens'] ?? 0),
        ];
    }

    private static function recordUsage(array $r, int $topicId): void
    {
        $priceIn = (float) self::cfg('price_in', 0);
        $priceOut = (float) self::cfg('price_out', 0);
        $cost = ($r['in'] / 1_000_000 * $priceIn) + ($r['out'] / 1_000_000 * $priceOut);

        if (Schema::hasTable('ai_usage')) {
            DB::table('ai_usage')->insert([
                'model' => self::cfg('model'),
                'prompt_tokens' => $r['in'],
                'completion_tokens' => $r['out'],
                'cost_cents' => (int) round($cost * 100),
                'topic_id' => $topicId,
                'created_at' => now(),
            ]);
        }
    }

    private static function monthSpentCents(): int
    {
        if (! Schema::hasTable('ai_usage')) {
            return 0;
        }

        return (int) DB::table('ai_usage')->where('created_at', '>=', now()->startOfMonth())->sum('cost_cents');
    }

    private static function textToHtml(string $text): string
    {
        $blocks = preg_split('/\n{2,}/', trim($text)) ?: [];
        $html = '';
        foreach ($blocks as $b) {
            $html .= '<p>'.nl2br(e(trim($b))).'</p>';
        }

        return $html ?: '<p>'.e($text).'</p>';
    }

    private static function adminPage(): string
    {
        $csrf = csrf_token();
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
        $enabled = self::cfg('enabled') ? 'checked' : '';
        $provider = (string) self::cfg('provider', 'anthropic');
        $keySet = self::cfg('api_key') ? 'A key is saved — leave blank to keep it.' : 'No key saved yet.';
        $base = $e(self::cfg('base_url'));
        $model = $e(self::cfg('model', 'claude-3-5-haiku-latest'));
        $trigger = (string) self::cfg('trigger', 'mention');
        $cat = $e(self::cfg('category_id'));
        $botName = $e(self::cfg('bot_name', 'Assistant'));
        $sys = $e(self::cfg('system_prompt'));
        $maxTokens = (int) self::cfg('max_tokens', 600);
        $budget = number_format(((int) self::cfg('monthly_budget_cents', 0)) / 100, 2);
        $priceIn = $e(self::cfg('price_in', 0.80));
        $priceOut = $e(self::cfg('price_out', 4.00));

        $sel = fn ($a, $b) => $a === $b ? 'selected' : '';
        $provOpts = '<option value="anthropic" '.$sel($provider, 'anthropic').'>Anthropic (Claude)</option>'
            .'<option value="openai" '.$sel($provider, 'openai').'>OpenAI-compatible</option>';
        $trigOpts = '<option value="mention" '.$sel($trigger, 'mention').'>When mentioned (@'.$botName.')</option>'
            .'<option value="category" '.$sel($trigger, 'category').'>Every topic in a support category</option>'
            .'<option value="all" '.$sel($trigger, 'all').'>Every new post (careful — costly)</option>';

        // Dashboard figures.
        $spent = number_format(self::monthSpentCents() / 100, 2);
        $calls = Schema::hasTable('ai_usage') ? DB::table('ai_usage')->where('created_at', '>=', now()->startOfMonth())->count() : 0;
        $tokens = Schema::hasTable('ai_usage')
            ? (int) DB::table('ai_usage')->where('created_at', '>=', now()->startOfMonth())->sum(DB::raw('prompt_tokens + completion_tokens'))
            : 0;
        $tokensFmt = number_format($tokens);

        return <<<HTML
<!DOCTYPE html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{$csrf}"><title>AI Helper · Convoro</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,sans-serif;background:#0f1120;color:#e6e8f5}
.wrap{max-width:760px;margin:0 auto;padding:40px 20px}a{color:#8b8bf0}
h1{font-size:24px;margin:0 0 4px}.sub{color:#9aa0b8;margin:0 0 24px;font-size:14px}
.card{background:#14172a;border:1px solid rgba(255,255,255,.06);border-radius:14px;padding:20px;margin-bottom:16px}
.card h2{font-size:15px;margin:0 0 12px}
label.f{display:block;font-size:13px;color:#c7cbe0;margin:12px 0 4px}
input:not([type=checkbox]),textarea,select{width:100%;background:#0f1120;border:1px solid rgba(255,255,255,.1);border-radius:9px;color:#e6e8f5;padding:10px 12px;font:inherit}
.toggle{display:flex;align-items:center;gap:10px;padding:12px 14px;background:#0f1120;border:1px solid rgba(255,255,255,.1);border-radius:10px;margin-bottom:6px;cursor:pointer;font-size:14px;font-weight:600;color:#e6e8f5}
.toggle input{width:18px;height:18px;margin:0;accent-color:#5b5bd6}
.top{display:flex;align-items:center;gap:12px;margin-bottom:20px}.top .sp{flex:1}
.ok{color:#34d399;font-size:13px;margin-left:10px}.hint{color:#6b7194;font-size:12px;margin:4px 0 0}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.stat b{display:block;font-size:24px;font-weight:800}.stat span{font-size:12px;color:#9aa0b8}
.cols{display:grid;grid-template-columns:1fr 1fr;gap:12px}@media(max-width:640px){.cols,.grid{grid-template-columns:1fr}}
</style></head><body><div class="wrap">
<div class="top"><div><h1>AI Helper</h1><p class="sub">A multi-LLM assistant that answers your members automatically.</p></div>
<div class="sp"></div><a href="/admin/marketplace">← Marketplace</a></div>

<div class="card"><h2>This month</h2><div class="grid">
<div class="stat"><b>\${$spent}</b><span>Spent</span></div>
<div class="stat"><b>{$calls}</b><span>Replies</span></div>
<div class="stat"><b>{$tokensFmt}</b><span>Tokens</span></div>
</div></div>

<div class="card"><h2>Connection</h2>
<label class="toggle"><input type="checkbox" id="enabled" {$enabled}> <span>Enable the assistant</span></label>
<div class="cols">
<div><label class="f">Provider</label><select id="provider">{$provOpts}</select></div>
<div><label class="f">Model</label><input id="model" value="{$model}"></div>
</div>
<label class="f">API key</label><input id="api_key" type="password" placeholder="••••••••"><p class="hint">{$keySet}</p>
<label class="f">Base URL (optional override)</label><input id="base_url" value="{$base}" placeholder="https://api.anthropic.com">
</div>

<div class="card"><h2>Behavior</h2>
<label class="f">Bot display name</label><input id="bot_name" value="{$botName}">
<label class="f">When should it reply?</label><select id="trigger">{$trigOpts}</select>
<label class="f">Support category ID (for the “category” trigger)</label><input id="category_id" type="number" value="{$cat}">
<label class="f">System prompt</label><textarea id="system_prompt" rows="3" placeholder="You are a helpful community assistant…">{$sys}</textarea>
<label class="f">Max reply tokens</label><input id="max_tokens" type="number" value="{$maxTokens}">
</div>

<div class="card"><h2>Cost controls</h2>
<div class="cols">
<div><label class="f">Monthly budget (USD, 0 = unlimited)</label><input id="monthly_budget" type="number" step="0.01" value="{$budget}"></div>
<div></div>
<div><label class="f">Price per 1M input tokens (USD)</label><input id="price_in" type="number" step="0.01" value="{$priceIn}"></div>
<div><label class="f">Price per 1M output tokens (USD)</label><input id="price_out" type="number" step="0.01" value="{$priceOut}"></div>
</div>
<p class="hint">Used only to estimate cost in the dashboard and enforce your budget cap.</p>
</div>

<div class="savebar"><span class="ok" id="msg">Changes save automatically</span></div>
</div><script>
const csrf=document.querySelector('meta[name=csrf-token]').content;
const h={'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'};
const val=id=>document.getElementById(id).value;
const notifyParent=(message,kind='success')=>{try{if(window.parent!==window)window.parent.postMessage({type:'convoro:toast',message,kind},location.origin);}catch(e){}};
const ids=['enabled','provider','model','api_key','base_url','bot_name','trigger','category_id','system_prompt','max_tokens','monthly_budget','price_in','price_out'];
function collect(){return {
  enabled:document.getElementById('enabled').checked,
  provider:val('provider'),model:val('model'),api_key:val('api_key'),base_url:val('base_url'),
  bot_name:val('bot_name'),trigger:val('trigger'),
  category_id:val('category_id')?parseInt(val('category_id'),10):null,
  system_prompt:val('system_prompt'),max_tokens:parseInt(val('max_tokens')||'600',10),
  monthly_budget:parseFloat(val('monthly_budget')||'0'),
  price_in:parseFloat(val('price_in')||'0'),price_out:parseFloat(val('price_out')||'0'),
};}
let saving=false,queued=false;
async function save(){
  if(saving){queued=true;return;}
  saving=true; const m=document.getElementById('msg'); m.textContent='Saving…'; m.style.color='#9aa0b8';
  try{ const r=await fetch('/admin/ext/ai-helper',{method:'POST',headers:h,body:JSON.stringify(collect())});
    m.textContent=r.ok?'Saved ✓':'Error saving'; m.style.color=r.ok?'#34d399':'#f87171';
    notifyParent(r.ok?'Settings saved':"Couldn't save",r.ok?'success':'error');
  }catch(e){ m.textContent='Error saving'; m.style.color='#f87171'; notifyParent("Couldn't save",'error'); }
  saving=false; if(queued){queued=false;save();}
  setTimeout(()=>{m.textContent='Changes save automatically';m.style.color='#6b7194';},2000);
}
let t=null; const debounced=()=>{clearTimeout(t);t=setTimeout(save,800);};
ids.forEach(id=>{const el=document.getElementById(id);if(!el)return;
  const immediate=(el.type==='checkbox'||el.tagName==='SELECT');
  el.addEventListener(immediate?'change':'input', immediate?save:debounced);});
</script></body></html>
HTML;
    }
}
