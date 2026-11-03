<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CompanyAccountController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactImportController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\Dev\SimulateInboundController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SettingsPipelineController;
use App\Http\Controllers\TodayController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'company'])->group(function () {
    Route::get('/', [TodayController::class, 'index'])->name('today');
    Route::post('/tasks/{task}/done', [TodayController::class, 'done'])->name('tasks.done');

    // Contacts (F2)
    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
    Route::get('/contacts/{contact}', [ContactController::class, 'show'])->whereNumber('contact')->name('contacts.show');
    Route::patch('/contacts/{contact}', [ContactController::class, 'update'])->whereNumber('contact')->name('contacts.update');
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])
        ->whereNumber('contact')->name('contacts.destroy');
    Route::post('/contacts/merge', [ContactController::class, 'merge'])->name('contacts.merge');
    Route::post('/contacts/{contact}/notes', [ContactController::class, 'addNote'])
        ->whereNumber('contact')->name('contacts.notes');

    Route::post('/contacts/{contact}/tasks', [ContactController::class, 'addTask'])->whereNumber('contact')->name('contacts.tasks');

    // CSV import (F2, FEATURE-MAP)
    Route::get('/contacts/import', [ContactImportController::class, 'show'])->name('contacts.import');
    Route::post('/contacts/import', [ContactImportController::class, 'preview'])->name('contacts.import.preview');
    Route::post('/contacts/import/confirm', [ContactImportController::class, 'confirm'])->name('contacts.import.confirm');

    // Companies
    Route::get('/companies', [CompanyAccountController::class, 'index'])->name('companies.index');
    Route::get('/companies/{account}', [CompanyAccountController::class, 'show'])->whereNumber('account')->name('companies.show');

    // Pipeline & deals (F3, F4)
    Route::get('/pipeline', [DealController::class, 'pipeline'])->name('pipeline');
    Route::post('/deals', [DealController::class, 'store'])->name('deals.store');
    Route::get('/deals/{deal}', [DealController::class, 'show'])->whereNumber('deal')->name('deals.show');
    Route::patch('/deals/{deal}/stage', [DealController::class, 'changeStage'])->whereNumber('deal')->name('deals.stage');
    Route::get('/deals/{deal}/timeline', [DealController::class, 'timelinePage'])->whereNumber('deal')->name('deals.timeline');
    Route::post('/deals/{deal}/notes', [DealController::class, 'addNote'])->whereNumber('deal')->name('deals.notes');
    Route::post('/deals/{deal}/calls', [DealController::class, 'logCall'])->whereNumber('deal')->name('deals.calls');
    Route::post('/deals/{deal}/owner', [DealController::class, 'changeOwner'])->whereNumber('deal')->name('deals.owner');
    Route::post('/deals/{deal}/nudge', [DealController::class, 'nudge'])->whereNumber('deal')->name('deals.nudge');
    Route::post('/snooze', [TodayController::class, 'snooze'])->name('snooze');
    Route::post('/deals/{deal}/tasks', [DealController::class, 'addTask'])->whereNumber('deal')->name('deals.tasks');

    // Inbox WhatsApp (F5)
    Route::get('/inbox', [ConversationController::class, 'index'])->name('inbox.index');
    Route::get('/inbox/{conversation}', [ConversationController::class, 'show'])->whereNumber('conversation')->name('inbox.show');
    Route::post('/inbox/{conversation}/send', [ConversationController::class, 'send'])->whereNumber('conversation')->name('inbox.send');
    Route::post('/inbox/{conversation}/messages/{message}/retry', [ConversationController::class, 'retry'])->whereNumber(['conversation', 'message'])->name('inbox.retry');
    Route::post('/inbox/{conversation}/attach', [ConversationController::class, 'attach'])->whereNumber('conversation')->name('inbox.attach');
    Route::post('/inbox/{conversation}/assign', [ConversationController::class, 'assign'])->whereNumber('conversation')->name('inbox.assign');
    Route::post('/inbox/{conversation}/close', [ConversationController::class, 'close'])->whereNumber('conversation')->name('inbox.close');

    // AI (F6, F7): usulan saja — tidak pernah mengirim otomatis
    Route::post('/inbox/{conversation}/summarize', [AiController::class, 'summarize'])->whereNumber('conversation')->name('ai.summarize');
    Route::post('/inbox/{conversation}/draft-reply', [AiController::class, 'draftReply'])->whereNumber('conversation')->name('ai.draft');
    Route::post('/contacts/{contact}/score', [AiController::class, 'score'])->whereNumber('contact')->name('ai.score');
    Route::post('/contacts/{contact}/score-override', [AiController::class, 'scoreOverride'])
        ->whereNumber('contact')->middleware('can:view-all')->name('ai.score-override');

    // Leads (F2)
    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
    Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
    Route::post('/leads/{lead}/status', [LeadController::class, 'updateStatus'])->whereNumber('lead')->name('leads.status');
    Route::post('/leads/{lead}/convert', [LeadController::class, 'convert'])->whereNumber('lead')->name('leads.convert');

    // Notifications (bell)
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/notifications/{id}', [NotificationController::class, 'read'])->name('notifications.read');

    // Global search (command palette)
    Route::get('/search', [SearchController::class, 'index'])->name('search');

    // Overview dashboard (S06)
    Route::get('/insights', [OverviewController::class, 'index'])->name('insights');

    // Settings (F1) — admin only
    Route::get('/settings', [SettingsController::class, 'index'])
        ->middleware('can:manage-settings')->name('settings.index');
    Route::post('/settings/users/{user}/role', [SettingsController::class, 'updateRole'])
        ->whereNumber('user')->middleware('can:manage-settings')->name('settings.role');
    Route::post('/settings/users/{user}/active', [SettingsController::class, 'updateUserActive'])
        ->whereNumber('user')->middleware('can:manage-settings')->name('settings.active');
    Route::post('/settings/working-hours', [SettingsController::class, 'updateWorkingHours'])
        ->middleware('can:manage-settings')->name('settings.hours');

    Route::middleware('can:manage-settings')->prefix('settings')->group(function () {
        Route::post('/company', [SettingsController::class, 'updateCompany'])->name('settings.company');
        Route::post('/users/{user}/quota', [SettingsController::class, 'updateQuota'])->whereNumber('user')->name('settings.quota');
        Route::patch('/stages/{stage}', [SettingsPipelineController::class, 'updateStage'])->whereNumber('stage')->name('settings.stage.update');
        Route::post('/stages/{stage}/move', [SettingsPipelineController::class, 'moveStage'])->whereNumber('stage')->name('settings.stage.move');
        Route::delete('/stages/{stage}', [SettingsPipelineController::class, 'deleteStage'])->whereNumber('stage')->name('settings.stage.delete');
        Route::post('/pipelines/{pipeline}/stages', [SettingsPipelineController::class, 'addStage'])->whereNumber('pipeline')->name('settings.stage.add');
        Route::post('/templates', [SettingsPipelineController::class, 'saveTemplate'])->name('settings.template.create');
        Route::patch('/templates/{template}', [SettingsPipelineController::class, 'saveTemplate'])->whereNumber('template')->name('settings.template.update');
        Route::delete('/templates/{template}', [SettingsPipelineController::class, 'deleteTemplate'])->whereNumber('template')->name('settings.template.delete');
    });
});

// WhatsApp Cloud API webhook (public; signature verified inside).
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('whatsapp.verify');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])->name('whatsapp.receive');

// Demo simulation endpoint (admin only).
Route::post('/dev/simulate-inbound', [SimulateInboundController::class, 'store'])
    ->middleware(['auth', 'company', 'can:manage-settings'])
    ->name('dev.simulate-inbound');
