<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Stage;
use App\Models\WorkingHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_settings(): void
    {
        $this->actingAs($this->admin)->get('/settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings')
                ->has('users', 3));
    }

    public function test_rep_and_manager_cannot_see_settings(): void
    {
        $this->actingAs($this->rep)->get('/settings')->assertForbidden();
        $this->actingAs($this->manager)->get('/settings')->assertForbidden();
    }

    public function test_admin_can_change_roles(): void
    {
        $this->actingAs($this->admin)
            ->post("/settings/users/{$this->rep->id}/role", ['role' => 'manager'])
            ->assertRedirect();

        $this->assertSame('manager', $this->rep->roleIn($this->company));
    }

    public function test_inactive_rep_requires_reassignment(): void
    {
        // Rep owns an open deal -> deactivation is blocked.
        $contact = Contact::forceCreate([
            'company_id' => $this->company->id, 'name' => 'X', 'source' => 'manual',
        ]);
        $pipeline = Pipeline::forceCreate(['company_id' => $this->company->id, 'name' => 'P', 'is_default' => true]);
        $stage = Stage::forceCreate(['pipeline_id' => $pipeline->id, 'name' => 'New', 'position' => 1, 'kind' => 'open']);
        Deal::forceCreate([
            'company_id' => $this->company->id, 'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id, 'contact_id' => $contact->id,
            'owner_id' => $this->rep->id, 'title' => 'Deal',
        ]);

        $this->actingAs($this->admin)
            ->post("/settings/users/{$this->rep->id}/active", ['is_active' => false])
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($this->company->users()->whereKey($this->rep->id)->first()->pivot->is_active);
    }

    public function test_admin_can_update_working_hours(): void
    {
        WorkingHours::create([
            'company_id' => $this->company->id, 'weekday' => 1,
            'start_time' => '08:00', 'end_time' => '16:00',
        ]);

        $this->actingAs($this->admin)
            ->post('/settings/working-hours', ['days' => [
                ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '17:00'],
                ['weekday' => 2, 'start_time' => '09:00', 'end_time' => '17:00'],
            ]])
            ->assertRedirect();

        $this->assertSame(2, WorkingHours::where('company_id', $this->company->id)->count());
        $this->assertSame('09:00', WorkingHours::where('company_id', $this->company->id)->where('weekday', 1)->first()->start_time->format('H:i'));
    }
}
