<?php

namespace App\Support;

use App\Models\Post;
use App\Models\Topic;
use App\Models\User;

/**
 * Reply-by-email. Notification emails carry a Reply-To of
 * reply+<token>@<domain>; the token is an HMAC-signed (user, topic) pair, so it
 * can only be produced by us and only works for the member it was sent to.
 * Inbound mail (via the webhook or the convoro:receive-email pipe) is parsed,
 * the quoted history stripped, and a reply posted as that member.
 */
class EmailReply
{
    public static function enabled(): bool
    {
        return (bool) Settings::get('mail.reply_enabled', false) && self::domain() !== '';
    }

    public static function domain(): string
    {
        return trim((string) Settings::get('mail.reply_domain', ''));
    }

    private static function secret(): string
    {
        // A dedicated secret so rotating it invalidates outstanding tokens
        // without touching the app key.
        $s = (string) Settings::get('mail.reply_secret', '');
        if ($s === '') {
            $s = bin2hex(random_bytes(16));
            Settings::set('mail.reply_secret', $s);
        }

        return $s;
    }

    /** Build the Reply-To address for a notification sent to $recipient about a topic. */
    public static function addressFor(User $recipient, int $topicId): ?string
    {
        if (! self::enabled()) {
            return null;
        }
        $token = self::sign(['u' => (int) $recipient->id, 't' => $topicId]);

        return 'reply+'.$token.'@'.self::domain();
    }

    public static function sign(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $body = self::b64u($json);
        $sig = self::b64u(hash_hmac('sha256', $body, self::secret(), true));

        return $body.'.'.substr($sig, 0, 22);
    }

    /** @return array{u:int,t:int}|null */
    public static function verify(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }
        [$body, $sig] = $parts;
        $expected = substr(self::b64u(hash_hmac('sha256', $body, self::secret(), true)), 0, 22);
        if (! hash_equals($expected, $sig)) {
            return null;
        }
        $data = json_decode((string) self::b64uDecode($body), true);

        return (is_array($data) && isset($data['u'], $data['t'])) ? ['u' => (int) $data['u'], 't' => (int) $data['t']] : null;
    }

    /** Pull the token out of a reply+<token>@domain recipient address. */
    public static function extractToken(string $to): ?string
    {
        if (preg_match('/reply\+([A-Za-z0-9_\-.]+)@/i', $to, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Process one inbound message. $env = ['to' => ..., 'from' => ..., 'text' => ...].
     *
     * @return array{ok:bool, reason:string, post_id?:int}
     */
    public static function handle(array $env): array
    {
        if (! self::enabled()) {
            return ['ok' => false, 'reason' => 'disabled'];
        }
        $token = self::extractToken((string) ($env['to'] ?? ''));
        if (! $token || ! ($claim = self::verify($token))) {
            return ['ok' => false, 'reason' => 'bad_token'];
        }

        $user = User::find($claim['u']);
        $topic = Topic::find($claim['t']);
        if (! $user || ! $topic) {
            return ['ok' => false, 'reason' => 'gone'];
        }

        // The From address must match the member the token was issued to —
        // the token is secret, but this stops a forwarded email being replayed.
        $from = self::emailOf((string) ($env['from'] ?? ''));
        if ($from === '' || strcasecmp($from, (string) $user->email) !== 0) {
            return ['ok' => false, 'reason' => 'from_mismatch'];
        }

        if ($user->isBanned() || $topic->is_locked) {
            return ['ok' => false, 'reason' => 'not_allowed'];
        }

        $text = self::stripQuoted((string) ($env['text'] ?? ''));
        if (trim($text) === '') {
            return ['ok' => false, 'reason' => 'empty'];
        }

        $html = Content::clean(self::textToHtml($text));
        if (TrustLevels::gatesContent($user)) {
            $html = TrustLevels::stripLinksMedia($html);
        }
        if (trim(strip_tags($html)) === '') {
            return ['ok' => false, 'reason' => 'empty'];
        }

        $post = ReplyPoster::create($user, $topic, $html);

        return ['ok' => true, 'reason' => 'posted', 'post_id' => $post->id];
    }

    /** Strip quoted reply history, signatures and "On … wrote:" attributions. */
    public static function stripQuoted(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $lines = explode("\n", $text);
        $out = [];
        foreach ($lines as $line) {
            $t = rtrim($line);
            // Common "On <date>, <name> wrote:" attribution line — stop here.
            if (preg_match('/^\s*On .+wrote:\s*$/', $t) || preg_match('/^\s*-{2,}\s*Original Message\s*-{2,}/i', $t)) {
                break;
            }
            // A signature delimiter — drop the rest.
            if ($t === '-- ') {
                break;
            }
            // Quoted line.
            if (preg_match('/^\s*>/', $t)) {
                continue;
            }
            $out[] = $t;
        }

        return rtrim(implode("\n", $out));
    }

    public static function textToHtml(string $text): string
    {
        $e = fn ($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $paras = preg_split('/\n{2,}/', trim($text));

        return collect($paras)
            ->filter(fn ($p) => trim($p) !== '')
            ->map(fn ($p) => '<p>'.nl2br($e(trim($p))).'</p>')
            ->implode('');
    }

    private static function emailOf(string $from): string
    {
        if (preg_match('/<([^>]+)>/', $from, $m)) {
            return strtolower(trim($m[1]));
        }

        return strtolower(trim($from));
    }

    private static function b64u(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private static function b64uDecode(string $s): string
    {
        return base64_decode(strtr($s, '-_', '+/')) ?: '';
    }
}
