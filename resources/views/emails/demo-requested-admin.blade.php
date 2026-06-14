<!DOCTYPE html>
<html>
<body style="font-family: Inter, system-ui, sans-serif; color: #1b2030; max-width: 560px; margin: 0 auto; padding: 24px;">
    <h1 style="font-size: 20px; color: #5b5bd6;">Demo request</h1>
    <p style="margin: 0 0 16px;">
        <strong>{{ $requester }}</strong>
        @if ($outcome === 'provisioned')
            requested a demo and one was provisioned.
        @else
            requested a demo — all slots were busy, so they were added to the waitlist.
        @endif
    </p>

    <div style="background: #f5f5fb; border-radius: 12px; padding: 14px 18px;">
        <p style="margin: 0 0 6px;"><strong>Outcome:</strong> {{ ucfirst($outcome) }}</p>
        @if ($url)
            <p style="margin: 0 0 6px;"><strong>Demo:</strong> <a href="{{ $url }}" style="color:#5b5bd6;">{{ $url }}</a></p>
        @endif
        <p style="margin: 0 0 6px;"><strong>Active demos:</strong> {{ $activeCount }}</p>
        <p style="margin: 0;"><strong>Waitlist:</strong> {{ $waitlistCount }}</p>
    </div>
</body>
</html>
