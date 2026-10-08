<?php

declare(strict_types=1);

use App\Http\Controllers\CrmHandoffMessageController;
use App\Http\Controllers\CrmHandoffWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Puerta del CRM. Sin el grupo `web`: no abre sesión ni pide CSRF.
| La firma HMAC reemplaza a la cookie. El trabajo pesado va a Redis.
*/
Route::post('/api/crm/handoff', CrmHandoffWebhookController::class)
    ->name('crm.handoff.store');

Route::post('/api/crm/handoff/message', CrmHandoffMessageController::class)
    ->name('crm.handoff.message');
