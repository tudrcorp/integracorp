<?php

use App\Jobs\AnulateAgentQuotes;
use App\Jobs\BackupDatabase;
use App\Jobs\ExpireOperationServiceOrders;
use App\Jobs\ExportCorporateAffiliations;
use App\Jobs\ExportIndividualAffiliations;
use App\Jobs\ExportScheduledEntity;
use App\Jobs\PrepareAffiliationRenovations;
use App\Jobs\SendCollaboratorAnniversaryNotification;
use App\Jobs\SendNotificationBirthday;
use App\Jobs\UpdateAffiliateIlsRemainingDays;
use App\Jobs\UpdateAnnualCollectionRemainingDays;
use Illuminate\Support\Facades\Schedule;

/**
 * Tarea que se ejecuta para enviar las tarjetas de cumpleaños
 * Se ejecutara todos los dias a las 8:00am
 */
Schedule::job(new SendNotificationBirthday, 'system')->dailyAt('8:00');

/**
 * Notificaciones de aniversario de colaboradores (día y mes de fechaIngreso = hoy).
 * Envía email y WhatsApp a cada colaborador que cumple años en la empresa.
 * Se ejecuta todos los días a las 8:00.
 */
Schedule::job(new SendCollaboratorAnniversaryNotification, 'system')->dailyAt('8:00');

/**
 * Tarea para recalcular días restantes hacia el próximo mes en false
 * de la cobranza anual. Se ejecuta todos los días a las 6:00am.
 */
Schedule::job(new UpdateAnnualCollectionRemainingDays, 'system')->dailyAt('6:00');

/**
 * Prepara renovaciones individuales: recalcula tarifas por edad y crea registro en renovations
 * cuando faltan 30 o más días para la fecha de renovación (effective_date + 1 año).
 */
Schedule::job(new PrepareAffiliationRenovations, 'renovations')->dailyAt('6:00');

/**
 * Recalcula días restantes hasta dateEnd (vaucher ILS) en familiares afiliados.
 * Se ejecuta todos los días a las 6:00.
 */
Schedule::job(new UpdateAffiliateIlsRemainingDays, 'system')->dailyAt('6:00');

/**
 * Tarea que anula cotizaciones individuales generadas por el agente.
 * Se ejecuta todos los días a las 23:00:00.
 * Actualiza status a ANULADA, elimina el PDF en storage/app/public/quotes
 * y envía correo a cotizaciones@tudrencasa.com con el número de cotizaciones anuladas.
 */
Schedule::job(new AnulateAgentQuotes, 'system')->dailyAt('23:00');

/**
 * Caduca órdenes de servicio no finalizadas dentro de los 10 días posteriores a su aprobación.
 */
Schedule::job(new ExpireOperationServiceOrders, 'system')->dailyAt('7:30');

/**
 * Respaldo completo de la base de datos en .sql (estructura + datos).
 * Envía resumen y archivo adjunto por WhatsApp al finalizar.
 */
Schedule::job(new BackupDatabase, 'system')->dailyAt('2:00');

/**
 * Exportaciones Excel diarias (cola system). Cada job se ejecuta cada 10 minutos desde las 6:00.
 */
Schedule::job(new ExportIndividualAffiliations, 'system')->dailyAt('6:00');
Schedule::job(new ExportCorporateAffiliations, 'system')->dailyAt('6:10');
Schedule::job(new ExportScheduledEntity('agents'), 'system')->dailyAt('6:20');
Schedule::job(new ExportScheduledEntity('agencies'), 'system')->dailyAt('6:30');
Schedule::job(new ExportScheduledEntity('natural_providers'), 'system')->dailyAt('6:40');
Schedule::job(new ExportScheduledEntity('juridical_providers'), 'system')->dailyAt('6:50');
Schedule::job(new ExportScheduledEntity('collaborators'), 'system')->dailyAt('7:00');
Schedule::job(new ExportScheduledEntity('doctors'), 'system')->dailyAt('7:10');
