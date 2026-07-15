<?php

use App\Enums\SystemNotificationKey;
use App\Jobs\AnulateAgentQuotes;
use App\Jobs\BackupDatabase;
use App\Jobs\DispatchScheduledMassNotifications;
use App\Jobs\ExpireOperationServiceOrders;
use App\Jobs\ExportCorporateAffiliations;
use App\Jobs\ExportIndividualAffiliations;
use App\Jobs\ExportScheduledEntity;
use App\Jobs\PrepareAffiliationRenovations;
use App\Jobs\SendCollaboratorAnniversaryNotification;
use App\Jobs\SendDailyAuditSummary;
use App\Jobs\SendIndividualQuoteDayFiveFollowUp;
use App\Jobs\SendIndividualQuoteDayNineFollowUp;
use App\Jobs\SendIndividualQuoteDaySevenFollowUp;
use App\Jobs\SendIndividualQuoteDayThreeFollowUp;
use App\Jobs\SendIndividualQuoteDayTwelveFollowUp;
use App\Jobs\SendNotificationBirthday;
use App\Jobs\UpdateAffiliateIlsRemainingDays;
use App\Jobs\UpdateAnnualCollectionRemainingDays;
use App\Support\IndividualQuotes\IndividualQuoteFollowUp;
use App\Support\SystemNotificationRecipients;
use Illuminate\Support\Facades\Schedule;

$individualQuoteFollowUpIsActive = static fn (): bool => IndividualQuoteFollowUp::isSchedulingActive()
    && SystemNotificationRecipients::isActive(SystemNotificationKey::IndividualQuoteFollowUp);

$agentQuoteAnulationIsActive = static fn (): bool => SystemNotificationRecipients::isActive(SystemNotificationKey::AgentQuoteAnulation);
$databaseBackupIsActive = static fn (): bool => SystemNotificationRecipients::isActive(SystemNotificationKey::DatabaseBackup);
$structureBackupIsActive = static fn (): bool => SystemNotificationRecipients::isActive(SystemNotificationKey::StructureBackup);
$dailyAuditSummaryIsActive = static fn (): bool => SystemNotificationRecipients::isActive(SystemNotificationKey::DailyAuditSummary);

/**
 * Tarea que se ejecuta para enviar las tarjetas de cumpleaños
 * Se ejecutara todos los dias a las 8:00am
 */
Schedule::job(new SendNotificationBirthday, 'system')->dailyAt('8:00');

/**
 * Seguimiento WhatsApp de cotizaciones individuales PRE-APROBADA creadas hace 3 días.
 * Agrupa por agente o agencia y notifica al teléfono del agente (agent_id) o de la agencia (code_agency).
 * Activo a partir de config individual-quotes.follow_up_scheduling_start_date
 * y del interruptor del Centro de notificaciones.
 */
Schedule::job(new SendIndividualQuoteDayThreeFollowUp, 'system')
    ->dailyAt('8:00')
    ->when($individualQuoteFollowUpIsActive);

/**
 * Seguimiento WhatsApp de cotizaciones individuales PRE-APROBADA creadas hace 5 días.
 * Envía mensaje explicativo y video informativo.
 */
Schedule::job(new SendIndividualQuoteDayFiveFollowUp, 'system')
    ->dailyAt('8:10')
    ->when($individualQuoteFollowUpIsActive);

/**
 * Seguimiento WhatsApp de cotizaciones individuales PRE-APROBADA creadas hace 7 días.
 * Envía mensaje e imágenes informativas (adquisición del plan y métodos de pago).
 */
Schedule::job(new SendIndividualQuoteDaySevenFollowUp, 'system')
    ->dailyAt('8:20')
    ->when($individualQuoteFollowUpIsActive);

/**
 * Seguimiento WhatsApp de cotizaciones individuales PRE-APROBADA creadas hace 9 días.
 * Envía mensaje y flyer de beneficios (flayer.pdf).
 */
Schedule::job(new SendIndividualQuoteDayNineFollowUp, 'system')
    ->dailyAt('8:30')
    ->when($individualQuoteFollowUpIsActive);

/**
 * Seguimiento WhatsApp de cotizaciones individuales PRE-APROBADA creadas hace 12 días.
 * Recordatorio de vencimiento próximo de la cotización.
 */
Schedule::job(new SendIndividualQuoteDayTwelveFollowUp, 'system')
    ->dailyAt('8:40')
    ->when($individualQuoteFollowUpIsActive);

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
 * y notifica el resumen a los destinatarios del Centro de notificaciones (agent_quote_anulation).
 */
Schedule::job(new AnulateAgentQuotes, 'system')
    ->dailyAt('23:00')
    ->when($agentQuoteAnulationIsActive);

/**
 * Caduca órdenes de servicio no finalizadas dentro de los 10 días posteriores a su aprobación.
 */
Schedule::job(new ExpireOperationServiceOrders, 'system')->dailyAt('7:30');

/**
 * Dispara notificaciones masivas cuya fecha programada ya venció.
 */
Schedule::job(new DispatchScheduledMassNotifications, 'system')->everyMinute();

/**
 * Respaldo completo de la base de datos en .sql (estructura + datos).
 * Envía resumen (y archivo por WhatsApp) a los destinatarios del Centro de notificaciones (database_backup).
 */
Schedule::job(new BackupDatabase, 'system')
    ->dailyAt('2:00')
    ->when($databaseBackupIsActive);

/**
 * Respaldo de Estructura: exportaciones Excel diarias (cola system).
 * Cada job se ejecuta cada 10 minutos desde las 6:00.
 * Notifica a los destinatarios del Centro de notificaciones (structure_backup).
 */
Schedule::job(new ExportIndividualAffiliations, 'system')
    ->dailyAt('6:00')
    ->when($structureBackupIsActive);
Schedule::job(new ExportCorporateAffiliations, 'system')
    ->dailyAt('6:10')
    ->when($structureBackupIsActive);
Schedule::job(new ExportScheduledEntity('agents'), 'system')
    ->dailyAt('6:20')
    ->when($structureBackupIsActive);
Schedule::job(new ExportScheduledEntity('agencies'), 'system')
    ->dailyAt('6:30')
    ->when($structureBackupIsActive);
Schedule::job(new ExportScheduledEntity('natural_providers'), 'system')
    ->dailyAt('6:40')
    ->when($structureBackupIsActive);
Schedule::job(new ExportScheduledEntity('juridical_providers'), 'system')
    ->dailyAt('6:50')
    ->when($structureBackupIsActive);
Schedule::job(new ExportScheduledEntity('collaborators'), 'system')
    ->dailyAt('7:00')
    ->when($structureBackupIsActive);
Schedule::job(new ExportScheduledEntity('doctors'), 'system')
    ->dailyAt('7:10')
    ->when($structureBackupIsActive);

/**
 * Reporte diario de auditorías completas (agencias, agentes, afiliaciones individuales y corporativas).
 * Contabiliza solo los registros con TODOS sus puntos de auditoría verificados y envía el
 * resumen por WhatsApp y correo a los destinatarios del Centro de notificaciones (daily_audit_summary).
 * Se ejecuta todos los días a las 7:00am.
 */
Schedule::job(new SendDailyAuditSummary, 'system')
    ->dailyAt('7:00')
    ->when($dailyAuditSummaryIsActive);
