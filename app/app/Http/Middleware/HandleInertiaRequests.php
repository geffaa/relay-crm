<?php

namespace App\Http\Middleware;

use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Task;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->roleIn(),
                ] : null,
                'company' => $request->user()?->currentCompany?->only('id', 'name', 'currency'),
            ],
            'notifications' => fn () => $request->user() ? [
                'unread' => $request->user()->unreadNotifications()->count(),
                'items' => $request->user()->notifications()->latest()->limit(8)->get()->map(fn ($n) => [
                    'id' => $n->id,
                    'title' => $n->data['title'] ?? '',
                    'read' => $n->read_at !== null,
                    'at' => $n->created_at?->toIso8601String(),
                ])->all(),
            ] : null,
            'badges' => fn () => $request->user() && app()->bound('relay.company') ? [
                'leads' => Lead::where('status', 'new')->count(),
                'inbox' => Conversation::view('unreplied')->count(),
                'today' => Task::query()->where('assignee_id', $request->user()->id)->whereNotIn('kind', ['unreplied', 'stale_deal'])->whereNull('done_at')->where('due_at', '<=', now()->endOfDay())->count(),
            ] : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
        ];
    }
}
