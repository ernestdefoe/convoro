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
        $other = $conversation->participants->where('id', '!=', $me)->first();

        return Inertia::render('Messages/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->is_group ? ($conversation->title ?: $conversation->participants->where('id', '!=', $me)->pluck('name')->join(', ')) : ($other?->name ?? 'Conversation'),
                'partner' => Present::avatar($other),
            ],
            'messages' => $conversation->messages->sortBy('created_at')->values()->map(fn ($m) => Present::message($m, $me)),
        ]);
    }

    /** Start (or reuse) a 1:1 conversation with another user. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $me = (int) $request->user()->id;
        $other = (int) $data['user_id'];
        abort_if($other === $me, 422, 'You cannot message yourself.');

        $conversation = Conversation::where('is_group', false)
            ->whereHas('participants', fn ($q) => $q->where('users.id', $me))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $other))
            ->first();

        if (! $conversation) {
            $conversation = Conversation::create(['is_group' => false]);
            $conversation->participants()->attach([$me, $other]);
        }

        return redirect()->route('messages.show', $conversation);
    }

    public function message(Request $request, Conversation $conversation): RedirectResponse
    {
        $me = (int) $request->user()->id;
        abort_unless($this->isParticipant($conversation, $me), 403);

        $data = $request->validate(['body_html' => ['required', 'string', 'max:20000']]);
        $html = Content::clean($data['body_html']);
        abort_if(trim(strip_tags($html)) === '', 422, 'Empty message.');

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
