<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Stage;
use App\Models\Task;
use App\Services\DealService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    use RefreshDatabase;

    private Pipeline $pipeline;

    private Stage $newStage;

    private Stage $proposalStage;

    private Stage $lostStage;

    private Deal $deal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pipeline = Pipeline::forceCreate([
            'company_id' => $this->company->id, 'name' => 'Sales', 'is_default' => true,
        ]);
        $this->newStage = Stage::forceCreate([
            'pipeline_id' => $this->pipeline->id, 'name' => 'New', 'position' => 1,
            'kind' => 'open', 'stale_after_days' => 7,
        ]);
        $this->proposalStage = Stage::forceCreate([
            'pipeline_id' => $this->pipeline->id, 'name' => 'Proposal', 'position' => 2,
            'kind' => 'open', 'stale_after_days' => 7,
        ]);
        $this->lostStage = Stage::forceCreate([
            'pipeline_id' => $this->pipeline->id, 'name' => 'Lost', 'position' => 3, 'kind' => 'lost',
        ]);

        $contact = Contact::forceCreate([
            'company_id' => $this->company->id, 'name' => 'Deal Contact',
            'phone_e164' => '+6281234599999', 'source' => 'whatsapp', 'owner_id' => $this->rep->id,
        ]);

        $this->deal = Deal::forceCreate([
            'company_id' => $this->company->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->newStage->id,
            'contact_id' => $contact->id,
            'owner_id' => $this->rep->id,
            'title' => 'Website redesign retainer — Sukha Living',
            'value' => 24000,
            'currency' => 'USD',
            'stage_entered_at' => now()->subDays(11),
        ]);
    }

    public function test_stage_change_resets_clock_and_records_activity(): void
    {
        $service = app(DealService::class);
        $service->changeStage($this->deal, $this->proposalStage, null, $this->rep->id);

        $this->deal->refresh();

        $this->assertTrue($this->deal->stage_entered_at->gt(now()->subMinute()));
        $this->assertFalse($this->deal->isStale());

        $this->assertDatabaseHas('activities', [
            'subject_type' => Deal::class,
            'subject_id' => $this->deal->id,
            'type' => 'stage_change',
        ]);

        $body = Activity::latest('id')->first()->body;
        $this->assertSame('New', $body['from']);
        $this->assertSame('Proposal', $body['to']);
    }

    public function test_marking_lost_requires_a_reason(): void
    {
        $this->actingAs($this->rep)
            ->patch("/deals/{$this->deal->id}/stage", ['stage_id' => $this->lostStage->id])
            ->assertSessionHasErrors('lost_reason');

        $this->deal->refresh();
        $this->assertSame($this->newStage->id, $this->deal->stage_id);
    }

    public function test_marking_lost_with_reason_closes_the_deal(): void
    {
        $this->actingAs($this->rep)
            ->patch("/deals/{$this->deal->id}/stage", [
                'stage_id' => $this->lostStage->id,
                'lost_reason' => 'Price too high',
            ])
            ->assertRedirect();

        $this->deal->refresh();
        $this->assertSame($this->lostStage->id, $this->deal->stage_id);
        $this->assertSame('Price too high', $this->deal->lost_reason);
        $this->assertNotNull($this->deal->closed_at);
    }

    public function test_rep_cannot_move_another_reps_deal(): void
    {
        $otherRep = $this->makeUser('Other Rep', 'rep');
        $this->deal->update(['owner_id' => $otherRep->id]);

        $this->actingAs($this->rep)
            ->patch("/deals/{$this->deal->id}/stage", ['stage_id' => $this->proposalStage->id])
            ->assertForbidden();
    }

    public function test_manager_can_move_any_deal(): void
    {
        $this->actingAs($this->manager)
            ->patch("/deals/{$this->deal->id}/stage", ['stage_id' => $this->proposalStage->id])
            ->assertRedirect();
    }

    public function test_stale_deal_gets_a_task_for_its_owner(): void
    {
        // Deal is 11 days in "New" (limit 7) -> stale.
        $this->assertTrue($this->deal->fresh()->isStale());

        $this->artisan('relay:detect-stale-deals');
        $this->artisan('relay:detect-stale-deals'); // idempotent

        $tasks = Task::where('subject_type', Deal::class)
            ->where('subject_id', $this->deal->id)
            ->where('kind', 'stale_deal')
            ->get();

        $this->assertCount(1, $tasks);
        $this->assertSame($this->rep->id, $tasks[0]->assignee_id);
    }

    public function test_deal_board_is_scoped_to_company(): void
    {
        $other = $this->otherCompany();

        $foreignContact = Contact::forceCreate([
            'company_id' => $other->id, 'name' => 'Foreign', 'source' => 'manual',
        ]);
        $foreignPipeline = Pipeline::forceCreate(['company_id' => $other->id, 'name' => 'Foreign', 'is_default' => true]);
        $foreignStage = Stage::forceCreate(['pipeline_id' => $foreignPipeline->id, 'name' => 'New', 'position' => 1, 'kind' => 'open']);
        Deal::forceCreate([
            'company_id' => $other->id, 'pipeline_id' => $foreignPipeline->id,
            'stage_id' => $foreignStage->id, 'contact_id' => $foreignContact->id, 'title' => 'Foreign deal',
        ]);

        $this->actingAs($this->rep)->get('/pipeline')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Pipeline')
                ->where('pipeline.id', $this->pipeline->id)
                // No foreign deals leak into any column.
                ->where('stages.0.deals.0.id', $this->deal->id));
    }
}
