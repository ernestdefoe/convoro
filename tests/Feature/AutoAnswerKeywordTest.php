<?php

namespace Tests\Feature;

use App\Jobs\AutoAnswerTopicJob;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoAnswerKeywordTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_keywords_answers_everything(): void
    {
        Settings::set('ai.autoanswer_keywords', '');
        $this->assertTrue(AutoAnswerTopicJob::passesKeywordGate('Anything at all'));
    }

    public function test_only_matching_topics_pass_the_gate(): void
    {
        Settings::set('ai.autoanswer_keywords', "billing, not working\nhow do I");

        $this->assertTrue(AutoAnswerTopicJob::passesKeywordGate('My Billing is wrong'), 'case-insensitive match');
        $this->assertTrue(AutoAnswerTopicJob::passesKeywordGate('How do I reset my password?'), 'phrase match');
        $this->assertTrue(AutoAnswerTopicJob::passesKeywordGate('The uploader is NOT WORKING'));

        $this->assertFalse(AutoAnswerTopicJob::passesKeywordGate('Show off your setup photos'));
        $this->assertFalse(AutoAnswerTopicJob::passesKeywordGate('Friday game night thread'));
    }
}
