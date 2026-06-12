<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Support\ContentTranslator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageTranslationTest extends TestCase
{
    use RefreshDatabase;

    private function dm(User $a, User $b): Conversation
    {
        $c = Conversation::create(['is_group' => false]);
        $c->participants()->attach([$a->id, $b->id]);

        return $c;
    }

    public function test_non_participant_cannot_translate_a_message(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $stranger = User::factory()->create();
        $conv = $this->dm($a, $b);
        $msg = $conv->messages()->create(['user_id' => $a->id, 'body_html' => '<p>Hola</p>', 'detected_locale' => 'es']);

        $this->actingAs($stranger)->postJson("/api/messages/{$msg->id}/translate")->assertForbidden();
    }

    public function test_participant_may_translate(): void
    {
        $a = User::factory()->create(['locale' => 'en']);
        $b = User::factory()->create();
        $conv = $this->dm($a, $b);
        $msg = $conv->messages()->create(['user_id' => $b->id, 'body_html' => '<p>Hi</p>', 'detected_locale' => 'en']);

        // No LLM configured in tests → returns the original, not an error.
        $this->actingAs($a)->postJson("/api/messages/{$msg->id}/translate", ['locale' => 'en'])
            ->assertOk()
            ->assertJson(['translated' => false]);
    }

    public function test_service_skips_when_already_in_target_language(): void
    {
        $u = User::factory()->create();
        $conv = $this->dm($u, User::factory()->create());
        $msg = $conv->messages()->create(['user_id' => $u->id, 'body_html' => '<p>Hi</p>', 'detected_locale' => 'en']);

        $result = ContentTranslator::translateMessage($msg->fresh(), 'en');

        $this->assertFalse($result['translated']);
        $this->assertSame('<p>Hi</p>', $result['html']);
    }
}
