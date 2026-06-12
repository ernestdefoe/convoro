<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>{{ $siteName }} invite</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f9; font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#1f2233;">
    <div style="max-width:520px; margin:0 auto; padding:40px 20px; text-align:center;">
        <div style="display:inline-block; width:44px; height:44px; line-height:44px; border-radius:12px; background:linear-gradient(135deg,#6366f1,#8b5cf6,#a855f7); color:#fff; font-weight:800; font-size:22px;">C</div>

        <div style="background:#ffffff; border:1px solid #e6e7ef; border-radius:16px; padding:32px; margin-top:24px;">
            <h1 style="margin:0 0 8px; font-size:22px; font-weight:800; letter-spacing:-0.02em;">You're invited 🎉</h1>
            <p style="margin:0 0 24px; color:#6b6f86; font-size:15px; line-height:1.6;">
                <strong>{{ $inviterName }}</strong> invited you to join <strong>{{ $siteName }}</strong> —
                a place to start conversations, share, and connect with the community.
            </p>

            <a href="{{ $joinUrl }}" style="display:inline-block; background:#5b5bd6; color:#ffffff; text-decoration:none; font-weight:700; font-size:15px; padding:13px 32px; border-radius:10px;">Accept your invite</a>

            <p style="margin:24px 0 0; color:#9499ad; font-size:12px; line-height:1.6;">
                Or paste this link into your browser:<br>
                <a href="{{ $joinUrl }}" style="color:#6b6f86; word-break:break-all;">{{ $joinUrl }}</a>
            </p>
        </div>

        <p style="color:#9499ad; font-size:12px; margin-top:20px;">
            If you weren't expecting this, you can safely ignore this email.
        </p>
    </div>
</body>
</html>
