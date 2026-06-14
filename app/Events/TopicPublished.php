<?php

namespace App\Events;

use App\Models\Topic;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a member publishes a topic (composer, a saved draft, or a scheduled
 * post) — NOT for imports/seeds/demos, which create topics directly. A reusable
 * hook for extensions that react to genuinely-published content (e.g. auto-post
 * to social networks).
 */
class TopicPublished
{
    use Dispatchable;

    public function __construct(public Topic $topic) {}
}
