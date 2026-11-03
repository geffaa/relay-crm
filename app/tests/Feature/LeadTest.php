<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\Stage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Default pipeline used by one-click conversion.
        $pipeline = Pipeline::forceCreate([
            'company_id' => $this->company->id, 'name' => 'Sales', 'is_default' => true,
        ]);
        Stage::forceCreate([
            'pipeline_id' => $pipeline->id, 'name' => 'New', 'position' => 1, 'kind' => 'open',
        ]);
    }

    public function test_lead_can_be_created_with_normalized_phone(): void
    {
        $this->actingAs($this->rep)
            ->post('/leads', [
                'name' => 'Rara Newbiz',
                'phone' => '0812-3456-7891',
                'email' => 'rara@newbiz.test',
                'source' => 'web',
                'notes' => 'Wants a quote for Shopify migration',
            ])
            ->assertRedirect();

        $lead = Lead::where('email', 'rara@newbiz.test')->firstOrFail();
        $this->assertSame('+6281234567891', $lead->phone_e164);
        $this->assertSame('new', $lead->status);
        $this->assertSame($this->rep->id, $lead->owner_id);
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $this->actingAs($this->rep)
            ->post('/leads', ['name' => 'Bad', 'phone' => '12'])
            ->assertSessionHasErrors('phone');
    }

    public function test_conversion_creates_contact_and_deal(): void
    {
        $lead = Lead::forceCreate([
            'company_id' => $this->company->id,
            'name' => 'Fresh Lead',
            'phone_e164' => '+6281299900001',
            'email' => 'fresh@lead.test',
            'source' => 'referral',
            'status' => 'qualified',
            'owner_id' => $this->rep->id,
        ]);

        $this->actingAs($this->rep)
            ->post("/leads/{$lead->id}/convert", [
                'deal_title' => 'Shopify migration',
                'deal_value' => 15000,
                'account_name' => 'NewBiz Co',
            ])
            ->assertRedirect();

        $lead->refresh();
        $this->assertSame('converted', $lead->status);
        $this->assertNotNull($lead->converted_contact_id);

        $contact = Contact::find($lead->converted_contact_id);
        $this->assertSame('Fresh Lead', $contact->name);
        $this->assertSame('NewBiz Co', $contact->account->name);

        $deal = Deal::where('contact_id', $contact->id)->firstOrFail();
        $this->assertSame('Shopify migration', $deal->title);
        $this->assertSame('15000.00', (string) $deal->value);
        $this->assertSame($this->rep->id, $deal->owner_id);

        $this->assertDatabaseHas('activities', [
            'subject_id' => $contact->id,
            'type' => 'lead_conversion',
        ]);
    }

    public function test_conversion_reuses_existing_contact_without_duplicates(): void
    {
        // Contact already exists with this number (from WhatsApp ingest).
        $existing = Contact::forceCreate([
            'company_id' => $this->company->id,
            'name' => 'Dewi Anggraini',
            'phone_e164' => '+6281299900002',
            'source' => 'whatsapp',
            'owner_id' => $this->rep->id,
        ]);

        $lead = Lead::forceCreate([
            'company_id' => $this->company->id,
            'name' => 'Dewi Anggraini',
            'phone_e164' => '+6281299900002', // same E.164
            'status' => 'qualified',
            'owner_id' => $this->rep->id,
        ]);

        $this->actingAs($this->rep)
            ->post("/leads/{$lead->id}/convert", ['deal_title' => 'Retainer'])
            ->assertRedirect();

        // No duplicate contact was created; the lead points at the existing one.
        $this->assertSame(1, Contact::where('phone_e164', '+6281299900002')->count());
        $lead->refresh();
        $this->assertSame($existing->id, $lead->converted_contact_id);

        // The deal attaches to the reused contact.
        $this->assertSame(1, Deal::where('contact_id', $existing->id)->count());
    }

    public function test_status_transitions_are_recorded(): void
    {
        $lead = Lead::forceCreate([
            'company_id' => $this->company->id, 'name' => 'S', 'status' => 'new', 'owner_id' => $this->rep->id,
        ]);

        $this->actingAs($this->rep)
            ->post("/leads/{$lead->id}/status", ['status' => 'qualified'])
            ->assertRedirect();

        $this->assertSame('qualified', $lead->fresh()->status);
    }
}
