<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Stage;
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
            return response()->json(['contacts' => [], 'deals' => [], 'leads' => [], 'companies' => []]);
        }

        $e164 = app(Phone::class)->toE164($q);

        $contacts = Contact::query()
            ->search($q)
            ->with('account:id,name')
            ->limit(5)
            ->get(['id', 'name', 'phone_e164', 'email', 'score'])
            ->map(fn ($c) => [
                'conversation_id' => Conversation::where('contact_id', $c->id)->latest('last_message_at')->value('id'),
                'id' => $c->id,
                'title' => $c->name,
                'subtitle' => $c->phone_e164 ?? $c->email ?? $c->account->name ?? null,
                'score' => $c->score,
                'href' => "/contacts/{$c->id}",
            ]);

        $deals = Deal::query()
            ->where(fn ($w) => $w->where('title', 'ilike', "%{$q}%")->orWhereHas('account', fn ($a) => $a->where('name', 'ilike', "%{$q}%")))
            ->with(['stage:id,name,kind,position,pipeline_id', 'account:id,name'])
            ->limit(5)
            ->get(['id', 'title', 'value', 'currency', 'stage_id', 'account_id'])
            ->map(fn ($d) => [
                'next_stage' => $d->stage->kind === 'open' ? Stage::where('pipeline_id', $d->stage->pipeline_id)->where('position', '>', $d->stage->position)->orderBy('position')->first(['id', 'name']) : null,
                'id' => $d->id,
                'title' => $d->title.($d->account_id ? ' · '.$d->account->name : ''),
                'deal_title' => $d->title,
                'subtitle' => ($d->stage->name ?? '—').' · '.($d->value ? $d->currency.' '.number_format((float) $d->value) : 'no value'),
                'score' => null,
                'href' => "/deals/{$d->id}",
            ]);

        $companies = Account::query()->where('name', 'ilike', "%{$q}%")->limit(4)->get(['id', 'name', 'industry'])
            ->map(fn ($a) => ['id' => $a->id, 'title' => $a->name, 'subtitle' => $a->industry, 'score' => null, 'href' => "/companies/{$a->id}"]);

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
            'companies' => $companies,
        ]);
    }
}
