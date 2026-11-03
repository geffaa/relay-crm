<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Deal;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Companies (accounts): who we sell to, with their people and deals in one place. */
class CompanyAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $cur = app('relay.company')->currency;
        $q = trim((string) $request->input('q'));

        $accounts = Account::query()
            ->with('owner:id,name')
            ->withCount('contacts')
            ->when($q !== '', fn ($w) => $w->where('name', 'ilike', "%{$q}%"))
            ->orderBy('name')
            ->get();

        $deals = Deal::query()->whereIn('account_id', $accounts->pluck('id'))
            ->whereHas('stage', fn ($s) => $s->where('kind', 'open'))
            ->get(['account_id', 'value', 'currency'])->groupBy('account_id');

        return Inertia::render('Companies/Index', [
            'accounts' => $accounts->map(fn (Account $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'industry' => $a->industry,
                'domain' => $a->domain,
                'health' => $a->health,
                'owner' => $a->owner?->name,
                'contacts' => $a->contacts_count,
                'open_deals' => ($deals[$a->id] ?? collect())->count(),
                'open_value' => (float) ($deals[$a->id] ?? collect())->sum(fn (Deal $d) => Money::convert((float) $d->value, $d->currency ?? $cur, $cur)),
            ])->values(),
            'currency' => $cur,
            'filters' => ['q' => $q],
        ]);
    }

    public function show(Request $request, Account $account): Response
    {
        $cur = app('relay.company')->currency;
        $mine = fn ($q) => $request->user()->isManager() ? $q : $q->where('owner_id', $request->user()->id);

        return Inertia::render('Companies/Show', [
            'account' => $account->only('id', 'name', 'domain', 'industry', 'size_band', 'health') + ['owner' => $account->owner?->name],
            'contacts' => $account->contacts()->orderBy('name')->get(['id', 'name', 'phone_e164', 'email', 'score']),
            'deals' => $mine(Deal::query()->where('account_id', $account->id))
                ->with(['stage:id,name,kind', 'owner:id,name'])->orderByDesc('updated_at')->get()
                ->map(fn (Deal $d) => [
                    'id' => $d->id, 'title' => $d->title, 'value' => $d->value, 'currency' => $d->currency,
                    'stage' => $d->stage->name, 'kind' => $d->stage->kind, 'owner' => $d->owner?->name,
                ]),
            'currency' => $cur,
        ]);
    }
}
