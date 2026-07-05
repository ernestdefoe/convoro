<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\IpBan;
use App\Models\Post;
use App\Models\Report;
use App\Models\Topic;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Staff moderation suite: report queue, spammer/IP bans, IP visibility, moving content. */
class ModerationController extends Controller
{
    public function index(): Response
    {
        $reports = Report::with('reporter')->where('status', 'open')->latest()->limit(100)->get()
            ->map(fn (Report $r) => [
                'id' => $r->id,
                'type' => $r->reportable_type,
                'reason' => $r->reason,
                'reporter' => $r->reporter?->name,
                'source' => $r->source ?? 'user',
                'ai' => $r->ai_meta,
                'createdAt' => optional($r->created_at)->diffForHumans(),
                'subject' => $this->subjectSummary($r),
            ]);

        $bannedUsers = User::whereNotNull('banned_at')->latest('banned_at')->limit(100)->get()
            ->map(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email,
                'reason' => $u->ban_reason, 'lastIp' => $u->last_ip, 'registrationIp' => $u->registration_ip,
                'bannedAt' => optional($u->banned_at)->diffForHumans(),
            ]);

        $ipBans = IpBan::latest()->limit(200)->get()
            ->map(fn (IpBan $b) => ['id' => $b->id, 'ip' => $b->ip_address, 'reason' => $b->reason, 'createdAt' => optional($b->created_at)->diffForHumans()]);

