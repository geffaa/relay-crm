<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use App\Services\ContactService;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function __construct(
        private readonly ContactService $contacts,
        private readonly Phone $phone,
    ) {}

    public function index(Request $request): Response
    {
        $contacts = Contact::query()
            ->with(['owner:id,name', 'account:id,name'])
            ->search($request->string('q'))
            ->when($request->input('owner') === 'mine', fn ($q) => $q->where('owner_id', $request->user()->id))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts,
            'filters' => ['q' => $request->input('q'), 'owner' => $request->input('owner')],
        ]);
    }

    public function show(Request $request, Contact $contact): Response
    {
        $contact->load(['owner:id,name', 'account:id,name']);

        $activities = $contact->activities()
            ->with('actor:id,name')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $deals = $contact->deals()->with(['stage:id,name,kind', 'pipeline:id,name'])->get();
        $latestScore = $contact->latestScore();

        $possibleDuplicates = $this->duplicatesFor($contact);

        return Inertia::render('Contacts/Show', [
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'phone_e164' => $contact->phone_e164,
                'email' => $contact->email,
                'source' => $contact->source,
                'tags' => $contact->tags ?? [],
                'score' => $contact->score,
                'score_updated_at' => $contact->score_updated_at?->toISOString(),
                'owner' => $contact->owner?->only('id', 'name'),
                'account' => $contact->account?->only('id', 'name'),
                'created_at' => $contact->created_at->toISOString(),
            ],
            'deals' => $deals->map(fn ($d) => [
                'id' => $d->id, 'title' => $d->title, 'value' => $d->value,
                'currency' => $d->currency, 'stage' => $d->stage?->name,
                'stage_kind' => $d->stage?->kind, 'pipeline' => $d->pipeline?->name,
            ]),
            'scoreDetail' => $latestScore ? [
                'score' => $latestScore->score,
                'structured_part' => $latestScore->structured_part,
                'ai_adjustment' => $latestScore->ai_adjustment,
                'reasons' => $latestScore->topReasons(),
                'created_at' => $latestScore->created_at->toISOString(),
            ] : null,
            'activities' => $activities->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'body' => $a->body,
                'actor' => $a->actor?->name,
                'occurred_at' => $a->occurred_at->toISOString(),
            ]),
            'duplicates' => $possibleDuplicates,
            'users' => $this->usersForAssignment(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateContact($request);

        $contact = $this->contacts->findOrCreate(
            app('relay.company'),
            $data['name'],
            $data['phone'] ?? null,
            $data['email'] ?? null,
            'manual',
            $data['owner_id'] ? User::find($data['owner_id']) : $request->user(),
        );

        if (isset($data['account_id'])) {
            $contact->update(['account_id' => $data['account_id']]);
        }

        return redirect()->route('contacts.show', $contact);
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $data = $this->validateContact($request, $contact->id);

        $contact->update([
            'name' => $data['name'],
            'phone_e164' => $this->phone->toE164($data['phone'] ?? null),
            'email' => $data['email'] ?? null,
            'account_id' => $data['account_id'] ?? null,
            'owner_id' => $data['owner_id'] ?? $contact->owner_id,
            'tags' => $data['tags'] ?? $contact->tags,
        ]);

        return back();
    }

    public function addTask(Request $request, Contact $contact): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'due_at' => ['nullable', 'date']]);

        Task::create([
            'company_id' => $contact->company_id, 'assignee_id' => $request->user()->id,
            'subject_type' => Contact::class, 'subject_id' => $contact->id,
            'title' => $data['title'], 'kind' => 'manual', 'due_at' => $data['due_at'] ?? null,
        ]);
        Activity::record($contact->company_id, $contact, 'task', $request->user()->id, ['event' => 'task_created', 'title' => $data['title']]);

        return back();
    }

    public function addNote(Request $request, Contact $contact): RedirectResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        Activity::record($contact->company_id, $contact, 'note', $request->user()->id, ['text' => $validated['body']]);

        return back();
    }

    public function merge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'primary_id' => ['required', 'integer', Rule::exists('contacts', 'id')],
            'duplicate_id' => ['required', 'integer', Rule::exists('contacts', 'id'), 'different:primary_id'],
        ]);

        $primary = Contact::findOrFail($validated['primary_id']);
        $duplicate = Contact::findOrFail($validated['duplicate_id']);

        $this->contacts->merge($primary, $duplicate);

        return redirect()->route('contacts.show', $primary)
            ->with('success', "Contacts merged into {$primary->name}.");
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $contact->delete();

        return redirect()->route('contacts.index');
    }

    private function validateContact(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:190',
                Rule::unique('contacts', 'email')
                    ->where('company_id', app('relay.company')->id)
                    ->whereNull('deleted_at')
                    ->ignore($ignoreId)],
            'account_id' => ['nullable', Rule::exists('accounts', 'id')],
            'owner_id' => ['nullable', Rule::exists('users', 'id')],
            'tags' => ['nullable', 'array'],
        ]);

        if (! empty($data['phone']) && ! $this->phone->toE164($data['phone'])) {
            throw ValidationException::withMessages([
                'phone' => 'The phone number is not valid.',
            ]);
        }

        if (! empty($data['phone'])) {
            $e164 = $this->phone->toE164($data['phone']);
            $taken = Contact::forCompany(app('relay.company'))
                ->where('phone_e164', $e164)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'phone' => 'A contact with this phone number already exists.',
                ]);
            }
        }

        return $data;
    }

    /**
     * Duplicates by shared E.164 phone or email, for the merge suggestion.
     */
    private function duplicatesFor(Contact $contact): array
    {
        if (! $contact->phone_e164 && ! $contact->email) {
            return [];
        }

        return Contact::query()
            ->where('id', '!=', $contact->id)
            ->where(function ($q) use ($contact) {
                $q->when($contact->phone_e164, fn ($qq) => $qq->where('phone_e164', $contact->phone_e164))
                    ->when($contact->email, fn ($qq) => $qq->orWhere('email', $contact->email));
            })
            ->limit(5)
            ->get(['id', 'name', 'phone_e164', 'email'])
            ->toArray();
    }

    private function usersForAssignment(): array
    {
        return User::whereHas('companies', fn ($q) => $q->whereKey(app('relay.company')->id))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }
}
