<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convoro digest</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f9; font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#1f2233;">
    <div style="max-width:560px; margin:0 auto; padding:32px 20px;">

        {{-- Header --}}
        <div style="text-align:center; margin-bottom:24px;">
            <div style="display:inline-block; width:40px; height:40px; line-height:40px; border-radius:12px; background:linear-gradient(135deg,#6366f1,#8b5cf6,#a855f7); color:#fff; font-weight:800; font-size:20px;">C</div>
            <div style="font-size:18px; font-weight:800; margin-top:8px; letter-spacing:-0.02em;">Convoro</div>
        </div>

        <div style="background:#ffffff; border:1px solid #e6e7ef; border-radius:16px; padding:28px;">
            <h1 style="margin:0 0 4px; font-size:20px; font-weight:800; letter-spacing:-0.02em;">{{ __('Hi :name,', ['name' => $user->name]) }}</h1>
            <p style="margin:0 0 20px; color:#6b6f86; font-size:14px;">{{ __('Here\'s what happened in the community over the :period.', ['period' => $periodLabel]) }}</p>

            @if ($unread > 0)
                <div style="background:#eef0fe; border-radius:12px; padding:12px 16px; margin-bottom:20px; font-size:14px; color:#4338ca;">
                    🔔 {!! __('You have <strong>:count</strong> unread :notifications.', ['count' => $unread, 'notifications' => \Illuminate\Support\Str::plural(__('notification'), $unread)]) !!}
                </div>
            @endif

            @forelse ($topics as $topic)
                <div style="padding:16px 0; border-top:1px solid #eef0f4;">
                    <a href="{{ $topic['url'] }}" style="font-size:16px; font-weight:700; color:#1f2233; text-decoration:none;">{{ $topic['title'] }}</a>
                    <p style="margin:6px 0 8px; color:#6b6f86; font-size:14px; line-height:1.5;">{{ $topic['excerpt'] }}</p>
                    <div style="font-size:12px; color:#9499ad;">{{ __('by :author', ['author' => $topic['author']]) }} · {{ $topic['replyCount'] }} {{ \Illuminate\Support\Str::plural(__('reply'), $topic['replyCount']) }}</div>
                </div>
            @empty
                <p style="color:#6b6f86; font-size:14px;">{{ __('No new topics this time — check in to start a conversation.') }}</p>
            @endforelse

            <div style="text-align:center; margin-top:24px;">
                <a href="{{ config('app.url') }}" style="display:inline-block; background:#5b5bd6; color:#ffffff; text-decoration:none; font-weight:700; font-size:14px; padding:12px 28px; border-radius:10px;">{{ __('Open Convoro') }}</a>
            </div>
        </div>

        <p style="text-align:center; color:#9499ad; font-size:12px; margin-top:20px;">
            {{ __('You\'re receiving this because your digest is set to :frequency.', ['frequency' => $user->digest_frequency]) }}
            <br>{!! __('Change it anytime in your :link', ['link' => '<a href="' . config('app.url') . '/profile" style="color:#6b6f86;">' . __('profile settings') . '</a>']) !!},
            {{ __('or') }} <a href="{{ $unsubscribeUrl }}" style="color:#6b6f86;">{{ __('unsubscribe from digests') }}</a>.
        </p>
    </div>
</body>
</html>
