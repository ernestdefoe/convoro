<?php

namespace App\Http\Controllers;

use App\Models\Draft;
use App\Models\Topic;
use App\Support\Content;
use App\Support\TopicPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** Member drafts and scheduled posts. */
class DraftController extends Controller
{
    public function index(Request $request): Response
    {
        $drafts = Draft::where('user_id', $request->user()->id)->latest('updated_at')->get()
            ->map(fn (Draft $d) => [
                'id' => $d->id,
                'title' => trim((string) $d->title) ?: __('Untitled draft'),
                'excerpt' => Str::limit(trim(strip_tags((string) $d->body_html)), 140),
                'scheduledAt' => optional($d->scheduled_at)->toIso8601String(),
                'scheduledLabel' => $d->scheduled_at ? $d->scheduled_at->diffForHumans() : null,
                'isScheduled' => $d->isScheduled(),
                'updated' => optional($d->updated_at)->diffForHumans(),
                'hasPoll' => ! empty($d->poll['question']),
            ]);

        return Inertia::render('Drafts', ['drafts' => $drafts]);
    }

    /** Create or update a draft (optionally scheduled). */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'draft_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:160'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'tags' => ['array'],
            'tags.*' => ['integer'],
            'body_html' => ['nullable', 'string', 'max:120000'],
            'body_json' => ['nullable', 'string', 'max:200000'],
            'cover' => ['nullable', 'string', 'max:2048'],
            'poll' => ['nullable', 'array'],
            'scheduled_at' => ['nullable', 'date', 'after:now', 'before:'.now()->addYear()->toDateString()],
        ]);

        $attrs = [
            'user_id' => $request->user()->id,
            'title' => $data['title'] ?? null,
            'body_html' => $data['body_html'] ?? null,
            'body_json' => $data['body_json'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'tags' => $data['tags'] ?? [],
            'cover' => $data['cover'] ?? null,
            'poll' => $data['poll'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
        ];

        if (! empty($data['draft_id'])) {
            Draft::where('user_id', $request->user()->id)->findOrFail($data['draft_id'])->update($attrs);
        } else {
            Draft::create($attrs);
        }

        return redirect()->route('drafts.index')
            ->with('status', ! empty($data['scheduled_at']) ? __('Post scheduled.') : __('Draft saved.'));
    }

    public function publish(Request $request, Draft $draft): RedirectResponse
    {
        abort_unless($draft->user_id === $request->user()->id, 403);
        $topic = self::publishDraft($draft);

        return $topic
            ? redirect()->route('topics.show', $topic)
            : back()->with('status', __('Add a title and body before publishing.'));
    }

    public function destroy(Request $request, Draft $draft): RedirectResponse
    {
        abort_unless($draft->user_id === $request->user()->id, 403);
        $draft->delete();

        return back()->with('status', __('Draft deleted.'));
    }

    /** Turn a draft into a published topic. Returns null if the draft is incomplete. */
    public static function publishDraft(Draft $draft): ?Topic
    {
        $title = trim((string) $draft->title);
        $html = Content::clean((string) $draft->body_html);
        if ($title === '' || trim(strip_tags($html)) === '') {
            return null;
        }

        $topic = TopicPublisher::publish($draft->user, [
            'title' => $title,
            'body_html' => $html,
            'body_json' => $draft->body_json,
            'category_id' => $draft->category_id,
            'tags' => $draft->tags ?? [],
            'cover' => $draft->cover,
            'poll' => $draft->poll,
        ]);
        $draft->delete();

        return $topic;
    }
}
