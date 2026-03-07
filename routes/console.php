<?php

use App\Jobs\AnulateAgentQuotes;
use App\Jobs\SendNotificationBirthday;
use App\Jobs\ValidateDateToRenew;
use Illuminate\Support\Facades\Schedule;

/**
 * Tarea que se ejecuta para enviar las tarjetas de cumpleaños
 * Se ejecutara todos los dias a las 8:00am
 */
Schedule::job(new SendNotificationBirthday, 'system')->dailyAt('8:00');

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

/**
 * Tarea que anula cotizaciones individuales generadas por el agente.
 * Se ejecuta todos los días a las 23:00:00.
 * Actualiza status a ANULADA, elimina el PDF en storage/app/public/quotes
 * y envía correo a cotizaciones@tudrencasa.com con el número de cotizaciones anuladas.
 */
Schedule::job(new AnulateAgentQuotes, 'system')->dailyAt('23:00');
