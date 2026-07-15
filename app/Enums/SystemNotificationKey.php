<?php

declare(strict_types=1);

namespace App\Enums;

enum SystemNotificationKey: string
{
    case CompanyAssociateRegistration = 'company_associate_registration';
    case IndividualQuoteFollowUp = 'individual_quote_follow_up';
    case AgentQuoteAnulation = 'agent_quote_anulation';
    case DatabaseBackup = 'database_backup';
    case StructureBackup = 'structure_backup';
    case DailyAuditSummary = 'daily_audit_summary';

    public function label(): string
    {
        return match ($this) {
            self::CompanyAssociateRegistration => 'Registro de asociados',
            self::IndividualQuoteFollowUp => 'Follow-up cotizaciones',
            self::AgentQuoteAnulation => 'Anulación de cotizaciones',
            self::DatabaseBackup => 'Respaldo de base de datos',
            self::StructureBackup => 'Respaldo de Estructura',
            self::DailyAuditSummary => 'Auditorías completas',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CompanyAssociateRegistration => 'Destinatarios principales de las alertas cuando un asociado se registra desde el enlace público.',
            self::IndividualQuoteFollowUp => 'Copias internas (control) de los seguimientos WhatsApp de cotizaciones PRE-APROBADA (días 3, 5, 7, 9 y 12). El mensaje principal sigue yendo al agente o a la agencia.',
            self::AgentQuoteAnulation => 'Destinatarios del resumen diario cuando se anulan cotizaciones individuales con más de 15 días sin aprobar ni ejecutar.',
            self::DatabaseBackup => 'Destinatarios del resumen y del archivo .sql al finalizar el respaldo diario de la base de datos.',
            self::StructureBackup => 'Destinatarios de las exportaciones Excel diarias de estructura (afiliaciones, agentes, agencias, proveedores, colaboradores y doctores).',
            self::DailyAuditSummary => 'Destinatarios del reporte diario de auditorías completas (agencias, agentes y afiliaciones).',
        };
    }

    public function heroTitle(): string
    {
        return match ($this) {
            self::CompanyAssociateRegistration => 'Alertas de nuevos asociados',
            self::IndividualQuoteFollowUp => 'Copias internas de follow-up',
            self::AgentQuoteAnulation => 'Resumen de cotizaciones anuladas',
            self::DatabaseBackup => 'Respaldo diario de base de datos',
            self::StructureBackup => 'Exportaciones diarias de estructura',
            self::DailyAuditSummary => 'Reporte diario de auditorías',
        };
    }

    public function heroBody(): string
    {
        return match ($this) {
            self::CompanyAssociateRegistration => 'Cada registro público dispara de forma asíncrona un correo y un WhatsApp con el detalle del asociado, la empresa, el responsable y un recordatorio para iniciar la gestión del voucher ILS.',
            self::IndividualQuoteFollowUp => 'Además del WhatsApp al agente o agencia que creó la cotización, el sistema puede enviar una copia interna por correo y WhatsApp a los contactos de control configurados aquí.',
            self::AgentQuoteAnulation => 'Cada noche el sistema anula cotizaciones individuales con más de 15 días sin aprobar ni ejecutar, elimina su PDF y notifica el total a los contactos configurados aquí.',
            self::DatabaseBackup => 'Cada madrugada se genera un .sql completo (estructura + datos). Al finalizar, los contactos configurados reciben el resumen; por WhatsApp también el archivo adjunto si no supera el límite.',
            self::StructureBackup => 'Entre las 6:00 y las 7:10 se generan Excel de afiliaciones individuales/corporativas, agentes, agencias, proveedores, colaboradores y doctores. Cada job notifica resumen y adjunta el archivo por WhatsApp cuando aplica.',
            self::DailyAuditSummary => 'Cada día a las 7:00 se contabilizan agencias, agentes y afiliaciones con auditoría completa (todos los puntos verificados) y se envía el resumen por WhatsApp y correo a estos contactos.',
        };
    }

