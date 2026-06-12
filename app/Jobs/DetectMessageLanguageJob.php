<?php

namespace App\Jobs;

use App\Models\Message;
use App\Support\ContentTranslator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Detect and store a direct message's source language so readers with
 * auto-translate on know whether it needs translating. Mirrors
 * DetectPostLanguageJob for conversation messages.
 */
class DetectMessageLanguageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function __construct(public int $messageId) {}

    public function handle(): void
    {
        $message = Message::find($this->messageId);
        if (! $message) {
            return;
        }
        $code = ContentTranslator::detect((string) $message->body_html);
        if ($code) {
            $message->updateQuietly(['detected_locale' => $code]);
        }
    }
}
