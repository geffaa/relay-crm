<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Stage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_contacts_by_phone_and_name_across_entities(): void
    {
        $contact = Contact::forceCreate([
            'company_id' => $this->company->id,
            'name' => 'Dewi Anggraini',
            'phone_e164' => '+6281234567099',
            'source' => 'whatsapp',
        ]);

        $pipeline = Pipeline::forceCreate(['company_id' => $this->company->id, 'name' => 'P', 'is_default' => true]);
        $stage = Stage::forceCreate(['pipeline_id' => $pipeline->id, 'name' => 'New', 'position' => 1, 'kind' => 'open']);
        Deal::forceCreate([
            'company_id' => $this->company->id, 'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id, 'contact_id' => $contact->id, 'title' => 'Shopify migration',
        ]);

        // Foreign company must not leak.
        $other = $this->otherCompany();
        $fc = Contact::forceCreate(['company_id' => $other->id, 'name' => 'Dewi Foreign', 'source' => 'manual']);
        $fp = Pipeline::forceCreate(['company_id' => $other->id, 'name' => 'FP', 'is_default' => true]);
        $fs = Stage::forceCreate(['pipeline_id' => $fp->id, 'name' => 'New', 'position' => 1, 'kind' => 'open']);
        Deal::forceCreate([
            'company_id' => $other->id, 'pipeline_id' => $fp->id, 'stage_id' => $fs->id,
            'contact_id' => $fc->id, 'title' => 'Foreign Shopify',
        ]);

        $this->actingAs($this->rep)->getJson('/search?q=dewi')
            ->assertOk()
            ->assertJsonCount(1, 'contacts')
            ->assertJsonPath('contacts.0.title', 'Dewi Anggraini');

        $this->actingAs($this->rep)->getJson('/search?q=shopify')
            ->assertOk()
            ->assertJsonCount(1, 'deals')
            ->assertJsonPath('deals.0.title', 'Shopify migration');

        // Local phone format hits the E.164 contact.
        $this->actingAs($this->rep)->getJson('/search?q=0812-3456-7099')
            ->assertOk()
            ->assertJsonCount(1, 'contacts');

        // Short query returns empty.
        $this->actingAs($this->rep)->getJson('/search?q=d')
            ->assertOk()
            ->assertJsonCount(0, 'contacts');
    }
}