    /**
     * @return list<string>
     */
    public function flowSteps(): array
    {
        return match ($this) {
            self::CompanyAssociateRegistration => [
                '1. Registro público',
                '2. Cola asíncrona',
                '3. Email + WhatsApp',
                '4. Gestión voucher ILS',
            ],
            self::IndividualQuoteFollowUp => [
                '1. Cotización PRE-APROBADA',
                '2. Cron días 3/5/7/9/12',
                '3. WhatsApp al aliado',
                '4. Copia interna control',
            ],
            self::AgentQuoteAnulation => [
                '1. Cron 23:00',
                '2. Anular + borrar PDF',
                '3. Contar anuladas',
                '4. Email / WhatsApp resumen',
            ],
            self::DatabaseBackup => [
                '1. Cron 02:00',
                '2. Generar .sql',
                '3. Purgar antiguos',
                '4. Notificar resumen',
            ],
            self::StructureBackup => [
                '1. Cron 6:00–7:10',
                '2. Generar Excel',
                '3. Purgar antiguos',
                '4. Notificar + adjunto',
            ],
            self::DailyAuditSummary => [
                '1. Cron 7:00',
                '2. Contar auditorías',
                '3. Armar resumen',
                '4. Email + WhatsApp',
            ],
        };
    }

    public function calloutTitle(): string
    {
        return match ($this) {
            self::CompanyAssociateRegistration => 'Acción requerida para el analista:',
            self::IndividualQuoteFollowUp => 'Copia de control:',
            self::AgentQuoteAnulation => 'Resumen operativo:',
            self::DatabaseBackup => 'Importante:',
            self::StructureBackup => 'Grupo de exportaciones:',
            self::DailyAuditSummary => 'Criterio del reporte:',
        };
    }

    public function calloutBody(): string
    {
        return match ($this) {
            self::CompanyAssociateRegistration => 'al recibir la alerta debe ingresar a INTEGRACORP → Nuevos Negocios → Asociados y cargar el voucher ILS para activar el plan del asociado registrado.',
            self::IndividualQuoteFollowUp => 'el WhatsApp principal sigue yendo al teléfono del agente o de la agencia que creó la cotización. Los contactos de esta pestaña reciben únicamente la copia interna para seguimiento operativo.',
            self::AgentQuoteAnulation => 'solo se envía el resumen si hubo anulaciones ese día. Sin destinatarios configurados, la anulación sigue ejecutándose pero no se notifica.',
            self::DatabaseBackup => 'el respaldo siempre se genera en el servidor. Sin destinatarios, no habrá aviso. El .sql se adjunta por WhatsApp solo si no supera el límite configurado.',
            self::StructureBackup => 'incluye afiliaciones individuales y corporativas, agentes, agencias, proveedores naturales/jurídicos, colaboradores y doctores. Los Excel se generan igual sin destinatarios; solo se omite la notificación.',
            self::DailyAuditSummary => 'solo se contabilizan registros con TODOS los puntos de auditoría verificados. Las auditorías parciales no entran en los totales.',
        };
    }

    public function calloutIcon(): string
    {
        return match ($this) {
            self::CompanyAssociateRegistration => 'heroicon-o-ticket',
            self::IndividualQuoteFollowUp => 'heroicon-o-shield-check',
            self::AgentQuoteAnulation => 'heroicon-o-document-minus',
            self::DatabaseBackup => 'heroicon-o-circle-stack',
            self::StructureBackup => 'heroicon-o-table-cells',
            self::DailyAuditSummary => 'heroicon-o-clipboard-document-check',
        };
    }

    /**
     * @return list<string>
     */
    public function defaultEmails(): array
    {
        return match ($this) {
            self::CompanyAssociateRegistration => [],
            self::IndividualQuoteFollowUp => [],
            self::AgentQuoteAnulation => [
                'cotizaciones@tudrencasa.com',
            ],
            self::DatabaseBackup => [],
            self::StructureBackup => [],
            self::DailyAuditSummary => [
                'solrodriguez@tudrencasa.com',
            ],
        };
    }

    /**
     * @return list<string>
     */
    public function defaultPhones(): array
    {
        return match ($this) {
            self::CompanyAssociateRegistration => [],
            self::IndividualQuoteFollowUp => [
                '04127018390',
                '04143027250',
            ],
            self::AgentQuoteAnulation => [],
            self::DatabaseBackup => [
                '04127018390',
                '04143027250',
            ],
            self::StructureBackup => [
                '04127018390',
                '04143027250',
            ],
            self::DailyAuditSummary => [
                '04127018390',
                '04143027250',
                '04245718777',
            ],
        };
    }

