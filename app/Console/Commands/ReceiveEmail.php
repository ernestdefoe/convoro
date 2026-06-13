<?php

namespace App\Console\Commands;

use App\Support\EmailReply;
use Illuminate\Console\Command;
use ZBateson\MailMimeParser\Message;

/**
 * Reads a raw RFC-822 email from stdin and, if it's a reply to a notification,
 * posts it. Wired up on a self-hosted server by piping reply+*@<domain> mail to
 * this command from Postfix (a virtual alias / transport). Always exits 0 so a
 * processing hiccup never bounces the sender's mail.
 */
class ReceiveEmail extends Command
{
    protected $signature = 'convoro:receive-email {recipient? : envelope recipient incl +token (Postfix ${original_recipient})}';

    protected $description = 'Process a raw inbound email (reply-by-email) from stdin';

    public function handle(): int
    {
        if (! EmailReply::enabled()) {
            return self::SUCCESS;
        }

        $raw = file_get_contents('php://stdin');
        if (! is_string($raw) || trim($raw) === '') {
            return self::SUCCESS;
        }

        try {
            $message = Message::from($raw, false);
            // Prefer the envelope recipient Postfix hands us (carries the +token
            // even when the To header was rewritten); fall back to headers.
            $to = $this->argument('recipient')
                ?: $message->getHeaderValue('x-original-to')
                ?: $message->getHeaderValue('delivered-to')
                ?: $message->getHeaderValue('to');
            $from = $message->getHeaderValue('from');
            $text = $message->getTextContent();
            if (($text === null || trim($text) === '') && $message->getHtmlContent()) {
                $text = trim(strip_tags((string) $message->getHtmlContent()));
            }

            $result = EmailReply::handle([
                'to' => (string) $to,
                'from' => (string) $from,
                'text' => (string) $text,
            ]);

            \Illuminate\Support\Facades\Log::info('reply-by-email', $result + ['from' => $from]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('reply-by-email parse failed: '.$e->getMessage());
        }

        return self::SUCCESS;
    }
}
