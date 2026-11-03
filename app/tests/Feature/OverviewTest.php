<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Stage;
use App\Models\WhatsAppNumber;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverviewTest extends TestCase
{
    use RefreshDatabase;

    private WhatsAppNumber $number;

    protected function setUp(): void
    {
        parent::setUp();

        $this->number = new WhatsAppNumber(['phone_number_id' => 'PN-OV', 'display_phone' => '+62811000009', 'mode' => 'simulated']);
        $this->number->company_id = $this->company->id;
        $this->number->save();
    }

    private function makeDeal(string $stageKind, ?float $value, ?int $ownerId = null, ?string $lostReason = null, ?Carbon $closedAt = null): Deal
    {
        $contact = Contact::forceCreate([
            'company_id' => $this->company->id, 'name' => 'C'.uniqid(), 'source' => 'manual',
        ]);
        $pipeline = Pipeline::forceCreate(['company_id' => $this->company->id, 'name' => 'P'.uniqid(), 'is_default' => false]);
        $stage = Stage::forceCreate([
            'pipeline_id' => $pipeline->id, 'name' => ucfirst($stageKind), 'position' => 1,
            'kind' => $stageKind,
        ]);

        return Deal::forceCreate([
            'company_id' => $this->company->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'contact_id' => $contact->id,
            'owner_id' => $ownerId ?? $this->rep->id,
            'title' => 'Deal '.uniqid(),
            'value' => $value,
            'closed_at' => $closedAt ?? ($stageKind !== 'open' ? now() : null),
            'lost_reason' => $lostReason,
        ]);
    }

    public function test_win_rate_matches_direct_query(): void
    {
        $this->makeDeal('won', 1000);
        $this->makeDeal('won', 2000);
        $this->makeDeal('lost', 3000, lostReason: 'Price too high');
        // Open deal must not affect win rate.
        $this->makeDeal('open', 5000);
        // Last quarter's win must not count.
        $this->makeDeal('won', 9000, closedAt: now()->subMonths(4));

        $this->actingAs($this->rep)->get('/insights')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Overview')
                ->where('kpi.win_rate', 67)   // 2 won / (2 won + 1 lost)
                ->where('kpi.won_count', 2)
                ->where('kpi.lost_count', 1));
    }

    public function test_pipeline_value_only_counts_open_deals(): void
    {
        $this->makeDeal('open', 4000);
        $this->makeDeal('open', 6000);
        $this->makeDeal('won', 9000);

        $this->actingAs($this->rep)->get('/insights')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('kpi.pipeline_value', 10000));
    }

    public function test_lost_reasons_aggregate_last_90_days(): void
    {
        $this->makeDeal('lost', 1000, lostReason: 'Price too high');
        $this->makeDeal('lost', 2000, lostReason: 'Price too high');
        $this->makeDeal('lost', 3000, lostReason: 'No budget this quarter');

        $this->actingAs($this->rep)->get('/insights')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('lostReasons', 2)
                ->where('lostReasons.0.reason', 'Price too high')
                ->where('lostReasons.0.count', 2));
    }

    public function test_needs_attention_lists_unreplied_and_stale(): void
    {
        // Unreplied conversation (inbound 3h ago, no outbound after).
        $contact = Contact::forceCreate([
            'company_id' => $this->company->id, 'name' => 'Waiting Lead', 'phone_e164' => '+6281234590001', 'source' => 'whatsapp',
        ]);
        $conversation = new Conversation([
            'contact_id' => $contact->id, 'whatsapp_number_id' => $this->number->id,
            'status' => 'open', 'assignee_id' => $this->rep->id,
            'last_inbound_at' => now()->subHours(3), 'last_message_at' => now()->subHours(3),
        ]);
        $conversation->company_id = $this->company->id;
        $conversation->save();
        $conversation->messages()->create([
            'direction' => 'in', 'type' => 'text', 'body' => 'hello?', 'status' => 'delivered',
            'created_at' => now()->subHours(3),
        ]);

        // Stale deal: entered 30 days ago in a stage limited to 7 days.
        $deal = $this->makeDeal('open', 12000);
        $deal->update(['stage_entered_at' => now()->subDays(30)]);
        // position 1 has no stale limit; give the stage one.
        Stage::find($deal->stage_id)->update(['stale_after_days' => 7]);

        $this->actingAs($this->rep)->get('/insights')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('attention')
                ->where('attention.0.kind', 'unreplied')
                ->where('attention.0.name', 'Waiting Lead'));
    }

    public function test_rep_is_scoped_to_their_company_numbers(): void
    {
        // Foreign company deal must not leak.
        $other = $this->otherCompany();
        $contact = Contact::forceCreate(['company_id' => $other->id, 'name' => 'Foreign', 'source' => 'manual']);
        $pipeline = Pipeline::forceCreate(['company_id' => $other->id, 'name' => 'FP', 'is_default' => true]);
        $stage = Stage::forceCreate(['pipeline_id' => $pipeline->id, 'name' => 'New', 'position' => 1, 'kind' => 'open']);
        Deal::forceCreate([
            'company_id' => $other->id, 'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id,
            'contact_id' => $contact->id, 'title' => 'Foreign deal', 'value' => 999999,
        ]);

        $this->makeDeal('open', 500);

        $this->actingAs($this->rep)->get('/insights')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('kpi.pipeline_value', 500));
    }
}
