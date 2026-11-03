<?php

use App\Http\Controllers\Api\IntegrationController;
use Illuminate\Support\Facades\Route;

// Public integration API. Auth: "Authorization: Bearer rly_…" (php artisan relay:api-key).
Route::middleware(['api.key', 'throttle:300,1'])->group(function () {
    Route::post('/contacts/upsert', [IntegrationController::class, 'upsertContact']);
    Route::post('/contacts/{contact}/activities', [IntegrationController::class, 'addActivity'])->whereNumber('contact');
    Route::post('/contacts/{contact}/tasks', [IntegrationController::class, 'createTask'])->whereNumber('contact');
    Route::post('/deals', [IntegrationController::class, 'createDeal']);
});
