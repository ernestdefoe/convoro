<!DOCTYPE html>
<html>
<body style="font-family: Inter, system-ui, sans-serif; color: #1b2030; max-width: 560px; margin: 0 auto; padding: 24px;">
    <h1 style="font-size: 22px; color: #5b5bd6;">Your Convoro demo is ready 🎉</h1>
    <p>Thanks for trying Convoro! We've spun up a private, fully-functional community just for you. You're the admin, so explore everything — the WYSIWYG composer, live theme editor, extensions, the lot.</p>

    <div style="background: #f5f5fb; border-radius: 12px; padding: 16px 20px; margin: 20px 0;">
        <p style="margin: 0 0 10px;"><strong>Your demo:</strong> <a href="{{ $url }}" style="color: #5b5bd6;">{{ $url }}</a></p>
        <p style="margin: 0 0 6px;"><strong>Username:</strong> {{ $login }}</p>
        <p style="margin: 0;"><strong>Password:</strong> <code style="background:#fff;padding:2px 6px;border-radius:4px;">{{ $password }}</code></p>
    </div>

    <p style="color: #6b7280; font-size: 14px;">Your demo is yours until <strong>{{ $expires }}</strong>, then it's automatically removed. We'll send a reminder 24 hours before. Outgoing email is disabled inside the demo, so post and click freely.</p>

    <p style="margin-top: 24px;">
        <a href="{{ $url }}" style="background:#5b5bd6;color:#fff;text-decoration:none;font-weight:600;padding:11px 22px;border-radius:10px;display:inline-block;">Open your demo</a>
    </p>
</body>
</html>
