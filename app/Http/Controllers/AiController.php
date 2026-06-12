<?php

namespace App\Http\Controllers;

use App\Jobs\RunAskIndexJob;
use App\Support\Ask;
use App\Support\AskIndex;
use App\Support\Llm;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Core AI features. "Ask Convoro" (public, throttled) is the flagship; the
 * admin page configures the shared LLM provider that powers it.
 */
class AiController extends Controller
{
    /** Public: answer a natural-language question from the community's content. */
    public function ask(Request $request): JsonResponse
    {
        if (! Ask::enabled()) {
            return response()->json(['answer' => __('The assistant isn’t enabled on this community yet.'), 'citations' => []]);
        }
        $data = $request->validate(['question' => ['required', 'string', 'min:3', 'max:500']]);

        return response()->json(Ask::answer($data['question']));
    }

    /** Existing discussions related to a draft title/question ("asked before?"). */
    public function related(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:4', 'max:300']]);

        return response()->json(['related' => Ask::related($data['q'])]);
    }

    /** Writing assistant: transform the user's draft text (improve/grammar/tone/…). */
    public function assist(Request $request): JsonResponse
    {
        if (! Llm::configured()) {
            return response()->json(['result' => null, 'error' => __('AI is not available.')]);
        }

        $data = $request->validate([
            'text' => ['required', 'string', 'min:1', 'max:8000'],
            'action' => ['required', 'string', 'in:improve,grammar,shorten,expand,friendly,professional,simplify,translate'],
            'locale' => ['nullable', 'string', 'max:16'],
        ]);

        $instructions = [
            'improve' => 'Rewrite the text to be clearer and better written, keeping the original meaning, language and tone.',
            'grammar' => 'Fix spelling, grammar and punctuation only. Do not change the wording, meaning or language otherwise.',
            'shorten' => 'Make the text more concise while keeping the key points and the original language.',
            'expand' => 'Expand the text with a little more helpful detail, keeping the original language and intent.',
            'friendly' => 'Rewrite the text in a warmer, friendlier tone, keeping the meaning and language.',
            'professional' => 'Rewrite the text in a polished, professional tone, keeping the meaning and language.',
            'simplify' => 'Rewrite the text in simpler, plain language that is easy to read, keeping the meaning and language.',
            'translate' => 'Translate the text into '.\App\Support\I18n::languageName($data['locale'] ?? 'en').'.',
        ];

        $system = 'You are a writing assistant inside a forum composer. '
            .$instructions[$data['action']]
            .' Return ONLY the resulting text — no quotes, no preamble, no explanations, no markdown code fences. '
            .'Preserve line breaks between paragraphs.';

        try {
            $result = Llm::chat($system, $data['text'], 2000, 'assist');
        } catch (\Throwable $e) {
            return response()->json(['result' => null, 'error' => __('AI request failed.')]);
        }

        $result = trim(preg_replace('/^```.*$|^```$/m', '', trim($result)) ?? $result);

        return response()->json(['result' => $result]);
    }

    /** Generate a full post (HTML) from a short brief the member describes. */
    public function compose(Request $request): JsonResponse
    {
        if (! Llm::configured()) {
            return response()->json(['html' => null, 'error' => __('AI is not available.')]);
        }

        $data = $request->validate(['brief' => ['required', 'string', 'min:3', 'max:2000']]);

        $system = 'You are helping a member write a forum post. Expand their brief into a clear, '
            .'friendly, well-structured post. Output valid HTML using only <p>, <strong>, <em>, <ul>, '
            .'<ol>, <li>, <blockquote> tags — no headings, no markdown, no code fences, no preamble or '
            .'sign-off. Keep it natural and concise, and write in the same language as the brief.';

        try {
            $raw = Llm::chat($system, $data['brief'], 1500, 'assist');
        } catch (\Throwable $e) {
            return response()->json(['html' => null, 'error' => __('AI request failed.')]);
        }

        $raw = trim(preg_replace('/^```(?:html)?|```$/m', '', trim($raw)) ?? $raw);
        $html = \App\Support\Content::clean($raw);

        if (trim(strip_tags($html)) === '') {
            return response()->json(['html' => null, 'error' => __('AI request failed.')]);
        }

        return response()->json(['html' => $html]);
    }

    /** Suggest topic titles for a draft body. */
    public function suggestTitle(Request $request): JsonResponse
    {
        if (! Llm::configured()) {
            return response()->json(['titles' => []]);
        }
        $data = $request->validate(['text' => ['required', 'string', 'min:10', 'max:8000']]);

        try {
            $raw = Llm::chat(
                'Suggest 3 concise, specific, non-clickbait discussion titles for this forum post. '
                .'Reply with ONLY a JSON array of 3 strings, no other text.',
                $data['text'],
                300,
                'assist'
            );
        } catch (\Throwable $e) {
            return response()->json(['titles' => []]);
        }

        $titles = json_decode($this->jsonSlice($raw), true);

        return response()->json(['titles' => is_array($titles) ? array_slice(array_values($titles), 0, 3) : []]);
    }

