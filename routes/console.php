<?php

use App\Jobs\ValidateDateToRenew;
use App\Jobs\DocumentUploadReminder;
use Illuminate\Foundation\Inspiring;
use App\Jobs\SendNotificationBirthday;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\SendNotificationRemenberMedication;

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote');

// Schedule::job(new DocumentUploadReminder, 'system')->everyFiveMinutes(); ->everyMinute();
// Schedule::job(new SendNotificationBirthday, 'system')->dailyAt('8:00');

/**
 * Tarea que se ejecuta para enviar las tarjetas de cumpleaños
 * Se ejecutara todos los dias a las 8:00am
 */
Schedule::job(new SendNotificationBirthday, 'system')->everyFiveMinutes();


/**
 * Tarea que se ejecuta para enviar los medicamentos asignados al paciente
 * en el proceso de la telemedicina
 * 
 * Se ejecutara todos los dias cada 6 horas
 * Hora de inicio = 8:00am
 */
// Schedule::job(new SendNotificationRemenberMedication, 'system')->everySixHours();

/**
 * Tarea que se ejecuta para validar las afiliacione que esta para renovar
 * 
 * Se ejecutara todos los dias cada 6 horas
 * Hora de inicio = 8:00am
 */
Schedule::job(new ValidateDateToRenew, 'renew')->dailyAt('00:00');