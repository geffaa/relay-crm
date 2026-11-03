<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Task;
use App\Models\WhatsAppNumber;
use App\Models\WorkingHours;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboxTest extends TestCase
{
    use RefreshDatabase;

    private WhatsAppNumber $number;

    protected function setUp(): void
    {
        parent::setUp();

        $this->number = new WhatsAppNumber([
            'phone_number_id' => 'PN-TEST-1',
            'display_phone' => '+62811000002',
            'waba_id' => 'WABA-1',
            'mode' => 'simulated',
        ]);
        $this->number->company_id = $this->company->id;
        $this->number->save();
    }

    private function webhookPayload(string $wamid, string $fromE164NoPlus, string $text, ?string $profileName = 'Webhook Lead'): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA-1',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['phone_number_id' => 'PN-TEST-1'],
                        'contacts' => [['profile' => ['name' => $profileName], 'wa_id' => $fromE164NoPlus]],
                        'messages' => [[
                            'from' => $fromE164NoPlus,
                            'id' => $wamid,
                            'timestamp' => (string) now()->getTimestamp(),
                            'text' => ['body' => $text],
                            'type' => 'text',
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    public function test_webhook_with_bad_signature_is_rejected(): void
    {
        config(['services.whatsapp.app_secret' => 'topsecret']);

        $this->postJson('/webhooks/whatsapp', $this->webhookPayload('wamid_1', '6281234000001', 'hi'), [
            'X-Hub-Signature-256' => 'sha256=deadbeef',
        ])->assertStatus(401);

        $this->assertSame(0, Message::where('direction', 'out')->count());
    }

    public function test_webhook_processes_valid_payload_and_assigns_round_robin(): void
    {
        $payload = $this->webhookPayload('wamid_rr_1', '6281234000042', 'Hello, need a quote', 'Dewi');

        $this->postJson('/webhooks/whatsapp', $payload)->assertOk();

        // Queue is sync in tests: job already ran.
        $contact = Contact::where('phone_e164', '+6281234000042')->firstOrFail();
        $this->assertSame('Dewi', $contact->name);
        $this->assertSame('whatsapp', $contact->source);

        $conversation = Conversation::where('contact_id', $contact->id)->firstOrFail();
        $this->assertNotNull($conversation->assignee_id, 'New conversation must be assigned');
        $this->assertSame(1, $conversation->unread_count);
        $this->assertSame('Hello, need a quote', Message::where('conversation_id', $conversation->id)->first()->body);
    }

    public function test_duplicate_wamid_is_stored_once(): void
    {
        $payload = $this->webhookPayload('wamid_dupe', '6281234000043', 'once');

        $this->postJson('/webhooks/whatsapp', $payload)->assertOk();
        $this->postJson('/webhooks/whatsapp', $payload)->assertOk();

        $this->assertSame(1, Message::where('wamid', 'wamid_dupe')->count());
        $this->assertSame(1, Contact::where('phone_e164', '+6281234000043')->count());
    }

    public function test_webhook_verify_echoes_challenge(): void
    {
        config(['services.whatsapp.verify_token' => 'tok']);

        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=tok&hub_challenge=CHALLENGE123')
            ->assertOk()->assertSee('CHALLENGE123');

        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=X')
            ->assertStatus(403);
    }

    public function test_freeform_reply_locked_after_24h_window(): void
    {
        $conversation = $this->makeConversation(now()->subHours(30));

        $this->actingAs($this->rep)
            ->post("/inbox/{$conversation->id}/send", ['body' => 'free reply'])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, Message::where('direction', 'out')->count());
    }

    public function test_template_send_allowed_after_window(): void
    {
        $conversation = $this->makeConversation(now()->subHours(30));
        $template = MessageTemplate::create([
            'company_id' => $this->company->id,
            'whatsapp_number_id' => $this->number->id,
            'name' => 'followup_no_response',
            'language' => 'en_US',
            'category' => 'UTILITY',
            'body' => 'Hi {1}, following up!',
        ]);

        $this->actingAs($this->rep)
            ->post("/inbox/{$conversation->id}/send", [
                'body' => 'following up',
                'template_id' => $template->id,
            ])
            ->assertRedirect();

        $message = Message::where('direction', 'out')->firstOrFail();
        $this->assertSame('out', $message->direction);
        $this->assertSame('template', $message->type);
        $this->assertSame('sent', $message->status);
    }

    public function test_freeform_send_within_window(): void
    {
        $conversation = $this->makeConversation(now()->subHours(3));

        $this->actingAs($this->rep)
            ->post("/inbox/{$conversation->id}/send", ['body' => 'Hi! Sure, sending the quote now.'])
            ->assertRedirect();

        $message = Message::where('direction', 'out')->firstOrFail();
        $this->assertSame('text', $message->type);
        $this->assertSame('sent', $message->status);
        $this->assertNotNull($message->wamid);
        $this->assertTrue($conversation->fresh()->last_message_at->gt(now()->subMinute()));
    }

    public function test_rep_cannot_open_unassigned_conversation(): void
    {
        $conversation = $this->makeConversation(now()->subMinutes(10));
        // assignee is the manager's; rep has none.
        $conversation->update(['assignee_id' => $this->manager->id]);

        $this->actingAs($this->rep)->get("/inbox/{$conversation->id}")->assertNotFound();

        // Manager sees everything.
        $this->actingAs($this->manager)->get("/inbox/{$conversation->id}")->assertOk();
    }

    public function test_inbox_views_count_and_filter(): void
    {
        $replied = $this->makeConversation(now()->subMinutes(30));
        $replied->messages()->create(['direction' => 'out', 'type' => 'text', 'body' => 'on it', 'status' => 'sent']);
        $replied->update(['last_message_at' => now()]);

        $unreplied = $this->makeConversation(now()->subMinutes(45));

        $this->actingAs($this->rep)->get('/inbox?view=unreplied')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('conversations', 1)
                ->where('conversations.0.id', $unreplied->id));

        $this->actingAs($this->rep)->get('/inbox?view=all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('conversations', 2));
    }

    public function test_unreplied_task_created_after_working_hours(): void
    {
        // Freeze to Monday 14:00 so the working-hours walk is deterministic.
        Carbon::setTestNow(Carbon::parse('2026-11-02 14:00', 'Asia/Jakarta'));

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            WorkingHours::create([
                'company_id' => $this->company->id,
                'weekday' => $weekday,
                'start_time' => '09:00',
                'end_time' => '17:00',
            ]);
        }

        $conversation = $this->makeConversation(now()->subHours(8));
        $conversation->update(['assignee_id' => $this->rep->id]);

        $this->artisan('relay:detect-unreplied')->assertSuccessful();

        $this->assertDatabaseHas('tasks', [
            'kind' => 'unreplied',
            'subject_type' => Conversation::class,
            'subject_id' => $conversation->id,
            'assignee_id' => $this->rep->id,
        ]);

        // Second run does not duplicate.
        $this->artisan('relay:detect-unreplied');

        $this->assertSame(1, Task::where('subject_id', $conversation->id)->where('kind', 'unreplied')->count());
    }

    public function test_simulate_inbound_creates_conversation(): void
    {
        $this->actingAs($this->admin)
            ->post('/dev/simulate-inbound', [
                'from' => '+6281999000111',
                'name' => 'Simulated Sue',
                'text' => 'Hello from the simulation button!',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $contact = Contact::where('phone_e164', '+6281999000111')->firstOrFail();
        $this->assertSame('Simulated Sue', $contact->name);
        $this->assertSame(1, Message::where('body', 'Hello from the simulation button!')->count());
    }

    public function test_rep_cannot_use_simulate_endpoint(): void
    {
        $this->actingAs($this->rep)
            ->post('/dev/simulate-inbound', ['from' => '+6281999000222'])
            ->assertForbidden();
    }

    private function makeConversation(CarbonInterface $inboundAt): Conversation
    {
        $contact = Contact::forceCreate([
            'company_id' => $this->company->id,
            'name' => 'Chat Contact',
            'phone_e164' => '+6281234567'.random_int(100, 999),
            'source' => 'whatsapp',
            'owner_id' => $this->rep->id,
        ]);

        $conversation = new Conversation([
            'contact_id' => $contact->id,
            'whatsapp_number_id' => $this->number->id,
            'status' => 'open',
            'assignee_id' => $this->rep->id,
            'last_inbound_at' => $inboundAt,
            'last_message_at' => $inboundAt,
            'unread_count' => 1,
        ]);
        $conversation->company_id = $this->company->id;
        $conversation->save();

        $conversation->messages()->create([
            'direction' => 'in',
            'type' => 'text',
            'body' => 'I need help with my shop',
            'status' => 'delivered',
            'created_at' => $inboundAt,
        ]);

        return $conversation;
    }
}
