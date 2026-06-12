<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;background:#f4f5fb;font-family:Inter,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1b2030">
  <div style="max-width:520px;margin:0 auto;padding:32px 20px">
    <div style="background:#fff;border:1px solid #e6e8f0;border-radius:14px;overflow:hidden">
      <div style="background:linear-gradient(135deg,#5b5bd6,#8b5cf6);height:6px"></div>
      <div style="padding:28px">
        <p style="margin:0 0 4px;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#8b8bf0">{{ $site }}</p>
        <h1 style="margin:0 0 14px;font-size:20px;line-height:1.3;color:#1b2030">{{ __(':name invited you to join :site', ['name' => $inviterName, 'site' => $site]) }}</h1>
        <p style="margin:0 0 18px;font-size:14px;line-height:1.6;color:#3c4258">{{ __('You’ve been invited to be part of the :site community. Click below to create your account and say hello.', ['site' => $site]) }}</p>
        @if ($personalMessage)
          <div style="margin:0 0 20px;padding:12px 16px;background:#f7f8fc;border-left:3px solid #5b5bd6;border-radius:8px;font-size:14px;color:#3c4258;font-style:italic">“{{ $personalMessage }}”</div>
        @endif
        <a href="{{ $url }}" style="display:inline-block;background:#5b5bd6;color:#fff;text-decoration:none;font-weight:700;font-size:14px;padding:11px 22px;border-radius:10px">{{ __('Accept invitation →') }}</a>
        <p style="margin:18px 0 0;font-size:12px;color:#8a90a6">{{ __('Or paste this link into your browser:') }}<br><span style="color:#5b5bd6;word-break:break-all">{{ $url }}</span></p>
      </div>
    </div>
    <p style="margin:16px 4px 0;font-size:12px;color:#8a90a6;text-align:center">
      {{ __('Didn’t expect this? You can safely ignore this email.') }}
    </p>
  </div>
</body>
</html>
