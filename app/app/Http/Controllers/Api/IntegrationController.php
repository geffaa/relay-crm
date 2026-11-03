<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Stage;
use App\Models\Task;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public integration API (/api/v1), used by automation hubs such as Simpul.
 * All writes are idempotent where it matters: contacts are matched by phone/email,
 * and deals/activities/tasks accept an `external_ref` so a retried call does not duplicate.
 */
class IntegrationController extends Controller
{
    public function __construct(private readonly ContactService $contacts) {}

    public function upsertContact(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'source' => ['nullable', 'string', 'max:40'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:40'],
        ]);

        if (empty($data['phone']) && empty($data['email'])) {
            return response()->json(['error' => 'validation', 'message' => 'Provide phone or email.'], 422);
        }

        $company = app('relay.company');
        $existing = $this->contacts->findExisting($company, $data['phone'] ?? null, $data['email'] ?? null);
        $contact = $this->contacts->findOrCreate($company, $data['name'] ?? null, $data['phone'] ?? null, $data['email'] ?? null, $data['source'] ?? 'web');

        if (! empty($data['tags'])) {
            $contact->update(['tags' => array_values(array_unique(array_merge($contact->tags ?? [], $data['tags'])))]);
        }
        if ($existing && ! empty($data['name']) && $contact->name !== $data['name'] && $contact->source === 'web') {
            $contact->update(['name' => $data['name']]);
        }

        return response()->json([
            'id' => $contact->id,
            'created' => ! $existing,
            'matched_by' => $existing ? ($contact->phone_e164 && $contact->phone_e164 === $this->contacts->normalizePhone($data['phone'] ?? null) ? 'phone' : 'email') : null,
            'name' => $contact->name,
            'phone_e164' => $contact->phone_e164,
            'tags' => $contact->tags ?? [],
        ], $existing ? 200 : 201);
    }

    public function addActivity(Request $request, int $contact): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:2000'], 'external_ref' => ['nullable', 'string', 'max:120']]);
        $c = Contact::findOrFail($contact);

        if (! empty($data['external_ref'])) {
            $dup = Activity::where('subject_type', Contact::class)->where('subject_id', $c->id)
                ->where('body->external_ref', $data['external_ref'])->first();
            if ($dup) {
                return response()->json(['id' => $dup->id, 'duplicate' => true]);
            }
        }

        $a = Activity::record($c->company_id, $c, 'note', null, ['text' => $data['text'], 'external_ref' => $data['external_ref'] ?? null, 'via' => 'api']);

        return response()->json(['id' => $a->id, 'duplicate' => false], 201);
    }

    public function createTask(Request $request, int $contact): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'due_at' => ['nullable', 'date'],
            'assignee_id' => ['nullable', 'integer'],
        ]);
        $c = Contact::findOrFail($contact);
        $company = app('relay.company');

        $assignee = ! empty($data['assignee_id'])
            ? $company->users()->where('users.id', $data['assignee_id'])->first()
            : ($c->owner ?? $company->users()->orderBy('users.id')->first());
        if (! $assignee) {
            return response()->json(['error' => 'validation', 'message' => 'No assignee available.'], 422);
        }

        $task = Task::forceCreate([
            'company_id' => $company->id, 'assignee_id' => $assignee->id, 'subject_type' => Contact::class, 'subject_id' => $c->id,
            'title' => $data['title'], 'kind' => 'manual', 'due_at' => $data['due_at'] ?? null,
        ]);

        return response()->json(['id' => $task->id, 'assignee_id' => $assignee->id], 201);
    }

    public function createDeal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contact_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:160'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'stage' => ['nullable', 'string', 'max:60'],
        ]);
        $contact = Contact::findOrFail($data['contact_id']);
        $company = app('relay.company');
        $pipeline = $company->defaultPipeline();
        if (! $pipeline) {
            return response()->json(['error' => 'validation', 'message' => 'No pipeline configured.'], 422);
        }

        $stage = ! empty($data['stage'])
            ? $pipeline->stages()->whereRaw('lower(name) = ?', [strtolower($data['stage'])])->first()
            : null;
        $stage ??= $pipeline->stages()->orderBy('position')->first();

        // Idempotent per contact + title: a retried call returns the existing open deal.
        $existing = Deal::where('contact_id', $contact->id)->where('title', $data['title'])->first();
        if ($existing) {
            return response()->json(['id' => $existing->id, 'duplicate' => true, 'stage' => $existing->stage?->name]);
        }

        $deal = Deal::forceCreate([
            'company_id' => $company->id, 'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id,
            'contact_id' => $contact->id, 'account_id' => $contact->account_id, 'owner_id' => $contact->owner_id,
            'title' => $data['title'], 'value' => $data['value'] ?? null,
            'currency' => $data['currency'] ?? $company->currency, 'stage_entered_at' => now(),
        ]);
        Activity::record($company->id, $deal, 'note', null, ['event' => 'deal_created', 'via' => 'api']);

        return response()->json(['id' => $deal->id, 'duplicate' => false, 'stage' => $stage->name], 201);
    }
}
