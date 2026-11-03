<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Snooze;
use App\Models\Stage;
use App\Models\WhatsAppNumber;
use App\Notifications\ConversationAssigned;
use App\Services\AI\AIClient;
use App\Services\AI\ConversationSummarizer;
use App\Services\RoundRobinAssigner;
use App\Support\Money;
use App\Support\Redactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FixPassTest extends TestCase
{
    use RefreshDatabase;

    private WhatsAppNumber $number;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company->update(['currency' => 'IDR']);
        $this->number = new WhatsAppNumber(['phone_number_id' => 'PN-FIX', 'display_phone' => '+62811000077', 'mode' => 'simulated']);
        $this->number->company_id = $this->company->id;
        $this->number->save();
    }

    private function contact(string $name = 'Fix Contact'): Contact
    {
        return Contact::forceCreate(['company_id' => $this->company->id, 'name' => $name, 'phone_e164' => '+6281200'.random_int(10000, 99999), 'source' => 'whatsapp']);
    }

    private function conversation(Contact $c, array $extra = []): Conversation
    {
        $conv = new Conversation($extra + [
            'contact_id' => $c->id, 'whatsapp_number_id' => $this->number->id, 'status' => 'open',
            'assignee_id' => $this->rep->id, 'last_inbound_at' => now()->subHour(), 'last_message_at' => now()->subHour(),
        ]);
        $conv->company_id = $this->company->id;
        $conv->save();

        return $conv;
    }

    private function openDeal(float $value, string $currency, ?Account $account = null): Deal
    {
        $pipeline = Pipeline::forceCreate(['company_id' => $this->company->id, 'name' => 'P'.uniqid(), 'is_default' => false]);
        $stage = Stage::forceCreate(['pipeline_id' => $pipeline->id, 'name' => 'Open', 'position' => 1, 'kind' => 'open']);

        return Deal::forceCreate([
            'company_id' => $this->company->id, 'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id,
            'contact_id' => $this->contact()->id, 'account_id' => $account?->id, 'owner_id' => $this->rep->id,
            'title' => 'Deal '.uniqid(), 'value' => $value, 'currency' => $currency,
        ]);
    }

    public function test_redactor_masks_payment_numbers_but_keeps_ordinary_numbers(): void
    {
        $this->assertStringContainsString(Redactor::MASK, Redactor::mask('No rekening BCA 1234567890'));
        $this->assertStringNotContainsString('4111', Redactor::mask('card 4111 1111 1111 1111'));
        $this->assertSame('We have 340 products and 12 variants', Redactor::mask('We have 340 products and 12 variants'));
    }

    public function test_money_converts_through_idr(): void
    {
        $this->assertSame(16000.0, Money::convert(1, 'USD', 'IDR'));
        $this->assertSame(1.0, Money::convert(16000, 'IDR', 'USD'));
    }

    public function test_pipeline_value_converts_mixed_currencies_and_counts_unvalued_deals(): void
    {
        $this->openDeal(100_000_000, 'IDR');
        $this->openDeal(1_000, 'USD');   // 16,000,000 IDR
        $this->openDeal(0, 'IDR');

        $this->actingAs($this->manager)->get('/insights')->assertInertia(fn ($p) => $p
            ->where('kpi.pipeline_value', 116_000_000));
    }

    public function test_median_first_reply_uses_first_inbound_per_conversation(): void
    {
        foreach ([10, 30, 50] as $minutes) {
            $conv = $this->conversation($this->contact());
            $conv->messages()->create(['direction' => 'in', 'type' => 'text', 'body' => 'hi', 'status' => 'delivered', 'created_at' => now()->subHours(5)]);
            $conv->messages()->create(['direction' => 'in', 'type' => 'text', 'body' => 'anyone?', 'status' => 'delivered', 'created_at' => now()->subHours(5)->addMinutes(2)]);
            $conv->messages()->create(['direction' => 'out', 'type' => 'text', 'body' => 'hello', 'status' => 'sent', 'created_at' => now()->subHours(5)->addMinutes($minutes)]);
        }

        $this->actingAs($this->manager)->get('/insights')->assertInertia(fn ($p) => $p
            ->where('kpi.median_first_reply_minutes', 30));
    }

    public function test_reassigning_notifies_the_new_owner_only_for_company_members(): void
    {
        Notification::fake();
        $conv = $this->conversation($this->contact());
        $other = $this->makeUser('Rep Two', 'rep');

        $this->actingAs($this->manager)->post("/inbox/{$conv->id}/assign", ['user_id' => $other->id])->assertRedirect();
        Notification::assertSentTo($other, ConversationAssigned::class);

        $stranger = $this->makeUser('Stranger', 'rep');
        $stranger->companies()->detach();
        $this->actingAs($this->manager)->post("/inbox/{$conv->id}/assign", ['user_id' => $stranger->id])->assertNotFound();
    }

    public function test_failed_message_can_be_retried_once_the_channel_works(): void
    {
        $conv = $this->conversation($this->contact());
        $conv->messages()->create(['direction' => 'in', 'type' => 'text', 'body' => 'hi', 'status' => 'delivered', 'created_at' => now()->subMinutes(10)]);
        $failed = $conv->messages()->create(['direction' => 'out', 'type' => 'text', 'body' => 'Sorry for the wait', 'status' => 'failed', 'error' => ['message' => 'timeout'], 'sent_by' => $this->rep->id]);

        $this->actingAs($this->rep)->post("/inbox/{$conv->id}/messages/{$failed->id}/retry")->assertRedirect();

        $failed->refresh();
        $this->assertSame('sent', $failed->status);
        $this->assertNull($failed->error);
    }

    public function test_companies_list_converts_open_value_and_hides_nothing_from_managers(): void
    {
        $account = Account::forceCreate(['company_id' => $this->company->id, 'name' => 'Kopi Senja']);
        $this->openDeal(2_000, 'USD', $account);

        $this->actingAs($this->manager)->get('/companies')->assertInertia(fn ($p) => $p
            ->component('Companies/Index')
            ->where('accounts.0.open_value', 32_000_000)
            ->where('accounts.0.open_deals', 1));
        $this->actingAs($this->manager)->get("/companies/{$account->id}")->assertOk();
    }

    public function test_timeline_merges_contact_activity_and_pages_with_a_cursor(): void
    {
        $deal = $this->openDeal(1, 'IDR');
        foreach (range(1, 35) as $i) {
            Activity::record($this->company->id, $i % 2 ? $deal : $deal->contact, 'note', $this->rep->id, ['text' => "n{$i}"], now()->subMinutes($i));
        }

        $page = $this->actingAs($this->rep)->get("/deals/{$deal->id}")->assertOk()->viewData('page')['props'];
        $this->assertCount(30, $page['activities']);
        $this->assertNotNull($page['timeline_next']);

        $this->actingAs($this->rep)->getJson("/deals/{$deal->id}/timeline?before=".urlencode($page['timeline_next']))
            ->assertOk()->assertJsonCount(5, 'items')->assertJsonPath('next', null);
    }

    public function test_simulated_summary_quotes_the_real_conversation(): void
    {
        $conv = $this->conversation($this->contact('Lina'));
        $in = $conv->messages()->create(['direction' => 'in', 'type' => 'text', 'body' => 'Halo, mau bikin website pesan online untuk catering.', 'status' => 'delivered', 'created_at' => now()->subHours(3)]);
        $conv->messages()->create(['direction' => 'out', 'type' => 'text', 'body' => 'Bisa, Kak. Kami sarankan landing page dulu.', 'status' => 'read', 'created_at' => now()->subHours(2)]);
        $conv->messages()->create(['direction' => 'in', 'type' => 'text', 'body' => 'Kira-kira kisaran harganya?', 'status' => 'delivered', 'created_at' => now()->subHour()]);

        $summary = app(ConversationSummarizer::class)->summarize($conv, $this->company->id);

        $this->assertNotNull($summary);
        $this->assertStringContainsString('website pesan online', $summary->points[0]['text']);
        $this->assertSame([$in->id], $summary->points[0]['message_ids']);
        $this->assertStringNotContainsString('Shopify', json_encode($summary->points));
    }

    public function test_payment_numbers_never_reach_the_model(): void
    {
        config(['services.groq.key' => 'test-key', 'services.ai.driver' => 'groq']);
        Http::fake(['api.groq.com/*' => Http::response([
            'choices' => [['message' => ['content' => '{"draft":"Transfer ke rekening 1234567890 ya"}']]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        ])]);

        [$text] = app(AIClient::class)->complete('You are a reply drafter.', 'Customer: transfer ke rekening BCA 1234567890 ya');

        Http::assertSent(fn ($r) => ! str_contains(json_encode($r->data()), '1234567890') && $r->hasHeader('Authorization', 'Bearer test-key'));
        $this->assertStringNotContainsString('1234567890', $text);
    }

    public function test_stage_editor_is_admin_only_and_tenant_scoped(): void
    {
        $deal = $this->openDeal(1, 'IDR');
        $this->actingAs($this->rep)->patch("/settings/stages/{$deal->stage_id}", ['name' => 'X'])->assertForbidden();
        $this->actingAs($this->admin)->patch("/settings/stages/{$deal->stage_id}", ['name' => 'Discovery', 'stale_after_days' => 5])->assertRedirect();
        $this->assertSame('Discovery', Stage::find($deal->stage_id)->name);

        $this->actingAs($this->admin)->deleteJson("/settings/stages/{$deal->stage_id}")->assertStatus(422);

        app()->forgetInstance('relay.company');
        $foreign = Pipeline::forceCreate(['company_id' => $this->otherCompany()->id, 'name' => 'Theirs', 'is_default' => false]);
        $theirStage = Stage::forceCreate(['pipeline_id' => $foreign->id, 'name' => 'Theirs', 'position' => 1, 'kind' => 'open']);
        app()->instance('relay.company', $this->company);
        $this->actingAs($this->admin)->patch("/settings/stages/{$theirStage->id}", ['name' => 'Hacked'])->assertNotFound();
    }

    public function test_snoozed_chat_leaves_only_my_queue_and_reappears_later(): void
    {
        $conv = $this->conversation($this->contact('Snooze Me'));
        $conv->messages()->create(['direction' => 'in', 'type' => 'text', 'body' => 'hi', 'status' => 'delivered', 'created_at' => now()->subHour()]);

        $this->actingAs($this->rep)->get('/')->assertInertia(fn ($p) => $p->has('replies', 1));
        $this->actingAs($this->rep)->post('/snooze', ['type' => 'conversation', 'id' => $conv->id, 'hours' => 4])->assertRedirect();
        $this->actingAs($this->rep)->get('/')->assertInertia(fn ($p) => $p->has('replies', 0));
        $this->actingAs($this->manager)->get('/?scope=team')->assertInertia(fn ($p) => $p->has('replies', 1));

        Snooze::query()->update(['until' => now()->subMinute()]);
        $this->actingAs($this->rep)->get('/')->assertInertia(fn ($p) => $p->has('replies', 1));
    }

    public function test_nudge_call_and_owner_change_on_a_deal(): void
    {
        $deal = $this->openDeal(1000, 'IDR');
        $other = $this->makeUser('Rep Three', 'rep');

        $this->actingAs($this->rep)->post("/deals/{$deal->id}/nudge")->assertRedirect();
        $this->assertDatabaseHas('tasks', ['subject_id' => $deal->id, 'kind' => 'manual', 'assignee_id' => $this->rep->id]);

        $this->actingAs($this->rep)->post("/deals/{$deal->id}/calls", ['text' => 'Agreed scope', 'minutes' => 12])->assertRedirect();
        $this->assertTrue(Activity::where('type', 'call')->where('subject_id', $deal->id)->exists());

        $this->actingAs($this->rep)->post("/deals/{$deal->id}/owner", ['owner_id' => $other->id])->assertForbidden();
        $this->actingAs($this->manager)->post("/deals/{$deal->id}/owner", ['owner_id' => $other->id])->assertRedirect();
        $this->assertSame($other->id, $deal->fresh()->owner_id);
    }

    public function test_quota_feeds_the_target_bar_and_round_robin_can_be_switched_off(): void
    {
        $this->actingAs($this->admin)->post("/settings/users/{$this->rep->id}/quota", ['quota' => 300_000_000])->assertRedirect();
        $this->actingAs($this->manager)->get('/insights')->assertInertia(fn ($p) => $p->where('target.amount', 300_000_000)->where('reps.0.target', 300_000_000));

        $this->actingAs($this->manager)->post('/settings/company', ['round_robin' => false])->assertForbidden();
        $this->actingAs($this->admin)->post('/settings/company', ['round_robin' => false])->assertRedirect();
        $this->assertNull(app(RoundRobinAssigner::class)->nextRep($this->company->fresh()));
    }

    public function test_pipeline_close_date_and_lost_this_quarter_filters(): void
    {
        $late = $this->openDeal(1, 'IDR');
        $late->update(['expected_close' => now()->subDays(3)->toDateString()]);
        $later = $this->openDeal(1, 'IDR');
        $later->update(['expected_close' => now()->addDays(60)->toDateString()]);

        $this->actingAs($this->manager)->get('/pipeline?close=overdue&pipeline='.$late->pipeline_id)->assertInertia(fn ($p) => $p->where('stages.0.count', 1));
        $this->actingAs($this->manager)->get('/pipeline?lost=1&pipeline='.$late->pipeline_id)->assertInertia(fn ($p) => $p->where('stages.0.count', 0));
    }

    public function test_simulated_attachment_is_stored_and_validated(): void
    {
        Storage::fake('public');
        $conv = $this->conversation($this->contact('Photo'));
        $conv->messages()->create(['direction' => 'in', 'type' => 'text', 'body' => 'send photo', 'status' => 'delivered', 'created_at' => now()->subMinutes(5)]);

        $ok = UploadedFile::fake()->image('brief.png');
        $this->actingAs($this->rep)->post("/inbox/{$conv->id}/attach", ['file' => $ok])->assertRedirect();
        $this->assertSame('image', $conv->messages()->latest('id')->first()->type);

        $bad = UploadedFile::fake()->create('run.exe', 10);
        $this->actingAs($this->rep)->post("/inbox/{$conv->id}/attach", ['file' => $bad])->assertSessionHasErrors('file');
    }
}
