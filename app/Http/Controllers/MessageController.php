<?php

namespace App\Http\Controllers;

use App\Events\MessageCreated;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\DirectMessageNotification;
use App\Support\Content;
use App\Support\Notifier;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function index(Request $request): Response
    {
        $me = (int) $request->user()->id;

        $conversations = $request->user()->conversations()
            ->with(['participants', 'lastMessage'])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn ($c) => Present::conversation($c, $me));

        return Inertia::render('Messages/Index', ['conversations' => $conversations]);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $me = (int) $request->user()->id;
        abort_unless($this->isParticipant($conversation, $me), 403);

        // Mark read for this user.
        $conversation->participants()->updateExistingPivot($me, ['last_read_at' => now()]);

        $conversation->load(['participants', 'messages.user']);
        $others = $conversation->participants->where('id', '!=', $me)->values();

        return Inertia::render('Messages/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'isGroup' => (bool) $conversation->is_group,
                'title' => $conversation->is_group
                    ? ($conversation->title ?: $others->pluck('name')->join(', '))
                    : ($others->first()?->name ?? __('Conversation')),
                'partner' => Present::avatar($others->first()),
                'participants' => $conversation->participants->map(fn ($u) => Present::avatar($u))->values(),
            ],
            'messages' => $conversation->messages->sortBy('created_at')->values()->map(fn ($m) => Present::message($m, $me)),
        ]);
    }

    /**
     * Start (or reuse) a conversation. One recipient → a 1:1 DM (reused if it
     * already exists); multiple recipients → a group conversation (optional title).
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],   // back-compat (single)
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);
        $me = (int) $request->user()->id;

        $ids = collect($data['user_ids'] ?? [])
            ->push($data['user_id'] ?? null)
            ->filter()->map('intval')
            ->reject(fn ($id) => $id === $me)
            ->unique()->values();

        abort_if($ids->isEmpty(), 422, __('Choose at least one person to message.'));

        if ($ids->count() === 1) {
            $other = $ids->first();
            $conversation = Conversation::where('is_group', false)
                ->whereHas('participants', fn ($q) => $q->where('users.id', $me))
                ->whereHas('participants', fn ($q) => $q->where('users.id', $other))
                ->first();

            if (! $conversation) {
                $conversation = Conversation::create(['is_group' => false]);
                $conversation->participants()->attach([$me, $other]);
            }
        } else {
            $conversation = Conversation::create(['is_group' => true, 'title' => $data['title'] ?: null]);
            $conversation->participants()->attach($ids->push($me)->unique()->all());
        }

        return redirect()->route('messages.show', $conversation);
    }

    /** Add one or more people to a conversation (turns a DM into a group). */
    public function addParticipants(Request $request, Conversation $conversation): RedirectResponse
    {
        $me = (int) $request->user()->id;
        abort_unless($this->isParticipant($conversation, $me), 403);

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $new = collect($data['user_ids'])->map('intval')->unique()
            ->reject(fn ($id) => $conversation->participants()->where('users.id', $id)->exists())
            ->values();

        if ($new->isNotEmpty()) {
            $conversation->participants()->attach($new->all());
            if ($conversation->participants()->count() > 2) {
                $conversation->update(['is_group' => true]);
            }
        }

        return back();
    }

    public function message(Request $request, Conversation $conversation): RedirectResponse
    {
        $me = (int) $request->user()->id;
        abort_unless($this->isParticipant($conversation, $me), 403);

        $data = $request->validate(['body_html' => ['required', 'string', 'max:20000']]);
        $html = Content::clean($data['body_html']);
        abort_if(trim(strip_tags($html)) === '', 422, __('Empty message.'));

        $message = $conversation->messages()->create(['user_id' => $me, 'body_html' => $html]);
        $conversation->update(['last_message_at' => now()]);

        $message->load('user');
        broadcast(new MessageCreated(Present::message($message, 0), $conversation->id));

        // Notify the other participants.
        foreach ($conversation->participants()->where('users.id', '!=', $me)->get() as $participant) {
            Notifier::send($participant, new DirectMessageNotification($message));
        }

        return back();
    }

    private function isParticipant(Conversation $conversation, int $userId): bool
    {
        return $conversation->participants()->where('users.id', $userId)->exists();
    }
}