    /** Suggest tags for a draft from the available tag list. */
    public function suggestTags(Request $request): JsonResponse
    {
        if (! Llm::configured()) {
            return response()->json(['tags' => []]);
        }
        $data = $request->validate([
            'text' => ['required', 'string', 'min:10', 'max:8000'],
            'title' => ['nullable', 'string', 'max:300'],
        ]);

        $available = \App\Models\Tag::orderBy('name')->limit(200)->pluck('name')->all();
        if (empty($available)) {
            return response()->json(['tags' => []]);
        }

        try {
            $raw = Llm::chat(
                'Pick the 1-3 most relevant tags for this post from the allowed list ONLY. '
                .'Allowed tags: '.implode(', ', $available).'. '
                .'Reply with ONLY a JSON array of the chosen tag names (exact matches from the list), no other text.',
                trim(($data['title'] ?? '')."\n\n".$data['text']),
                200,
                'assist'
            );
        } catch (\Throwable $e) {
            return response()->json(['tags' => []]);
        }

        $picked = json_decode($this->jsonSlice($raw), true);
        $picked = is_array($picked) ? array_values(array_intersect($picked, $available)) : [];

        return response()->json(['tags' => array_slice($picked, 0, 3)]);
    }

    /** Extract the first JSON value from an LLM response. */
    private function jsonSlice(string $raw): string
    {
        $raw = trim($raw);
        $start = strcspn($raw, '[{');
        $s = strpos($raw, '[') !== false ? strpos($raw, '[') : strpos($raw, '{');
        $e = strrpos($raw, ']') !== false ? strrpos($raw, ']') : strrpos($raw, '}');

        return ($s !== false && $e !== false && $e > $s) ? substr($raw, $s, $e - $s + 1) : $raw;
    }

    /** Translate a single post into the reader's language (cached per post+locale). */
    public function translatePost(Request $request, \App\Models\Post $post): JsonResponse
    {
        if (! \App\Support\ContentTranslator::enabled()) {
            return response()->json(['translated' => false, 'html' => $post->body_html]);
        }

        $data = $request->validate(['locale' => ['nullable', 'string', 'max:16']]);
        $target = $data['locale']
            ?? $request->user()?->locale
            ?? app()->getLocale();

        // Only translate into a language we know about; never echo an arbitrary code.
        if (! array_key_exists($target, \App\Support\I18n::available()) && ! array_key_exists($target, \App\Support\I18n::known())) {
            $target = 'en';
        }

        return response()->json(\App\Support\ContentTranslator::translatePost($post, $target));
    }

    /** Translate a single DM into the reader's language (cached per message+locale). */
    public function translateMessage(Request $request, \App\Models\Message $message): JsonResponse
    {
        // Only participants of the conversation may translate its messages.
        $me = (int) $request->user()->id;
        abort_unless($message->conversation->participants()->where('users.id', $me)->exists(), 403);

        if (! \App\Support\ContentTranslator::enabled()) {
            return response()->json(['translated' => false, 'html' => $message->body_html]);
        }

        $data = $request->validate(['locale' => ['nullable', 'string', 'max:16']]);
        $target = $data['locale']
            ?? $request->user()?->locale
            ?? app()->getLocale();

        if (! array_key_exists($target, \App\Support\I18n::available()) && ! array_key_exists($target, \App\Support\I18n::known())) {
            $target = 'en';
        }

        return response()->json(\App\Support\ContentTranslator::translateMessage($message, $target));
    }

    /** "Catch me up" — an on-demand, cached AI summary of a long topic. */
    public function summarize(\App\Models\Topic $topic): JsonResponse
    {
        if (! Llm::configured()) {
            return response()->json(['summary' => null]);
        }
        $posts = $topic->posts()->orderBy('created_at')->limit(250)->get(['body_html']);
        if ($posts->count() < 4) {
            return response()->json(['summary' => null]);
        }

        $key = 'topic_summary:'.$topic->id.':'.$topic->reply_count;
        $summary = \Illuminate\Support\Facades\Cache::remember($key, 86400, function () use ($topic, $posts) {
            $text = 'Topic title: '.$topic->title."\n\n";
            foreach ($posts as $p) {
                $text .= '- '.trim(preg_replace('/\s+/', ' ', strip_tags((string) $p->body_html)))."\n";
            }
            try {
                return trim(Llm::chat(
                    'You are summarizing a forum discussion for someone catching up. Give a 2–3 sentence overview, then 3–5 concise bullet points of the key points, answers, or decisions. Be neutral and factual.',
                    mb_substr($text, 0, 14000),
                    500,
                    'summary',
                ));
            } catch (\Throwable) {
                return '';
            }
        });

        if ($summary === '') {
            \Illuminate\Support\Facades\Cache::forget($key);

            return response()->json(['summary' => null, 'error' => true]);
        }

        return response()->json(['summary' => $summary]);
    }