        return Inertia::render('Admin/Moderation', [
            'reports' => $reports,
            'bannedUsers' => $bannedUsers,
            'ipBans' => $ipBans,
            'categories' => Category::orderBy('position')->get(['id', 'name']),
            'counts' => ['reports' => $reports->count(), 'bannedUsers' => $bannedUsers->count(), 'ipBans' => $ipBans->count()],
        ]);
    }

    /** Compact, staff-only summary of a reported post or user (includes IPs). */
    private function subjectSummary(Report $r): ?array
    {
        if ($r->reportable_type === 'post') {
            $p = Post::with('user', 'topic')->find($r->reportable_id);
            if (! $p) {
                return null;
            }

            return [
                'kind' => 'post', 'id' => $p->id,
                'excerpt' => \Illuminate\Support\Str::limit(trim(strip_tags($p->body_html)), 240),
                'authorId' => $p->user_id, 'author' => $p->user?->name,
                'ip' => $p->ip_address,
                'topic' => $p->topic?->title, 'topicSlug' => $p->topic?->slug,
                // Whether the post is still hidden — the queue shows "Approve &
                // restore" for ANY held post (guard-held or AI-held). Plain
                // "Resolve" closes the report but deliberately does not unhide.
                'held' => (bool) $p->hidden,
            ];
        }

        $u = User::find($r->reportable_id);

        return $u ? [
            'kind' => 'user', 'id' => $u->id, 'author' => $u->name, 'authorId' => $u->id,
            'ip' => $u->last_ip, 'registrationIp' => $u->registration_ip,
            'banned' => $u->isBanned(),
        ] : null;
    }

    public function resolveReport(Report $report): RedirectResponse
    {
        $report->update(['status' => 'resolved', 'resolved_by' => request()->user()->id, 'resolved_at' => now()]);
        AuditLog::record('report.resolve', $report);

        return back()->with('status', __('Report resolved.'));
    }

    public function dismissReport(Report $report): RedirectResponse
    {
        $report->update(['status' => 'dismissed', 'resolved_by' => request()->user()->id, 'resolved_at' => now()]);
        AuditLog::record('report.dismiss', $report);

        return back()->with('status', __('Report dismissed.'));
    }

    /** Delete a post (and decrement its topic's reply count). */
    public function deletePost(Post $post): RedirectResponse
    {
        $topic = $post->topic;
        if ($topic && ! $post->is_first && $topic->reply_count > 0) {
            $topic->decrement('reply_count');
        }
        AuditLog::record('post.delete', $post, subject: $post->user);
        $post->delete();
        Report::where('reportable_type', 'post')->where('reportable_id', $post->id)
            ->update(['status' => 'resolved', 'resolved_at' => now()]);

        return back()->with('status', __('Post deleted.'));
    }

    /** Approve a held post: restore it publicly and clear its open reports. */
    public function approvePost(Post $post): RedirectResponse
    {
        $post->forceFill(['hidden' => false])->saveQuietly();

        // Approving a held opening post also publishes its (held) topic so it
        // re-enters the listings, and bumps the activity timestamp.
        if ($post->is_first && $post->topic && $post->topic->hidden) {
            $post->topic->forceFill(['hidden' => false, 'last_post_at' => now()])->saveQuietly();
        }

        Report::where('reportable_type', 'post')->where('reportable_id', $post->id)
            ->where('status', 'open')
            ->update(['status' => 'resolved', 'resolved_by' => request()->user()->id, 'resolved_at' => now()]);
        AuditLog::record('post.approve', $post, subject: $post->user);

        return back()->with('status', __('Post approved.'));
    }

    /** Ban a user (spammer). Optionally ban their last IP and wipe their posts. */
    public function banUser(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is_admin, 403, __('You cannot ban an administrator.'));

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
            'ban_ip' => ['boolean'],
            'delete_posts' => ['boolean'],
        ]);

        $user->forceFill(['banned_at' => now(), 'ban_reason' => $data['reason'] ?? null])->save();

        if (($data['ban_ip'] ?? false) && $user->last_ip) {
            $this->addIpBan($user->last_ip, __('Spammer: :name', ['name' => $user->name]), $request->user()->id);
        }

        if ($data['delete_posts'] ?? false) {
            Post::where('user_id', $user->id)->where('is_first', false)->delete();
        }

        Report::where('reportable_type', 'user')->where('reportable_id', $user->id)
            ->update(['status' => 'resolved', 'resolved_at' => now()]);

        AuditLog::record('user.ban', $user, subject: $user, reason: $data['reason'] ?? null, meta: array_filter([
            'ban_ip' => (bool) ($data['ban_ip'] ?? false),
            'deleted_posts' => (bool) ($data['delete_posts'] ?? false),
        ]));

        return back()->with('status', __('“:name” has been banned.', ['name' => $user->name]));
    }

    public function unbanUser(User $user): RedirectResponse
    {
        $user->forceFill(['banned_at' => null, 'ban_reason' => null])->save();
        AuditLog::record('user.unban', $user, subject: $user);

        return back()->with('status', __('“:name” has been reinstated.', ['name' => $user->name]));
    }

    public function banIp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ip_address' => ['required', 'ip'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $this->addIpBan($data['ip_address'], $data['reason'] ?? null, $request->user()->id);
        AuditLog::record('ip.ban', target: ['ip', $data['ip_address'], $data['ip_address']], reason: $data['reason'] ?? null);

        return back()->with('status', __('Banned :ip.', ['ip' => $data['ip_address']]));
    }

    public function unbanIp(IpBan $ipBan): RedirectResponse
    {
        $ip = $ipBan->ip_address;
        $ipBan->delete();
        IpBan::flush($ip);
        AuditLog::record('ip.unban', target: ['ip', $ip, $ip]);

        return back()->with('status', __('Unbanned :ip.', ['ip' => $ip]));
    }

    /** Move a topic into a different category. */
    public function moveTopic(Request $request, Topic $topic): RedirectResponse
    {
        $data = $request->validate(['category_id' => ['nullable', 'integer', 'exists:categories,id']]);
        $from = $topic->category_id;
        $topic->update(['category_id' => $data['category_id'] ?? null]);
        AuditLog::record('topic.move', $topic, meta: ['from_category_id' => $from, 'to_category_id' => $data['category_id'] ?? null]);

        return back()->with('status', __('Topic moved.'));
    }

    /** Search topics (for the move-post picker). */
    public function topicSearch(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $topics = Topic::query()
            ->when($q !== '', fn ($query) => $query->where('title', 'like', '%'.addcslashes($q, '%_\\').'%'))
            ->latest('last_post_at')->limit(10)
            ->get(['id', 'title', 'slug'])
            ->map(fn ($t) => ['id' => $t->id, 'title' => $t->title, 'slug' => $t->slug]);

        return response()->json(['topics' => $topics]);
    }

    /** Move a single post to a different existing topic. */
    public function movePost(Request $request, Post $post): RedirectResponse
    {
        $data = $request->validate(['topic_id' => ['required', 'integer', 'exists:topics,id']]);
        abort_if($post->is_first, 422, __('The opening post cannot be moved on its own.'));

        $from = $post->topic;
        $post->update(['topic_id' => $data['topic_id']]);

        if ($from && $from->reply_count > 0) {
            $from->decrement('reply_count');
        }
        Topic::where('id', $data['topic_id'])->increment('reply_count');
        AuditLog::record('post.move', $post, subject: $post->user, meta: ['from_topic_id' => $from?->id, 'to_topic_id' => $data['topic_id']]);

        return back()->with('status', __('Post moved.'));
    }

    private function addIpBan(string $ip, ?string $reason, int $by): void
    {
        IpBan::updateOrCreate(['ip_address' => $ip], ['reason' => $reason, 'created_by' => $by]);
        IpBan::flush($ip);
    }
}
