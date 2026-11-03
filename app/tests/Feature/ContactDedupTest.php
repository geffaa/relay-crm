<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Stage;
use App\Services\ContactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactDedupTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_number_with_different_formats_maps_to_one_contact(): void
    {
        // "0812…" first, then "+62812…" must land on the same contact.
        $first = app(ContactService::class)->findOrCreate($this->company, 'Dewi', '081234567890');
        $second = app(ContactService::class)->findOrCreate($this->company, null, '+62 812 3456 7890');

        $this->assertSame($first->id, $second->id);
        $this->assertSame('+6281234567890', $first->phone_e164);
        $this->assertSame(1, Contact::count());
    }

    public function test_unknown_number_creates_contact_with_whatsapp_source(): void
    {
        $contact = app(ContactService::class)->findOrCreate($this->company, 'Rizky', '+628111222333');

        $this->assertSame('whatsapp', $contact->source);
        $this->assertSame('+628111222333', $contact->phone_e164);
    }

    public function test_contacts_are_scoped_to_their_company(): void
    {
        $other = $this->otherCompany();

        Contact::forceCreate([
            'company_id' => $other->id,
            'name' => 'Other Co Contact',
            'phone_e164' => '+628999888777',
            'source' => 'manual',
        ]);

        Contact::forceCreate([
            'company_id' => $this->company->id,
            'name' => 'My Co Contact',
            'phone_e164' => '+628777666555',
            'source' => 'manual',
        ]);

        // Global scope from the bound company only sees our own tenant.
        $this->assertSame(1, Contact::count());
        $this->assertSame('My Co Contact', Contact::first()->name);

        // Explicit company scoping still finds the other tenant's row.
        $this->assertSame(1, Contact::forCompany($other)->count());
    }

    public function test_find_or_create_matches_email_when_phone_missing(): void
    {
        $first = app(ContactService::class)->findOrCreate($this->company, 'Maya', null, 'Maya@Example.com', 'web');
        $second = app(ContactService::class)->findOrCreate($this->company, 'Maya Again', '+6282223334444', 'maya@example.com');

        $this->assertSame($first->id, $second->id);
    }

    public function test_rep_cannot_access_other_companys_contact(): void
    {
        $other = $this->otherCompany();

        $foreign = Contact::forceCreate([
            'company_id' => $other->id,
            'name' => 'Foreign Contact',
            'phone_e164' => '+628555444333',
            'source' => 'manual',
        ]);

        $this->actingAs($this->rep);

        // The route uses the global scope; a foreign id resolves 404.
        $this->get("/contacts/{$foreign->id}")->assertNotFound();
    }

    public function test_invalid_phone_is_rejected_on_create(): void
    {
        $this->actingAs($this->rep);

        $this->post('/contacts', [
            'name' => 'Bad Phone',
            'phone' => '12345',
        ])->assertSessionHasErrors('phone');
    }

    public function test_merge_moves_deals_and_records_activity(): void
    {
        $primary = Contact::forceCreate([
            'company_id' => $this->company->id,
            'name' => 'Keep Me',
            'phone_e164' => '+628111000111',
            'source' => 'manual',
        ]);

        $duplicate = Contact::forceCreate([
            'company_id' => $this->company->id,
            'name' => 'Drop Me',
            'email' => 'drop@example.com',
            'source' => 'referral',
            'score' => 88,
            'score_updated_at' => now(),
        ]);

        $pipeline = Pipeline::create([
            'company_id' => $this->company->id, 'name' => 'Sales', 'is_default' => true,
        ]);
        $stage = Stage::create([
            'pipeline_id' => $pipeline->id, 'name' => 'New', 'position' => 1, 'kind' => 'open',
        ]);

        $deal = Deal::create([
            'company_id' => $this->company->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'contact_id' => $duplicate->id,
            'title' => 'Merged deal',
            'currency' => 'USD',
        ]);

        $this->actingAs($this->manager);

        $merged = app(ContactService::class)->merge($primary, $duplicate);

        $this->assertTrue($merged->exists);
        $this->assertSame($primary->id, $deal->refresh()->contact_id);
        $this->assertSame(88, $primary->refresh()->score);
        $this->assertNull(Contact::find($duplicate->id));
        $this->assertDatabaseHas('activities', [
            'subject_id' => $primary->id,
            'subject_type' => Contact::class,
            'type' => 'contact_merged',
        ]);
    }
}
