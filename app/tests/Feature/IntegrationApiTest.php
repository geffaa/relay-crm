<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Stage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        [, $this->token] = ApiKey::issue($this->company, 'Simpul');
        $pipeline = Pipeline::forceCreate(['company_id' => $this->company->id, 'name' => 'Sales', 'is_default' => true]);
        Stage::forceCreate(['pipeline_id' => $pipeline->id, 'name' => 'New', 'position' => 1, 'kind' => 'open']);
        Stage::forceCreate(['pipeline_id' => $pipeline->id, 'name' => 'Won', 'position' => 2, 'kind' => 'won']);
    }

    private function api(string $method, string $uri, array $data = [], ?string $token = null)
    {
        return $this->withHeader('Authorization', 'Bearer '.($token ?? $this->token))->json($method, '/api/v1'.$uri, $data);
    }

    public function test_requires_a_valid_key(): void
    {
        $this->json('POST', '/api/v1/contacts/upsert', ['phone' => '081234567890'])->assertStatus(401);
        $this->api('POST', '/contacts/upsert', ['phone' => '081234567890'], 'rly_wrong')->assertStatus(401);
    }

    public function test_upsert_matches_phone_formats_to_one_contact(): void
    {
        $first = $this->api('POST', '/contacts/upsert', ['name' => 'Rina', 'phone' => '0812-3456-7890', 'tags' => ['paid']]);
        $first->assertStatus(201)->assertJsonPath('created', true);

        $second = $this->api('POST', '/contacts/upsert', ['phone' => '+62 812 3456 7890', 'tags' => ['repeat']]);
        $second->assertStatus(200)->assertJsonPath('created', false)->assertJsonPath('matched_by', 'phone');

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, Contact::count());
        $this->assertEqualsCanonicalizing(['paid', 'repeat'], $second->json('tags'));
    }

    public function test_upsert_needs_phone_or_email(): void
    {
        $this->api('POST', '/contacts/upsert', ['name' => 'No contact info'])->assertStatus(422);
    }

    public function test_activity_is_idempotent_per_external_ref(): void
    {
        $id = $this->api('POST', '/contacts/upsert', ['name' => 'Rina', 'email' => 'rina@example.com'])->json('id');

        $a = $this->api('POST', "/contacts/{$id}/activities", ['text' => 'Order SP-1001 paid', 'external_ref' => 'evt_1']);
        $b = $this->api('POST', "/contacts/{$id}/activities", ['text' => 'Order SP-1001 paid', 'external_ref' => 'evt_1']);

        $a->assertStatus(201)->assertJsonPath('duplicate', false);
        $b->assertStatus(200)->assertJsonPath('duplicate', true);
        $this->assertSame($a->json('id'), $b->json('id'));
    }

    public function test_task_and_deal_are_created_and_deal_is_idempotent(): void
    {
        $id = $this->api('POST', '/contacts/upsert', ['name' => 'Rina', 'email' => 'rina@example.com'])->json('id');

        $this->api('POST', "/contacts/{$id}/tasks", ['title' => 'Call about order'])->assertStatus(201);

        $d1 = $this->api('POST', '/deals', ['contact_id' => $id, 'title' => 'Order SP-1001', 'value' => 2400000, 'currency' => 'IDR', 'stage' => 'won']);
        $d2 = $this->api('POST', '/deals', ['contact_id' => $id, 'title' => 'Order SP-1001', 'value' => 2400000, 'currency' => 'IDR']);

        $d1->assertStatus(201)->assertJsonPath('stage', 'Won');
        $d2->assertJsonPath('duplicate', true);
        $this->assertSame(1, Deal::count());
    }

    public function test_a_key_cannot_touch_another_company(): void
    {
        $other = $this->otherCompany();
        [, $otherToken] = ApiKey::issue($other, 'Other');
        $id = $this->api('POST', '/contacts/upsert', ['name' => 'Rina', 'email' => 'rina@example.com'])->json('id');

        $this->api('POST', "/contacts/{$id}/activities", ['text' => 'x'], $otherToken)->assertStatus(404);
    }
}