    /** Admin: configure the LLM provider + Ask. */
    public function settings(): Response
    {
        return Inertia::render('Admin/Ai', [
            'config' => [
                'provider' => Settings::get('ai.provider', 'anthropic'),
                'model' => Settings::get('ai.model', ''),
                'base_url' => Settings::get('ai.base_url', ''),
                'has_key' => Llm::configured(),
                'ask_enabled' => (bool) Settings::get('ask.enabled', true),
                'embed_has_key' => AskIndex::configured(),
                'embed_model' => Settings::get('ai.embed_model', ''),
                'autoanswer_enabled' => (bool) Settings::get('ai.autoanswer_enabled', false),
                'bot_name' => Settings::get('ai.bot_name', 'Convoro Assistant'),
                'moderation_enabled' => (bool) Settings::get('ai.moderation_enabled', false),
                'translate_posts' => (bool) Settings::get('translate.posts', true),
                'price_in' => (float) Settings::get('ai.price_in', 0),
                'price_out' => (float) Settings::get('ai.price_out', 0),
                'monthly_budget' => round(((int) Settings::get('ai.monthly_budget_cents', 0)) / 100, 2),
            ],
            'index' => $this->indexState(),
            'usage' => Llm::usageSummary(),
        ]);
    }

    private function indexState(): array
    {
        return [
            'ready' => AskIndex::ready(),
            'count' => AskIndex::count(),
            'running' => (bool) Settings::get('ask_index.running', false),
            'percent' => (int) Settings::get('ask_index.percent', 0),
            'status' => Settings::get('ask_index.status'),
            'builtAt' => Settings::get('ask_index.built_at'),
        ];
    }

    /** Kick off a full semantic re-index. */
    public function buildIndex(): RedirectResponse
    {
        if (! AskIndex::configured()) {
            return back()->with('status', __('Add a Voyage embeddings key first.'));
        }
        if (Settings::get('ask_index.running')) {
            return back()->with('status', __('An index build is already running.'));
        }
        Settings::setMany(['ask_index.running' => true, 'ask_index.percent' => 0, 'ask_index.status' => __('Starting…')]);
        RunAskIndexJob::dispatch();

        return back()->with('status', __('Building the semantic index in the background…'));
    }

    public function indexStatus(): JsonResponse
    {
        return response()->json($this->indexState());
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'in:anthropic,openai'],
            'model' => ['nullable', 'string', 'max:120'],
            'base_url' => ['nullable', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'ask_enabled' => ['boolean'],
            'embed_model' => ['nullable', 'string', 'max:120'],
            'embed_key' => ['nullable', 'string', 'max:255'],
            'autoanswer_enabled' => ['boolean'],
            'bot_name' => ['nullable', 'string', 'max:60'],
            'moderation_enabled' => ['boolean'],
            'translate_posts' => ['boolean'],
            'price_in' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'price_out' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'monthly_budget' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ]);

        $set = [
            'ai.provider' => $data['provider'],
            'ai.model' => $data['model'] ?? '',
            'ai.base_url' => $data['base_url'] ?? '',
            'ai.embed_model' => $data['embed_model'] ?? '',
            'ask.enabled' => $request->boolean('ask_enabled'),
            'ai.autoanswer_enabled' => $request->boolean('autoanswer_enabled'),
            'ai.bot_name' => $data['bot_name'] ?: 'Convoro Assistant',
            'ai.moderation_enabled' => $request->boolean('moderation_enabled'),
            'translate.posts' => $request->boolean('translate_posts'),
            'ai.price_in' => (float) ($data['price_in'] ?? 0),
            'ai.price_out' => (float) ($data['price_out'] ?? 0),
            'ai.monthly_budget_cents' => (int) round(((float) ($data['monthly_budget'] ?? 0)) * 100),
        ];
        // Only overwrite keys when a new one is supplied (the form never echoes them back).
        if (! empty($data['api_key'])) {
            $set['ai.api_key'] = trim($data['api_key']);
        }
        if (! empty($data['embed_key'])) {
            $set['ai.embed_key'] = trim($data['embed_key']);
        }
        Settings::setMany($set);

        return back()->with('status', __('AI settings saved.'));
    }
}
