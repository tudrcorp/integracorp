<?php

use App\Jobs\DocumentUploadReminder;
use Illuminate\Foundation\Inspiring;
use App\Jobs\SendNotificationBirthday;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\SendNotificationRemenberMedication;

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote');

// Schedule::job(new DocumentUploadReminder, 'system')->everyFiveMinutes();
// Schedule::job(new SendNotificationBirthday, 'system')->everyFiveMinutes();

// Schedule::command('reminder:uploaddoc')->everyFiveMinutes()->runInBackground();


/**
 * Tarea que se ejecuta para enviar los medicamentos asignados al paciente
 * en el proceso de la telemedicina
 * 
 * Se ejecutara todos los dias cada 6 horas
 * Hora de inicio = 8:00am
 */
Schedule::job(new SendNotificationRemenberMedication, 'system')->everySixHours();