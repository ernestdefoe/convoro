<?php

namespace App\Http\Controllers;

use App\Models\ModerationLog;
use App\Support\Present;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Staff audit log — a searchable record of every moderation/admin action. */
class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $action = (string) $request->query('action', '');

        $logs = ModerationLog::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->whereLike('actor_name', "%{$q}%", caseSensitive: false)
                ->orWhereLike('target_label', "%{$q}%", caseSensitive: false)
                ->orWhereLike('reason', "%{$q}%", caseSensitive: false)))
            ->when($action !== '', fn ($w) => $w->where('action', $action))
            ->with(['actor:id,name,avatar_path', 'subject:id,name'])
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (ModerationLog $l) => [
                'id' => $l->id,
                'action' => $l->action,
                'actor' => [
                    'id' => $l->actor?->id ?? $l->actor_id,
                    'name' => $l->actor?->name ?? $l->actor_name ?? __('System'),
                    'avatar' => $l->actor?->avatar_path,
                    'initials' => Present::avatar($l->actor)['initials'],
                    'color' => Present::avatar($l->actor)['color'],
                ],
                'target' => $l->target_type
                    ? ['type' => $l->target_type, 'id' => $l->target_id, 'label' => $l->target_label]
                    : null,
                'subject' => $l->subject ? ['id' => $l->subject->id, 'name' => $l->subject->name] : null,
                'reason' => $l->reason,
                'meta' => $l->meta,
                'ip' => $l->ip,
                'at' => optional($l->created_at)->toIso8601String(),
                'ago' => optional($l->created_at)->diffForHumans(),
            ]);

        return Inertia::render('Admin/AuditLog', [
            'logs' => $logs,
            'q' => $q,
            'action' => $action,
            // distinct actions actually present, for the filter dropdown
            'actions' => ModerationLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
