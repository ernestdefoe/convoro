<?php

namespace App\Http\Controllers;

use App\Support\EmailReply;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inbound reply-by-email webhook. Point a provider's inbound-parse (Mailgun,
 * Postmark, SES, …) here; it must send the shared key so randoms can't post.
 * Field names are read flexibly to suit whichever provider is in front.
 */
class MailInboundController extends Controller
{
    public function inbound(Request $request): JsonResponse
    {
        if (! EmailReply::enabled()) {
            return response()->json(['ok' => false, 'reason' => 'disabled'], 404);
        }

        $secret = trim((string) Settings::get('mail.reply_secret', ''));
        $given = (string) ($request->header('X-Convoro-Mail-Key') ?: $request->input('key', ''));
        if ($secret === '' || ! hash_equals($secret, $given)) {
            return response()->json(['ok' => false, 'reason' => 'unauthorized'], 401);
        }

        $env = [
            'to' => (string) ($request->input('to') ?: $request->input('recipient') ?: $request->input('To') ?: ''),
            'from' => (string) ($request->input('from') ?: $request->input('sender') ?: $request->input('From') ?: ''),
            'text' => (string) ($request->input('text') ?: $request->input('body-plain') ?: $request->input('TextBody') ?: $request->input('plain') ?: ''),
        ];

        $result = EmailReply::handle($env);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }
}
