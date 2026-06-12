<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribed — Convoro</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f9; font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#1f2233;">
    <div style="max-width:480px; margin:0 auto; padding:64px 20px; text-align:center;">
        <div style="display:inline-block; width:44px; height:44px; line-height:44px; border-radius:12px; background:linear-gradient(135deg,#6366f1,#8b5cf6,#a855f7); color:#fff; font-weight:800; font-size:22px;">C</div>

        <div style="background:#ffffff; border:1px solid #e6e7ef; border-radius:16px; padding:32px; margin-top:24px;">
            <h1 style="margin:0 0 8px; font-size:22px; font-weight:800; letter-spacing:-0.02em;">You're unsubscribed</h1>
            <p style="margin:0 0 4px; color:#6b6f86; font-size:15px; line-height:1.6;">
                Digest emails are now off for <strong>{{ $user->email }}</strong>.
                You won't receive any more summary emails.
            </p>
            <p style="margin:16px 0 0; color:#9499ad; font-size:13px;">
                Changed your mind? You can turn the digest back on any time from your
                notification preferences.
            </p>

            <div style="margin-top:24px;">
                <a href="{{ config('app.url') }}/notifications" style="display:inline-block; background:#5b5bd6; color:#ffffff; text-decoration:none; font-weight:700; font-size:14px; padding:12px 28px; border-radius:10px;">Notification settings</a>
            </div>
        </div>
    </div>
</body>
</html>
