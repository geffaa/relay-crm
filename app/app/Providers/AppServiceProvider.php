<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AI\AIClient;
use App\Services\AI\ClaudeClient;
use App\Services\AI\FakeAIClient;
use App\Services\AI\GroqClient;
use App\Services\AI\RedactingAIClient;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Demo/test mode: without an API key the deterministic fake client
        // answers with contract-compatible JSON, clearly labeled in the UI.
        $this->app->bind(AIClient::class, function () {
            $driver = config('services.ai.driver', 'auto');
            $driver = $driver === 'auto'
                ? (filled(config('services.groq.key')) ? 'groq' : (filled(config('services.anthropic.key')) ? 'anthropic' : 'fake'))
                : $driver;

            return new RedactingAIClient(match ($driver) {
                'groq' => new GroqClient,
                'anthropic' => new ClaudeClient,
                default => new FakeAIClient,
            });
        });
    }

    public function boot(): void
    {
        // Role gates: roles live on the company_user pivot. Gate::before
        // returning false denies, true allows, null defers to other rules.
        Gate::before(function (User $user, string $ability) {
            $role = $user->roleIn();

            return match ($ability) {
                'manage-settings' => $role === 'admin',
                'manage-contacts', 'manage-conversations', 'view-all' => in_array($role, ['admin', 'manager'], true),
                default => null,
            };
        });
    }
}
