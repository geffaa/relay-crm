<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Services\ContactService;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function __construct(private readonly ContactService $contacts) {}

    public function index(Request $request): Response
    {
        $status = $request->input('status', 'open');

        $leads = Lead::query()
            ->with('owner:id,name')
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ['new', 'contacted', 'qualified']))
            ->when(in_array($status, ['new', 'contacted', 'qualified', 'converted', 'lost']), fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Leads/Index', [
            'leads' => $leads,
            'status' => $status,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:190'],
            'source' => ['nullable', 'in:whatsapp,web,referral,manual'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $phone = app(Phone::class)->toE164($data['phone'] ?? null);

        if (! empty($data['phone']) && $phone === null) {
            throw ValidationException::withMessages([
                'phone' => 'The phone number is not valid.',
            ]);
        }

        Lead::create([
            'name' => $data['name'],
            'phone_e164' => $phone,
            'email' => $data['email'] ?? null,
            'source' => $data['source'] ?? 'manual',
            'status' => 'new',
            'owner_id' => $request->user()->id,
            'notes' => $data['notes'] ?? null,
        ]);

        return back();
    }

    public function updateStatus(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:new,contacted,qualified,converted,lost'],
        ]);

        $lead->update(['status' => $data['status']]);

        return back();
    }

    /**
     * One-click conversion: lead -> contact (+ deal, optional account).
     * Reuses ContactService so an existing phone/email never duplicates.
     */
    public function convert(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'deal_title' => ['nullable', 'string', 'max:160'],
            'deal_value' => ['nullable', 'numeric', 'min:0'],
            'account_name' => ['nullable', 'string', 'max:120'],
        ]);

        $company = app('relay.company');

        $converted = DB::transaction(function () use ($lead, $data, $company, $request) {
            $contact = $this->contacts->findOrCreate(
                $company,
                $lead->name,
                $lead->phone_e164,
                $lead->email,
                $lead->source,
                $lead->owner,
            );

            $accountId = null;
            if (! empty($data['account_name'])) {
                $account = Account::create([
                    'name' => $data['account_name'],
                    'owner_id' => $lead->owner_id ?? $request->user()->id,
                ]);
                $account->company_id = $company->id;
                $account->save();
                $accountId = $account->id;
                $contact->update(['account_id' => $accountId]);
            }

            $pipeline = $company->defaultPipeline();
            $stage = $pipeline?->stages()->orderBy('position')->first();

            $deal = null;
            if ($pipeline && $stage) {
                $deal = new Deal([
                    'pipeline_id' => $pipeline->id,
                    'stage_id' => $stage->id,
                    'contact_id' => $contact->id,
                    'account_id' => $accountId ?? $contact->account_id,
                    'owner_id' => $lead->owner_id ?? $request->user()->id,
                    'title' => $data['deal_title'] ?? "New business — {$lead->name}",
                    'value' => $data['deal_value'] ?? null,
                    'currency' => $company->currency,
                    'stage_entered_at' => now(),
                ]);
                $deal->company_id = $company->id;
                $deal->save();
            }

            $lead->update([
                'status' => 'converted',
                'converted_contact_id' => $contact->id,
            ]);

            Activity::record($company->id, $contact, 'lead_conversion', $request->user()->id, [
                'lead_id' => $lead->id,
                'deal_id' => $deal?->id,
            ]);

            return [$contact, $deal];
        });

        [$contact, $deal] = $converted;

        return redirect()->route($deal ? 'deals.show' : 'contacts.show', $deal ?? $contact)
            ->with('success', "Lead converted — {$contact->name} is now a contact.");
    }
}