    public function emptyRecipientsHint(): string
    {
        return match ($this) {
            self::CompanyAssociateRegistration => 'Aún no hay destinatarios configurados. Agregue al menos un correo o un teléfono para activar las notificaciones.',
            self::IndividualQuoteFollowUp => 'Sin copias internas configuradas. Los seguimientos seguirán enviándose al agente o agencia, pero no habrá copia de control.',
            self::AgentQuoteAnulation => 'Sin destinatarios configurados. Las cotizaciones seguirán anulándose, pero no se enviará el resumen diario.',
            self::DatabaseBackup => 'Sin destinatarios configurados. El respaldo se generará igual, pero no se enviará el resumen ni el archivo por WhatsApp/email.',
            self::StructureBackup => 'Sin destinatarios configurados. Las exportaciones Excel se generarán igual, pero no se enviará el resumen ni el archivo.',
            self::DailyAuditSummary => 'Sin destinatarios configurados. El conteo de auditorías se calculará igual, pero no se enviará el resumen.',
        };
    }

    public function savedRecipientsMessage(array $emails, array $phones): string
    {
        $empty = $emails === [] && $phones === [];

        return match ($this) {
            self::CompanyAssociateRegistration => $empty
                ? 'No hay destinatarios activos. Las notificaciones de asociados quedarán en pausa hasta que agregue contactos.'
                : 'Se notificará por correo y WhatsApp a los contactos configurados cuando se registre un asociado.',
            self::IndividualQuoteFollowUp => $empty
                ? 'Sin copias internas. Los follow-ups seguirán enviándose al agente o agencia.'
                : 'Las copias internas de los follow-ups de cotizaciones se enviarán a los contactos configurados.',
            self::AgentQuoteAnulation => $empty
                ? 'Sin destinatarios. La anulación automática continuará, pero no se enviará el resumen.'
                : 'El resumen diario de cotizaciones anuladas se enviará a los contactos configurados.',
            self::DatabaseBackup => $empty
                ? 'Sin destinatarios. El respaldo seguirá generándose, pero no se notificará.'
                : 'El resumen del respaldo (y el .sql por WhatsApp cuando aplique) se enviará a los contactos configurados.',
            self::StructureBackup => $empty
                ? 'Sin destinatarios. Las exportaciones de estructura seguirán generándose, pero no se notificarán.'
                : 'Los resúmenes de las exportaciones de estructura (y los Excel por WhatsApp cuando aplique) se enviarán a los contactos configurados.',
            self::DailyAuditSummary => $empty
                ? 'Sin destinatarios. El reporte de auditorías se calculará, pero no se enviará.'
                : 'El resumen diario de auditorías completas se enviará a los contactos configurados.',
        };
    }

    public function pausesScheduledTask(): bool
    {
        return $this !== self::CompanyAssociateRegistration;
    }

    public function activationHelp(): string
    {
        return match ($this) {
            self::CompanyAssociateRegistration => 'Si está inactiva, no se enviarán alertas al registrar un asociado (el registro público sigue funcionando).',
            self::IndividualQuoteFollowUp => 'Si está inactiva, no se ejecutarán los seguimientos de los días 3, 5, 7, 9 y 12.',
            self::AgentQuoteAnulation => 'Si está inactiva, no se anularán cotizaciones ni se enviará el resumen nocturno.',
            self::DatabaseBackup => 'Si está inactiva, no se generará el respaldo .sql ni se enviará la notificación.',
            self::StructureBackup => 'Si está inactiva, no se ejecutarán las exportaciones Excel diarias de estructura.',
            self::DailyAuditSummary => 'Si está inactiva, no se calculará ni enviará el reporte diario de auditorías.',
        };
    }

    /**
     * @return list<self>
     */
    public static function managed(): array
    {
        return [
            self::CompanyAssociateRegistration,
            self::IndividualQuoteFollowUp,
            self::AgentQuoteAnulation,
            self::DatabaseBackup,
            self::StructureBackup,
            self::DailyAuditSummary,
        ];
    }
}
