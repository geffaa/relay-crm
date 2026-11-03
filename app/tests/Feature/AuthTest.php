<?php

namespace Tests\Feature;

use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_for_guests(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn ($page) => $page->component('Auth/Login'));
    }

    public function test_user_can_log_in_and_sees_today(): void
    {
        $this->post('/login', [
            'email' => $this->rep->email,
            'password' => 'password',
        ])->assertRedirect(route('today'));

        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Today')
            ->where('auth.user.email', $this->rep->email)
            ->where('auth.user.role', 'rep')
            ->where('auth.company.name', $this->company->name));
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->from('/login')->post('/login', [
            'email' => $this->rep->email,
            'password' => 'wrong',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');
    }

    public function test_contacts_index_shows_scoped_contacts(): void
    {
        Contact::forceCreate([
            'company_id' => $this->company->id,
            'name' => 'Citra Lead',
            'phone_e164' => '+6281234500001',
            'source' => 'whatsapp',
            'owner_id' => $this->rep->id,
        ]);

        Contact::forceCreate([
            'company_id' => $this->otherCompany()->id,
            'name' => 'Foreign Lead',
            'phone_e164' => '+6281234500002',
            'source' => 'whatsapp',
        ]);

        $this->actingAs($this->rep)->get('/contacts')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Contacts/Index')
                ->has('contacts.data', 1)
                ->where('contacts.data.0.name', 'Citra Lead'));
    }

    public function test_rep_cannot_merge_contacts(): void
    {
        $a = Contact::forceCreate(['company_id' => $this->company->id, 'name' => 'A', 'source' => 'manual']);
        $b = Contact::forceCreate(['company_id' => $this->company->id, 'name' => 'B', 'source' => 'manual']);

        $this->actingAs($this->rep)
            ->post('/contacts/merge', ['primary_id' => $a->id, 'duplicate_id' => $b->id])
            ->assertForbidden();
    }

    public function test_manager_can_merge_contacts(): void
    {
        $a = Contact::forceCreate(['company_id' => $this->company->id, 'name' => 'A', 'source' => 'manual']);
        $b = Contact::forceCreate(['company_id' => $this->company->id, 'name' => 'B', 'source' => 'manual']);

        $this->actingAs($this->manager)
            ->post('/contacts/merge', ['primary_id' => $a->id, 'duplicate_id' => $b->id])
            ->assertRedirect();

        $this->assertNull(Contact::find($b->id));
    }
}
