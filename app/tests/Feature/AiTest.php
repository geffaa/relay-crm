<?php

namespace Tests\Feature;

use App\Models\AiSummary;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\LeadScore;
use App\Models\Message;
use App\Models\Pipeline;
use App\Models\Stage;
use App\Models\WhatsAppNumber;
use App\Services\AI\FakeAIClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiTest extends TestCase
{
    use RefreshDatabase;

    private Conversation $conversation;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();
        FakeAIClient::$nextResponse = null;

        $this->contact = Contact::forceCreate([
            'company_id' => $this->company->id,
            'name' => 'Dewi Anggraini',
            'phone_e164' => '+6281234567001',
            'email' => 'dewi@sukhaliving.test',
            'source' => 'whatsapp',
            'owner_id' => $this->rep->id,
        ]);

        $number = new WhatsAppNumber(['phone_number_id' => 'PN-AI', 'display_phone' => '+62811000003', 'mode' => 'simulated']);
        $number->company_id = $this->company->id;
        $number->save();

        $this->conversation = new Conversation([
            'contact_id' => $this->contact->id,
            'whatsapp_number_id' => $number->id,
            'status' => 'open',
            'assignee_id' => $this->rep->id,
        ]);
        $this->conversation->company_id = $this->company->id;
        $this->conversation->save();
    }

    protected function tearDown(): void
    {
        FakeAIClient::$nextResponse = null;
        parent::tearDown();
    }

    private function seedMessages(int $count = 5): void
    {
        $texts = [
            'in' => 'Hi, we need a Shopify migration before 12.12. Budget around 60 million.',
            'out' => 'Sure, we can help. Let me prepare a quote.',
        ];

        for ($i = 0; $i < $count; $i++) {
            $direction = $i % 2 === 0 ? 'in' : 'out';
            $this->conversation->messages()->create([
                'direction' => $direction,
                'type' => 'text',
                'body' => $texts[$direction],
                'status' => $direction === 'in' ? 'delivered' : 'read',
                'created_at' => now()->subMinutes($count - $i),
            ]);
        }
    }

    public function test_summary_points_always_cite_real_messages(): void
    {
        $this->seedMessages(6);

        $this->actingAs($this->rep)
            ->post("/inbox/{$this->conversation->id}/summarize")
            ->assertSessionHasNoErrors();

        $summary = AiSummary::latest('id')->firstOrFail();
        $allMessageIds = $this->conversation->messages()->pluck('id')->all();

        $this->assertNotEmpty($summary->points);

        foreach ($summary->points as $point) {
            $this->assertNotEmpty($point['message_ids'], 'Every point must cite sources');
            foreach ($point['message_ids'] as $id) {
                $this->assertContains($id, $allMessageIds, 'Cited id must be a real message of this conversation');
            }
        }

        $this->assertDatabaseHas('ai_runs', ['kind' => 'summarize', 'status' => 'ok', 'company_id' => $this->company->id]);
    }

    public function test_points_with_fabricated_message_ids_are_rejected(): void
    {
        $this->seedMessages(4);

        FakeAIClient::$nextResponse = json_encode([
            'points' => [
                ['text' => 'Totally invented claim.', 'message_ids' => [999001, 999002]],
                ['text' => 'Real point grounded in the chat.', 'message_ids' => [$this->conversation->messages()->first()->id]],
            ],
            'next_step' => 'Quote.',
            'confidence' => 0.5,
        ]);

        $this->actingAs($this->rep)
            ->post("/inbox/{$this->conversation->id}/summarize")
            ->assertSessionHasNoErrors();

        $summary = AiSummary::latest('id')->firstOrFail();

        $this->assertCount(1, $summary->points, 'Only the sourced point survives');
        $this->assertSame('Real point grounded in the chat.', $summary->points[0]['text']);
    }

    public function test_summary_rejected_when_no_point_is_sourced(): void
    {
        $this->seedMessages(4);

        FakeAIClient::$nextResponse = json_encode([
            'points' => [['text' => 'Unsourced claim.', 'message_ids' => []]],
            'next_step' => '',
        ]);

        $this->actingAs($this->rep)
            ->post("/inbox/{$this->conversation->id}/summarize")
            ->assertSessionHasErrors('summary');

        $this->assertSame(0, AiSummary::count());
    }

    public function test_draft_reply_returns_text_and_never_sends(): void
    {
        $this->seedMessages(4);

        $outBefore = Message::where('conversation_id', $this->conversation->id)->where('direction', 'out')->count();

        $this->actingAs($this->rep)
            ->postJson("/inbox/{$this->conversation->id}/draft-reply")
            ->assertOk()
            ->assertJsonStructure(['draft']);

        // No new outbound message may appear from drafting.
        $this->assertSame($outBefore, Message::where('conversation_id', $this->conversation->id)->where('direction', 'out')->count());
    }

    public function test_lead_score_provides_structured_plus_ai_with_reasons(): void
    {
        $this->seedMessages(6);

        $pipeline = Pipeline::forceCreate(['company_id' => $this->company->id, 'name' => 'P', 'is_default' => true]);
        $stage = Stage::forceCreate(['pipeline_id' => $pipeline->id, 'name' => 'New', 'position' => 1, 'kind' => 'open']);
        Deal::forceCreate([
            'company_id' => $this->company->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'contact_id' => $this->contact->id,
            'title' => 'Shopify migration',
            'value' => 60000,
        ]);

        $this->actingAs($this->rep)
            ->post("/contacts/{$this->contact->id}/score")
            ->assertSessionHasNoErrors();

        $score = LeadScore::latest('id')->firstOrFail();

        $this->assertEqualsWithDelta($score->score, max(0, min(100, $score->structured_part + $score->ai_adjustment)), 0.001);
        $this->assertGreaterThanOrEqual(3, count($score->reasons), 'At least 3 reasons must be shown');
        $this->assertSame($score->score, $this->contact->fresh()->score);

        // Throttled: immediate recompute without new messages doesn't add a row.
        $this->actingAs($this->rep)->post("/contacts/{$this->contact->id}/score");
        $this->assertSame(1, LeadScore::count());
    }

    public function test_manager_override_wins_over_recomputation(): void
    {
        $this->contact->update(['score' => 55, 'score_updated_at' => now()]);

        $this->actingAs($this->manager)
            ->post("/contacts/{$this->contact->id}/score-override", [
                'to' => 85,
                'reason' => 'Verbal confirmation of budget on the call',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(85, $this->contact->fresh()->score);
        $this->assertDatabaseHas('score_overrides', [
            'contact_id' => $this->contact->id,
            'user_id' => $this->manager->id,
            'to' => 85,
        ]);

        // A rep cannot override.
        $this->actingAs($this->rep)
            ->post("/contacts/{$this->contact->id}/score-override", ['to' => 10, 'reason' => 'nope'])
            ->assertForbidden();
    }

    public function test_ai_failure_does_not_break_the_endpoint(): void
    {
        $this->seedMessages(4);

        // Simulate AI returning garbage: parse fails gracefully.
        FakeAIClient::$nextResponse = 'not json at all';

        $this->actingAs($this->rep)
            ->post("/inbox/{$this->conversation->id}/summarize")
            ->assertSessionHasErrors('summary');

        $this->assertDatabaseHas('ai_runs', ['kind' => 'summarize', 'status' => 'error']);
        $this->assertSame(0, AiSummary::count());
    }
}
