<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Support\Phone;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Global search powering the ⌘K command palette.
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['contacts' => [], 'deals' => [], 'leads' => []]);
        }

        $e164 = app(Phone::class)->toE164($q);

        $contacts = Contact::query()
            ->search($q)
            ->with('account:id,name')
            ->limit(5)
            ->get(['id', 'name', 'phone_e164', 'email', 'score'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->name,
                'subtitle' => $c->phone_e164 ?? $c->email ?? $c->account->name ?? null,
                'score' => $c->score,
                'href' => "/contacts/{$c->id}",
            ]);

        $deals = Deal::query()
            ->where('title', 'ilike', "%{$q}%")
            ->with('stage:id,name,kind')
            ->limit(5)
            ->get(['id', 'title', 'value', 'currency'])
            ->map(fn ($d) => [
                'id' => $d->id,
                'title' => $d->title,
                'subtitle' => ($d->stage->name ?? '—').' · '.($d->value ? $d->currency.' '.number_format((float) $d->value) : 'no value'),
                'score' => null,
                'href' => "/deals/{$d->id}",
            ]);

        $leads = Lead::query()
            ->where(fn ($query) => $query
                ->where('name', 'ilike', "%{$q}%")
                ->orWhere('email', 'ilike', "%{$q}%")
                ->when($e164, fn ($qq) => $qq->orWhere('phone_e164', $e164)))
            ->limit(5)
            ->get(['id', 'name', 'phone_e164', 'status'])
            ->map(fn ($l) => [
                'id' => $l->id,
                'title' => $l->name,
                'subtitle' => 'Lead · '.($l->phone_e164 ?? $l->status),
                'score' => null,
                'href' => '/leads',
            ]);

        return response()->json([
            'contacts' => $contacts,
            'deals' => $deals,
            'leads' => $leads,
        ]);
    }
}
