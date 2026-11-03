<?php

namespace App\Http\Controllers;

use App\Models\AiRun;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\MessageTemplate;
use App\Models\Pipeline;
use App\Models\User;
use App\Models\WhatsAppNumber;
use App\Models\WorkingHours;
use App\Services\AI\AIClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * S10 — settings. Admin only.
     */
    public function index(Request $request): Response
    {
        $company = app('relay.company');

        $memberships = DB::table('company_user')
            ->where('company_id', $company->id)
            ->get()
            ->keyBy('user_id');

        $users = User::whereHas('companies', fn ($q) => $q->whereKey($company->id))
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->roleIn($company),
                'is_active' => (bool) $memberships[$u->id]->is_active,
                'quota' => $memberships[$u->id]->quarterly_quota !== null ? (float) $memberships[$u->id]->quarterly_quota : null,
            ]);

        return Inertia::render('Settings', [
            'company' => $company->only('id', 'name', 'currency', 'timezone', 'round_robin'),
            'users' => $users,
            'pipelines' => Pipeline::with(['stages' => fn ($q) => $q->orderBy('position')->withCount('deals')])->orderBy('name')->get()->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->name,
                'stages' => $p->stages->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'kind' => $s->kind, 'stale_after_days' => $s->stale_after_days, 'deals' => $s->deals_count]),
            ]),
            'templates' => MessageTemplate::orderBy('name')->get(['id', 'name', 'language', 'category', 'body', 'status']),
            'ai' => ['model' => app(AIClient::class)->name(), 'live' => app(AIClient::class)->name() !== 'simulated-demo'],
            'whatsappNumbers' => WhatsAppNumber::get()->map(fn ($n) => [
                'id' => $n->id,
                'display_phone' => $n->display_phone,
                'mode' => $n->mode,
                'phone_number_id' => $n->phone_number_id,
            ]),
            'workingHours' => $company->workingHours()->orderBy('weekday')->get(['weekday', 'start_time', 'end_time']),
            'aiRuns' => AiRun::orderByDesc('created_at')->limit(15)->get([
                'kind', 'model', 'input_tokens', 'output_tokens', 'status', 'created_at',
            ])->map(fn ($r) => [...$r->toArray(), 'created_at' => $r->created_at->toISOString()]),
        ]);
    }

    public function updateCompany(Request $request): RedirectResponse
    {
        $data = $request->validate(['round_robin' => ['required', 'boolean']]);
        app('relay.company')->update($data);

        return back()->with('success', 'Assignment setting saved.');
    }

    public function updateQuota(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['quota' => ['nullable', 'numeric', 'min:0', 'max:100000000000']]);

        DB::table('company_user')->where('company_id', app('relay.company')->id)->where('user_id', $user->id)
            ->update(['quarterly_quota' => $data['quota'] ?? null]);

        return back()->with('success', 'Target saved.');
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', 'in:admin,manager,rep']]);

        $company = app('relay.company');
        $company->users()->updateExistingPivot($user->id, ['role' => $data['role']]);

        return back()->with('success', "Role for {$user->name} updated.");
    }

    public function updateUserActive(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        $company = app('relay.company');

        if (! $data['is_active']) {
            // Edge case from F1: deactivate only when nothing is assigned.
            $hasDeals = Deal::where('owner_id', $user->id)
                ->whereHas('stage', fn ($q) => $q->where('kind', 'open'))->exists();
            $hasChats = Conversation::where('assignee_id', $user->id)->where('status', '!=', 'closed')->exists();

            if ($hasDeals || $hasChats) {
                return back()->withErrors([
                    'is_active' => "Reassign {$user->name}'s open deals and conversations first.",
                ]);
            }
        }

        $company->users()->updateExistingPivot($user->id, ['is_active' => $data['is_active']]);

        return back();
    }

    public function updateWorkingHours(Request $request): RedirectResponse
    {
        $company = app('relay.company');

        $data = $request->validate([
            'days' => ['required', 'array'],
            'days.*.weekday' => ['required', 'integer', 'between:0,6'],
            'days.*.start_time' => ['required', 'date_format:H:i'],
            'days.*.end_time' => ['required', 'date_format:H:i', 'after:days.*.start_time'],
        ]);

        WorkingHours::where('company_id', $company->id)->delete();

        foreach ($data['days'] as $day) {
            WorkingHours::create([
                'company_id' => $company->id,
                'weekday' => $day['weekday'],
                'start_time' => $day['start_time'],
                'end_time' => $day['end_time'],
            ]);
        }

        return back()->with('success', 'Working hours updated.');
    }
}
