<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\ScheduledNotificationPhones;

enum SystemNotificationKey: string
{
    case CompanyAssociateRegistration = 'company_associate_registration';
    case IndividualQuoteFollowUp = 'individual_quote_follow_up';
    case AgentQuoteAnulation = 'agent_quote_anulation';
    case DatabaseBackup = 'database_backup';
    case StructureBackup = 'structure_backup';
    case DailyAuditSummary = 'daily_audit_summary';
    case OperationInventoryLowStock = 'operation_inventory_low_stock';
    case TelemedicineCaseReversal = 'telemedicine_case_reversal';
    case BirthdayNotificationWitnessCopy = 'birthday_notification_witness_copy';
    case BirthdayNotificationSummary = 'birthday_notification_summary';
    case TdevRegistration = 'tdev_registration';
    case CompanyAssociateIlsCoverage = 'company_associate_ils_coverage';
    case TelemedicineServiceLimitOverride = 'telemedicine_service_limit_override';
    case StorefrontQuotePaymentReceipt = 'storefront_quote_payment_receipt';
    case CorporatePaymentFrequencyChange = 'corporate_payment_frequency_change';
    case LiveSecurityAlert = 'live_security_alert';
    case LiveSystemAlert = 'live_system_alert';
    case OperationsAffiliateUpdate = 'operations_affiliate_update';
    case EarlyRenovationAcceptance = 'early_renovation_acceptance';
    case TelemedicineCaseAgentNotice = 'telemedicine_case_agent_notice';
    case CorporatePopulationUpdate = 'corporate_population_update';
    case SaleReceiptWitnessCopy = 'sale_receipt_witness_copy';
    case CollectionNoticeWitnessCopy = 'collection_notice_witness_copy';
    case TelegramTest = 'telegram_test';
    case ScheduledTaskSummary = 'scheduled_task_summary';
    case ClinicalUsageAccessOtp = 'clinical_usage_access_otp';
    case PersonalAssignments = 'personal_assignments';
    case DoctorCaseAssignment = 'doctor_case_assignment';
    case HelpdeskNotices = 'helpdesk_notices';
    case ContadoQuotePayment = 'contado_quote_payment';
    case CourtesyFinance = 'courtesy_finance';
    case NewQuoteAnalysts = 'new_quote_analysts';
    case CommercialNetworkActivity = 'commercial_network_activity';
    case DressTaylorRequest = 'dress_taylor_request';
    case GuiaChatFeedback = 'guia_chat_feedback';

    public function label(): string
    {
        return match ($this) {
            self::CompanyAssociateRegistration => 'Registro de asociados',
            self::IndividualQuoteFollowUp => 'Follow-up cotizaciones',
            self::AgentQuoteAnulation => 'Anulación de cotizaciones',
            self::DatabaseBackup => 'Respaldo de base de datos',
            self::StructureBackup => 'Respaldo de Estructura',
            self::DailyAuditSummary => 'Auditorías completas',
            self::OperationInventoryLowStock => 'Stock bajo de inventario',
            self::TelemedicineCaseReversal => 'Reverso de casos de telemedicina',
            self::BirthdayNotificationWitnessCopy => 'Copia testigo cumpleaños',
            self::BirthdayNotificationSummary => 'Resumen cumpleaños',
            self::TdevRegistration => 'Registros TDEV',
            self::CompanyAssociateIlsCoverage => 'Cobertura ILS confirmada',
            self::TelemedicineServiceLimitOverride => 'Autorización de servicio fuera de límite',
            self::StorefrontQuotePaymentReceipt => 'Comprobante de pago PWA',
            self::CorporatePaymentFrequencyChange => 'Cambio de frecuencia de pago corporativa',
            self::LiveSecurityAlert => 'Alertas de seguridad',
            self::LiveSystemAlert => 'Alertas de colas y errores',
            self::OperationsAffiliateUpdate => 'Actualización de afiliados (Operaciones)',
            self::EarlyRenovationAcceptance => 'Renovación anticipada',
            self::CorporatePopulationUpdate => 'Clave OTP de actualización de población corporativa',
            self::TelemedicineCaseAgentNotice => 'Nuevo caso al agente de la afiliación',
            self::SaleReceiptWitnessCopy => 'Testigos de recibos de pago',
            self::CollectionNoticeWitnessCopy => 'Testigos de avisos de cobro',
            self::TelegramTest => 'Prueba de notificación a Telegram',
            self::ScheduledTaskSummary => 'Resúmenes de tareas programadas',
            self::ClinicalUsageAccessOtp => 'Clave OTP de acceso a uso clínico',
            self::PersonalAssignments => 'Asignaciones e invitaciones personales',
            self::DoctorCaseAssignment => 'Caso asignado al médico',
            self::HelpdeskNotices => 'Avisos del Helpdesk',
            self::ContadoQuotePayment => 'Pagos de contado urgentes',
            self::CourtesyFinance => 'Servicios por cortesía',
            self::NewQuoteAnalysts => 'Nuevas cotizaciones',
            self::CommercialNetworkActivity => 'Movimientos de la red comercial',
            self::DressTaylorRequest => 'Solicitudes Dress Taylor',
            self::GuiaChatFeedback => 'Feedback de la Guía Chat',
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
            self::OperationInventoryLowStock => 'Destinatarios de la alerta diaria de productos de inventario Diagnomóvil con existencia total menor o igual al umbral configurado.',
            self::TelemedicineCaseReversal => 'Destinatarios de la alerta cuando un médico reversa un caso desde el panel de telemedicina (correo y WhatsApp con la nota del reverso).',
            self::BirthdayNotificationWitnessCopy => 'Copias testigo de cada tarjeta de cumpleaños enviada (WhatsApp duplicado y CC en el correo). No incluye el resumen de la corrida.',
            self::BirthdayNotificationSummary => 'Destinatarios del resumen de ejecución de la tarea diaria de tarjetas de cumpleaños (totales, fallas y configuración). No reciben la copia de cada envío.',
            self::TdevRegistration => 'Destinatarios de las alertas cuando se registra una agencia nivel 3 o un agente TDEV (freelance de nivel 2 o asociado a nivel 3) desde los formularios públicos.',
            self::CompanyAssociateIlsCoverage => 'Destinatarios del aviso que confirma que un asociado de nuevos negocios quedó cubierto en su totalidad, con el número de voucher ILS, su vigencia y el documento adjunto.',
            self::TelemedicineServiceLimitOverride => 'Quienes reciben la clave OTP de 6 dígitos cuando un médico pide asignar un servicio clínico que ya agotó el cupo del plan. El médico no recibe la clave: se la dictan estos contactos.',
            self::StorefrontQuotePaymentReceipt => 'Destinatarios de Administración (correo y WhatsApp) cuando un cliente carga el comprobante de una cotización desde la PWA.',
            self::CorporatePaymentFrequencyChange => 'Contactos de Administración (correo y WhatsApp) que reciben el aviso cuando Negocios cambia la frecuencia de pago de una afiliación corporativa, y cuando Administración revierte ese cambio. Además se avisa siempre a todos los usuarios activos del departamento de Administración.',
            self::LiveSecurityAlert => 'Destinatarios del aviso por WhatsApp y correo cuando el monitor en vivo detecta un ataque: fuerza bruta, relleno de credenciales, ataque distribuido a una cuenta, inundación de peticiones o cuenta bloqueada.',
            self::LiveSystemAlert => 'Destinatarios del aviso por WhatsApp y correo cuando una cola se queda sin worker, se atasca o acumula trabajos colgados, cuando se dispara la cantidad de trabajos fallidos, o cuando aparece un error nuevo o vuelve uno ya resuelto.',
            self::OperationsAffiliateUpdate => 'Equipo de Afiliaciones que recibe, por WhatsApp y correo, el detalle de cada dato personal de un afiliado individual o corporativo que Operaciones actualiza: qué cambió, el valor anterior y el nuevo, quién lo hizo y cuándo.',
            self::EarlyRenovationAcceptance => 'Contactos adicionales que reciben, por WhatsApp y correo, el aviso cada vez que un analista con permiso acepta una renovación (individual o corporativa) antes del período de renovación. Además se avisa siempre a todos los usuarios activos con SUPERADMIN.',
            self::CorporatePopulationUpdate => 'Quienes reciben la clave OTP de 6 dígitos cuando un analista de Negocios va a aplicar o revertir la actualización de población de una afiliación corporativa (altas, reactivaciones, bajas y corrección de cédulas desde el archivo del cliente).',
            self::TelemedicineCaseAgentNotice => 'Copias por WhatsApp del aviso que recibe el agente (o la agencia) dueño de la afiliación cuando se registra un caso nuevo de telemedicina para uno de sus afiliados.',
            self::SaleReceiptWitnessCopy => 'Copias testigo de cada recibo de pago enviado desde Administración → Ventas: por correo (en copia visible CC u oculta CCO, según cada contacto) y por WhatsApp con el PDF. También reciben la copia del envío del recibo por WhatsApp.',
            self::CollectionNoticeWitnessCopy => 'Copias testigo de cada aviso de cobro enviado desde Gestión de Cobranza o Cobranza Por Mes: por correo (en copia visible CC u oculta CCO, según cada contacto) y por WhatsApp con el PDF.',
            self::TelegramTest => 'Mensaje de prueba para comprobar que Telegram entrega a las personas y grupos elegidos, y que el respaldo por WhatsApp y correo funciona.',
            self::ScheduledTaskSummary => 'Destinatarios del resumen que envía cada tarea programada sin aviso propio: renovaciones, días de ILS y de cobranza anual, caducidad de órdenes de servicio, detectores de inconsistencias y duplicados, aniversarios y seguimientos de cotizaciones.',
            self::ClinicalUsageAccessOtp => 'La clave OTP para entrar a configurar el uso clínico (cupos) de un plan va siempre a todos los SUPERADMIN activos. Aquí solo se elige si les llega por Telegram en vez de WhatsApp.',
            self::PersonalAssignments => 'Avisos que van a una persona concreta: la tarea de un prospecto asignada a un colaborador, la invitación a una actividad de la Agenda corporativa y la respuesta a esa invitación. Aquí solo se elige si usan Telegram.',
            self::DoctorCaseAssignment => 'Aviso al médico cuando Operaciones le asigna un caso de telemedicina. Aquí solo se elige si le llega por Telegram (sin el motivo de consulta, que sigue en el correo y en el portal).',
            self::HelpdeskNotices => 'Avisos de los tickets del Helpdesk a las personas que les toca: ticket creado o asignado, notas, cambios de estado o prioridad, reasignaciones, flujo Scrum y reportes de error. Aquí solo se elige si usan Telegram.',
            self::ContadoQuotePayment => 'Quienes reciben el aviso urgente cuando Operaciones aprueba una cotización de proveedor que se paga de contado: montos en US$ y Bs, tasa BCV, paciente, caso y proveedor. El correo a Administración no cambia.',
            self::CourtesyFinance => 'Quienes reciben el aviso cuando Operaciones marca un servicio como cortesía (en la cotización, en la cuenta por cobrar o en la orden de servicio). El correo a Administración no cambia.',
            self::NewQuoteAnalysts => 'Analistas que reciben el aviso, con la propuesta en PDF, cada vez que un agente o una agencia crea una cotización individual o corporativa.',
            self::CommercialNetworkActivity => 'Analistas que reciben los movimientos de la red comercial: data corporativa cargada, comprobante de pago cargado, pre-afiliación individual creada y observaciones en cotizaciones.',
            self::DressTaylorRequest => 'Quienes reciben cada solicitud de cotización Dress Taylor que crea un agente, con sus características.',
            self::GuiaChatFeedback => 'Quienes reciben por WhatsApp o Telegram cada comentario o reporte que deja un visitante en la Guía Chat pública. El correo a soporte no cambia.',
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
            self::OperationInventoryLowStock => 'Alerta diaria de stock bajo',
            self::TelemedicineCaseReversal => 'Alerta de reverso de caso',
            self::BirthdayNotificationWitnessCopy => 'Copia testigo de cada envío',
            self::BirthdayNotificationSummary => 'Resumen de la corrida diaria',
            self::TdevRegistration => 'Alertas de registros TDEV',
            self::CompanyAssociateIlsCoverage => 'Confirmación de cobertura del asociado',
            self::TelemedicineServiceLimitOverride => 'Clave OTP fuera de límite clínico',
            self::StorefrontQuotePaymentReceipt => 'Comprobante de cotización PWA',
            self::CorporatePaymentFrequencyChange => 'Cambio de frecuencia de pago',
            self::LiveSecurityAlert => 'Alertas de seguridad en vivo',
            self::LiveSystemAlert => 'Alertas de colas y errores',
            self::OperationsAffiliateUpdate => 'Aviso de afiliados actualizados por Operaciones',
            self::EarlyRenovationAcceptance => 'Aviso de renovaciones aceptadas antes de tiempo',
            self::CorporatePopulationUpdate => 'Clave OTP para actualizar población corporativa',
            self::TelemedicineCaseAgentNotice => 'Aviso de caso nuevo al agente',
            self::SaleReceiptWitnessCopy => 'Copia testigo de cada recibo de pago',
            self::CollectionNoticeWitnessCopy => 'Copia testigo de cada aviso de cobro',
            self::TelegramTest => 'Prueba de notificación a Telegram',
            self::ScheduledTaskSummary => 'Resúmenes de tareas programadas',
            self::ClinicalUsageAccessOtp => 'Clave OTP de acceso a uso clínico',
            self::PersonalAssignments => 'Asignaciones e invitaciones personales',
            self::DoctorCaseAssignment => 'Caso asignado al médico',
            self::HelpdeskNotices => 'Avisos del Helpdesk',
            self::ContadoQuotePayment => 'Pagos de contado urgentes',
            self::CourtesyFinance => 'Servicios por cortesía',
            self::NewQuoteAnalysts => 'Aviso de nuevas cotizaciones',
            self::CommercialNetworkActivity => 'Movimientos de la red comercial',
            self::DressTaylorRequest => 'Solicitudes Dress Taylor',
            self::GuiaChatFeedback => 'Feedback de la Guía Chat',
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
            self::OperationInventoryLowStock => 'Cada vez que un producto activo quede con existencia total menor o igual al umbral se envía una alerta inmediata por correo y WhatsApp. Además, cada día a las 8:00 se reenvía el listado completo mientras sigan bajo el umbral (configurable en Parámetros de Inventario).',
            self::TelemedicineCaseReversal => 'Cuando un médico reversa un caso, el sistema elimina el caso para permitir la reasignación y dispara de forma asíncrona un correo y un WhatsApp detallados, resaltando la nota/observación del médico.',
            self::BirthdayNotificationWitnessCopy => 'Por cada tarjeta enviada al cumpleañero, estos contactos reciben una copia: WhatsApp duplicado (con demora) y CC en el correo. Sirve para auditar el contenido enviado, no el resultado de la tarea.',
            self::BirthdayNotificationSummary => 'Al terminar la corrida de las 8:00, estos contactos reciben el resumen operativo (validaciones, envíos encolados, fallas y tarjetas aprobadas). No reciben la tarjeta de cada persona.',
            self::TdevRegistration => 'Cada registro público de agencia nivel 3, agente de agencia nivel 3 o agente freelance de agencia nivel 2 dispara correo y WhatsApp con el detalle completo y branding TDEV.',
            self::CompanyAssociateIlsCoverage => 'Cuando el analista guarda el voucher ILS de un asociado, el sistema le pide declarar que completó toda la gestión que garantiza la cobertura. Al confirmar se envía correo y WhatsApp con los datos del asociado, el número de voucher, la vigencia desde-hasta y el documento del voucher adjunto en ambos canales.',
            self::TelemedicineServiceLimitOverride => 'Si el médico confirma que quiere continuar con un servicio agotado, el sistema envía en el acto (WhatsApp y correo) una clave de 6 dígitos con 5 minutos de vida. Sin destinatarios o con la alerta pausada no hay excepción.',
            self::StorefrontQuotePaymentReceipt => 'Cuando el cliente adjunta el comprobante en la app, el sistema encola correo y WhatsApp a Administración con el número de cotización, el usuario que lo cargó y el canal PWA. El archivo viaja adjunto.',
            self::CorporatePaymentFrequencyChange => 'Cuando un analista cambia la frecuencia de pago de una afiliación corporativa, el sistema anula los avisos de cobro pendientes, genera los nuevos y avisa a Administración por WhatsApp, correo (con la lista de avisos cancelados y creados) y notificación del panel. El reverso se avisa igual.',
            self::LiveSecurityAlert => 'El monitor en vivo vigila logins fallidos, escáneres, bots y ráfagas de peticiones. Cuando detecta algo crítico avisa a estos contactos y a los usuarios del monitor, una vez por tipo de ataque cada 30 minutos.',
            self::LiveSystemAlert => 'Cada minuto un vigilante revisa las colas, los trabajos fallidos y los errores del sistema. Si algo necesita atención avisa a estos contactos y a los usuarios del monitor, sin depender de la cola (funciona aunque los workers estén caídos), una vez por problema cada 30 minutos.',
            self::OperationsAffiliateUpdate => 'Cuando un analista de Operaciones guarda cambios en los datos personales de un afiliado individual o corporativo —desde «Editar» o al fijar la dirección con el mapa—, el sistema encola un correo y un WhatsApp con cada campo modificado (antes → después), el analista, la fecha y si también se actualizó el paciente de telemedicina.',
            self::EarlyRenovationAcceptance => 'El período de renovación se abre a 30 días de la fecha de renovación. Un analista con el permiso «Renovar antes del período de renovación» puede aceptar antes, con un motivo obligatorio. Cada vez que lo hace se encola un WhatsApp y un correo con la afiliación, los días que faltaban, el plan aplicado, quién lo autorizó y el motivo.',
            self::CorporatePopulationUpdate => 'El analista sube el archivo de población del corporativo, el sistema lo compara con los afiliados y arma un resumen. Al aprobar (o revertir) la actualización, el sistema envía en el acto (WhatsApp y correo) una clave de 6 dígitos con 5 minutos de vida a estos contactos. Sin destinatarios o con la alerta pausada no se puede aplicar ni revertir.',
            self::TelemedicineCaseAgentNotice => 'Cuando se registra un caso nuevo para un paciente —desde Operaciones al asignar médico, por registro directo o RETAIL—, el sistema le envía un WhatsApp al agente dueño de su afiliación (o a la agencia, si la afiliación no tiene agente) con los datos del paciente y el número de caso. Se envía una sola vez por caso: los seguimientos no lo repiten.',
            self::SaleReceiptWitnessCopy => 'Cuando un analista envía un recibo de pago al afiliado y/o al agente / agencia, cada correo sale con estos contactos en copia visible (CC) o en copia oculta (CCO), según lo marcado en cada correo. Los teléfonos reciben por WhatsApp el PDF y el detalle de a quién se envió y con qué copy. Si el recibo se envía por WhatsApp, estos teléfonos reciben la copia del mensaje.',
            self::CollectionNoticeWitnessCopy => 'Cuando un analista envía un aviso de cobro al afiliado y/o al agente / agencia, cada correo sale con estos contactos en copia visible (CC) o en copia oculta (CCO), según lo marcado en cada correo. Los teléfonos reciben por WhatsApp el PDF del aviso y el detalle de a quién se envió y con qué copy.',
            self::TelegramTest => 'Elija a quién va la prueba (persona, grupo o ambos), guarde y pulse «Enviar prueba». Las personas con Telegram vinculado la reciben allí; las que no, por WhatsApp o correo. Cada envío queda en la bitácora de la página Telegram.',
            self::ScheduledTaskSummary => 'Al terminar cada tarea programada que no tiene aviso propio, el sistema envía un resumen con lo procesado, las fallas y la duración. Antes iba a dos teléfonos fijos de la configuración del servidor; ahora se eligen aquí.',
            self::ClinicalUsageAccessOtp => 'Cuando un analista que no es SUPERADMIN entra a la mesa de uso clínico, el sistema envía una clave de 6 dígitos a todos los SUPERADMIN activos (a su correo y su teléfono de usuario). Con Telegram en «Persona», los SUPERADMIN vinculados la reciben allí al instante.',
            self::PersonalAssignments => 'Cada aviso va a la persona que corresponde (el colaborador asignado, el invitado o quien creó la actividad), no a una lista. Con «Persona», quien tenga Telegram vinculado lo recibe allí; los demás, por WhatsApp como siempre. El correo y la campanita no cambian.',
            self::DoctorCaseAssignment => 'Cuando Operaciones asigna un caso, el médico recibe el código, el paciente y la dirección para atenderlo. Con «Persona», si el médico tiene Telegram vinculado le llega allí, sin el motivo de consulta; si no, por WhatsApp como siempre. El correo con el detalle no cambia.',
            self::HelpdeskNotices => 'Cada aviso de un ticket va a quien corresponde: los asignados, el creador o el equipo de ejecución. Con «Persona», quien tenga Telegram vinculado lo recibe allí; los demás, por WhatsApp como siempre. Los correos del Helpdesk no cambian.',
            self::ContadoQuotePayment => 'Cuando una cotización de proveedor se aprueba con pago de contado, Administración debe pagar ya. Estos contactos reciben el detalle con montos y tasa. Antes iba a dos números fijos del sistema; ahora se eligen aquí.',
            self::CourtesyFinance => 'Cuando un servicio se da por cortesía, Administración debe registrarlo sin cobrarlo. Estos contactos reciben el detalle. Antes iba a dos números fijos del sistema; ahora se eligen aquí.',
            self::NewQuoteAnalysts => 'Cuando un agente o una agencia registra una cotización individual o corporativa, estos contactos reciben el aviso para acompañar al agente hasta la pre-afiliación. La propuesta en PDF llega siempre por WhatsApp. Antes iba a números fijos del sistema; ahora se eligen aquí.',
            self::CommercialNetworkActivity => 'Agrupa los avisos de la red comercial que antes iban a un número fijo de cotizaciones y afiliaciones: data corporativa cargada, comprobante de pago cargado por el agente, pre-afiliación individual creada y observaciones en cotizaciones individuales y corporativas.',
            self::DressTaylorRequest => 'Cuando un agente crea una solicitud Dress Taylor desde su panel, estos contactos reciben el código y las características pedidas para comunicarse con el agente. Antes iba a un número fijo.',
            self::GuiaChatFeedback => 'Cada vez que un visitante deja un comentario o reporta un problema en la Guía Chat, estos contactos reciben el detalle. Antes iba a tres números fijos del sistema; el correo a soporte sigue igual.',
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
            self::OperationInventoryLowStock => [
                '1. Baja de existencia',
                '2. Cruce de umbral',
                '3. Alerta inmediata async',
                '4. Resumen diario 8:00',
            ],
            self::TelemedicineCaseReversal => [
                '1. Médico reversa caso',
                '2. Nota obligatoria',
                '3. Eliminar caso',
                '4. Email + WhatsApp async',
            ],
            self::BirthdayNotificationWitnessCopy => [
                '1. Cumpleaños hoy',
                '2. Envío al destinatario',
                '3. Copia WhatsApp testigo',
                '4. CC en correo',
            ],
            self::BirthdayNotificationSummary => [
                '1. Cron 8:00',
                '2. Procesar tarjetas',
                '3. Armar resumen',
                '4. Email + WhatsApp',
            ],
            self::TdevRegistration => [
                '1. Formulario público TDEV',
                '2. Cola asíncrona',
                '3. Email + WhatsApp',
                '4. Revisar en AGENCIAS TDEV',
            ],
            self::CompanyAssociateIlsCoverage => [
                '1. Analista guarda voucher',
                '2. Confirma la gestión',
                '3. Cola asíncrona',
                '4. Email + WhatsApp con voucher',
            ],
            self::TelemedicineServiceLimitOverride => [
                '1. Cupo clínico agotado',
                '2. Médico confirma y escribe motivo',
                '3. OTP 6 dígitos / 5 min',
                '4. Médico ingresa la clave',
            ],
            self::StorefrontQuotePaymentReceipt => [
                '1. Cliente carga comprobante',
                '2. Guardado en la cotización',
                '3. Cola asíncrona',
                '4. Email + WhatsApp a Administración',
            ],
            self::CorporatePaymentFrequencyChange => [
                '1. Negocios cambia la frecuencia',
                '2. Avisos pendientes anulados y reemplazados',
                '3. Registro con el estado anterior',
                '4. WhatsApp + correo + panel a Administración',
            ],
            self::LiveSecurityAlert => [
                '1. Petición o login fallido',
                '2. Contadores por ventana en Redis',
                '3. Umbral crítico superado',
                '4. WhatsApp + correo con pausa',
            ],
            self::LiveSystemAlert => [
                '1. Vigilante cada minuto',
                '2. Colas, fallidos y errores',
                '3. Problema nuevo detectado',
                '4. WhatsApp + correo sin pasar por la cola',
            ],
            self::OperationsAffiliateUpdate => [
                '1. Operaciones edita el afiliado',
                '2. Se guardan solo los datos personales',
                '3. Cola asíncrona',
                '4. Email + WhatsApp con el detalle',
            ],
            self::EarlyRenovationAcceptance => [
                '1. Analista con permiso acepta antes de tiempo',
                '2. Confirma y escribe el motivo',
                '3. Histórico marcado como anticipada',
                '4. WhatsApp + correo a SUPERADMIN',
            ],
            self::CorporatePopulationUpdate => [
                '1. Analista sube el archivo del cliente',
                '2. El sistema arma el resumen',
                '3. OTP 6 dígitos / 5 min',
                '4. Se aplica o se revierte',
            ],
            self::TelemedicineCaseAgentNotice => [
                '1. Se registra un caso nuevo',
                '2. Se busca el agente de la afiliación',
                '3. Cola asíncrona, una sola vez',
                '4. WhatsApp al agente + copias',
            ],
            self::SaleReceiptWitnessCopy => [
                '1. El analista elige los copys',
                '2. Correo al afiliado y al agente / agencia',
                '3. Copia CC o CCO a los testigos',
                '4. WhatsApp testigo con el PDF',
            ],
            self::CollectionNoticeWitnessCopy => [
                '1. El analista regenera el aviso y elige los copys',
                '2. Correo al afiliado y al agente / agencia',
                '3. Copia CC o CCO a los testigos',
                '4. WhatsApp testigo con el PDF',
            ],
            self::TelegramTest => [
                '1. Elegir persona, grupo o ambos',
                '2. Guardar',
                '3. Enviar prueba',
                '4. Revisar la bitácora',
            ],
            self::ScheduledTaskSummary => [
                '1. Termina una tarea programada',
                '2. Se arma el resumen',
                '3. Telegram o WhatsApp',
                '4. Correo si se configura',
            ],
            self::ClinicalUsageAccessOtp => [
                '1. Analista entra a uso clínico',
                '2. Clave a todos los SUPERADMIN',
                '3. Telegram o WhatsApp + correo',
                '4. El SUPERADMIN la dicta',
            ],
            self::PersonalAssignments => [
                '1. Se asigna una tarea o se invita',
                '2. Se busca a la persona',
                '3. Telegram si está vinculada',
                '4. Si no, WhatsApp como siempre',
            ],
            self::DoctorCaseAssignment => [
                '1. Operaciones asigna el caso',
                '2. Correo con el detalle',
                '3. Telegram si el médico está vinculado',
                '4. Si no, WhatsApp como siempre',
            ],
            self::HelpdeskNotices => [
                '1. Movimiento en un ticket',
                '2. Se busca a cada persona',
                '3. Telegram si está vinculada',
                '4. Si no, WhatsApp como siempre',
            ],
            self::ContadoQuotePayment => [
                '1. Operaciones aprueba de contado',
                '2. Correo a Administración',
                '3. Aviso por Telegram o WhatsApp',
                '4. Administración paga',
            ],
            self::CourtesyFinance => [
                '1. Operaciones marca cortesía',
                '2. Correo a Administración',
                '3. Aviso por Telegram o WhatsApp',
                '4. Se registra sin cobro',
            ],
            self::NewQuoteAnalysts => [
                '1. El agente crea la cotización',
                '2. Aviso al equipo de análisis',
                '3. PDF de la propuesta por WhatsApp',
                '4. Acompañar hasta la pre-afiliación',
            ],
            self::CommercialNetworkActivity => [
                '1. Movimiento en la red comercial',
                '2. Aviso al equipo',
                '3. Telegram o WhatsApp',
                '4. Revisar en el panel',
            ],
            self::DressTaylorRequest => [
                '1. El agente pide Dress Taylor',
                '2. Aviso con las características',
                '3. Telegram o WhatsApp',
                '4. Contactar al agente',
            ],
            self::GuiaChatFeedback => [
                '1. Visitante deja feedback',
                '2. Correo a soporte',
                '3. Aviso por Telegram o WhatsApp',
                '4. Revisar en Guía Chat',
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
            self::OperationInventoryLowStock => 'Criterio de la alerta:',
            self::TelemedicineCaseReversal => 'Acción requerida:',
            self::BirthdayNotificationWitnessCopy => 'Copia testigo:',
            self::BirthdayNotificationSummary => 'Resumen de ejecución:',
            self::TdevRegistration => 'Acción requerida:',
            self::CompanyAssociateIlsCoverage => 'Declaración del analista:',
            self::TelemedicineServiceLimitOverride => 'Autorización excepcional:',
            self::StorefrontQuotePaymentReceipt => 'Acción requerida para Administración:',
            self::CorporatePaymentFrequencyChange => 'Acción requerida para Administración:',
            self::LiveSecurityAlert => 'Acción requerida:',
            self::LiveSystemAlert => 'Acción requerida:',
            self::OperationsAffiliateUpdate => 'Acción requerida para Afiliaciones:',
            self::EarlyRenovationAcceptance => 'Control de excepciones:',
            self::CorporatePopulationUpdate => 'Cambio masivo de población:',
            self::TelemedicineCaseAgentNotice => 'Privacidad del paciente:',
            self::SaleReceiptWitnessCopy => 'CC o CCO:',
            self::CollectionNoticeWitnessCopy => 'CC o CCO:',
            self::TelegramTest => 'Cómo leer el resultado:',
            self::ScheduledTaskSummary => 'Para qué sirve:',
            self::ClinicalUsageAccessOtp => 'Quién la recibe:',
            self::PersonalAssignments => 'Quién lo recibe:',
            self::DoctorCaseAssignment => 'Datos clínicos:',
            self::HelpdeskNotices => 'Quién lo recibe:',
            self::ContadoQuotePayment => 'Antes:',
            self::CourtesyFinance => 'Antes:',
            self::NewQuoteAnalysts => 'Antes:',
            self::CommercialNetworkActivity => 'Antes:',
            self::DressTaylorRequest => 'Antes:',
            self::GuiaChatFeedback => 'Antes:',
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
            self::OperationInventoryLowStock => 'solo se incluyen productos activos cuya existencia total (suma de todos los almacenes) sea menor o igual al umbral. Si no hay productos bajo umbral, no se envía notificación. El umbral se configura en Operaciones → Parámetros de Inventario.',
            self::TelemedicineCaseReversal => 'al recibir la alerta debe reasignar el paciente desde Operaciones (TDG o proveedor). El caso ya fue eliminado; revise con atención la nota del médico.',
            self::BirthdayNotificationWitnessCopy => 'estos contactos reciben una copia de cada tarjeta enviada (WhatsApp y/o CC en email). El resumen consolidado de la corrida se configura en la pestaña «Resumen cumpleaños».',
            self::BirthdayNotificationSummary => 'estos contactos reciben solo el resumen de la corrida (totales y fallas). Las copias de cada tarjeta se configuran en la pestaña «Copia testigo cumpleaños».',
            self::TdevRegistration => 'al recibir la alerta revise INTEGRACORP → Estructura comercial → AGENCIAS TDEV para validar el registro (agencia nivel 3 o agente) y dar seguimiento comercial.',
            self::CompanyAssociateIlsCoverage => 'el aviso solo se envía cuando el analista confirma expresamente que realizó toda la gestión que garantiza la cobertura del cliente. Quién confirmó y cuándo queda registrado en las trazas de seguridad.',
            self::TelemedicineServiceLimitOverride => 'dicte la clave al médico por un canal interno. La clave vence en 5 minutos, es de un solo uso y no debe reenviarse al paciente. Sin esta clave el sistema no asigna el servicio extra.',
            self::StorefrontQuotePaymentReceipt => 'revise el comprobante adjunto, concilie el pago y dé seguimiento a la cotización. El teléfono configurado aquí también es el que usa el cliente en la PWA para escribirle a Administración.',
            self::CorporatePaymentFrequencyChange => 'revise el cambio en Administración → Cambios de frecuencia de pago y márquelo como validado. Si no está de acuerdo o hubo una falla, desde allí puede revertirlo: la afiliación y su cobranza vuelven exactamente al estado anterior.',
            self::LiveSecurityAlert => 'abra Negocios → Monitor en vivo para ver la IP, las cuentas atacadas y el detalle. Las cuentas con muchos fallos se bloquean solas por unos minutos; desde el monitor puede desbloquearlas o bloquear a un usuario.',
            self::LiveSystemAlert => 'abra Negocios → Colas y errores: cada problema trae su diagnóstico, la línea de código donde ocurrió y la acción recomendada (reintentar, eliminar o corregir).',
            self::OperationsAffiliateUpdate => 'revise cada cambio contra el expediente de la afiliación. Si la fecha de nacimiento dejó la edad fuera del rango tarifario, el aviso lo marca: la tarifa no se recalcula sola y debe revisarla con Negocios.',
            self::EarlyRenovationAcceptance => 'revise el motivo y el histórico de la renovación (Negocios → Histórico de renovaciones, filtro «Anticipadas»). La nueva vigencia parte de la fecha de renovación original; renovar antes no adelanta el aniversario. El permiso se asigna por usuario en Negocios y en Administración.',
            self::CorporatePopulationUpdate => 'dicte la clave solo al analista que está aplicando o revirtiendo la carga. Vence en 5 minutos y es de un solo uso. Sin esta clave no se agregan, reactivan ni dan de baja afiliados.',
            self::TelemedicineCaseAgentNotice => 'el mensaje lleva solo datos de identificación del paciente, de su afiliación y del caso. No incluye motivo de consulta, diagnóstico ni ningún dato clínico.',
            self::SaleReceiptWitnessCopy => 'con CC el afiliado o el agente ve el correo del testigo; con CCO el testigo recibe el mismo correo sin que nadie más lo vea. El modo prueba nunca copia a los testigos.',
            self::CollectionNoticeWitnessCopy => 'con CC el afiliado o el agente ve el correo del testigo; con CCO el testigo recibe el mismo correo sin que nadie más lo vea. El modo prueba nunca copia a los testigos.',
            self::TelegramTest => 'si una persona no tiene Telegram vinculado o el bot falla, la prueba le llega por WhatsApp (o por correo si no tiene teléfono). Así también se comprueba el respaldo. Revise cada envío en CONFIGURACIÓN → Telegram.',
            self::ScheduledTaskSummary => 'confirma cada mañana que las tareas automáticas corrieron. Si un día no llega el resumen de una tarea, revise Negocios → Colas y errores.',
            self::ClinicalUsageAccessOtp => 'todos los usuarios activos con SUPERADMIN, sin lista aparte. Dicte la clave solo al analista que la pidió. Una clave nunca va a un grupo de Telegram.',
            self::PersonalAssignments => 'siempre la persona del aviso; no hace falta cargar contactos. Estos avisos nunca van a un grupo.',
            self::DoctorCaseAssignment => 'el motivo de consulta nunca viaja por Telegram. El médico lo ve en el correo y en el portal de Telemedicina.',
            self::HelpdeskNotices => 'siempre las personas del ticket; no hace falta cargar contactos. Estos avisos nunca van a un grupo.',
            self::ContadoQuotePayment => 'iba a dos números fijos escritos en el sistema. Este aviso arrancó con esos mismos números: agregue o quite contactos sin pedir cambios a Sistemas.',
            self::CourtesyFinance => 'iba a dos números fijos escritos en el sistema. Este aviso arrancó con esos mismos números: agregue o quite contactos sin pedir cambios a Sistemas.',
            self::NewQuoteAnalysts => 'los números estaban escritos en el sistema. Este aviso arrancó con esos mismos números: agregue o quite contactos sin pedir cambios a Sistemas.',
            self::CommercialNetworkActivity => 'iban al número fijo de cotizaciones y afiliaciones. Este aviso arrancó con ese número: agregue o quite contactos sin pedir cambios a Sistemas.',
            self::DressTaylorRequest => 'iba a un número fijo escrito en el sistema. Este aviso arrancó con ese número.',
            self::GuiaChatFeedback => 'iba a tres números fijos escritos en el sistema. Este aviso arrancó con esos mismos números.',
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
            self::OperationInventoryLowStock => 'heroicon-o-cube',
            self::TelemedicineCaseReversal => 'heroicon-o-arrow-uturn-left',
            self::BirthdayNotificationWitnessCopy => 'heroicon-o-document-duplicate',
            self::BirthdayNotificationSummary => 'heroicon-o-chart-bar',
            self::TdevRegistration => 'heroicon-o-building-office-2',
            self::CompanyAssociateIlsCoverage => 'heroicon-o-shield-check',
            self::TelemedicineServiceLimitOverride => 'heroicon-o-key',
            self::StorefrontQuotePaymentReceipt => 'heroicon-o-banknotes',
            self::CorporatePaymentFrequencyChange => 'heroicon-o-arrows-right-left',
            self::LiveSecurityAlert => 'heroicon-o-shield-exclamation',
            self::LiveSystemAlert => 'heroicon-o-queue-list',
            self::OperationsAffiliateUpdate => 'heroicon-o-user-circle',
            self::EarlyRenovationAcceptance => 'heroicon-o-forward',
            self::CorporatePopulationUpdate => 'heroicon-o-user-group',
            self::TelemedicineCaseAgentNotice => 'heroicon-o-user-plus',
            self::SaleReceiptWitnessCopy => 'heroicon-o-document-duplicate',
            self::CollectionNoticeWitnessCopy => 'heroicon-o-document-duplicate',
            self::TelegramTest => 'heroicon-o-paper-airplane',
            self::ScheduledTaskSummary => 'heroicon-o-clock',
            self::ClinicalUsageAccessOtp => 'heroicon-o-key',
            self::PersonalAssignments => 'heroicon-o-user',
            self::DoctorCaseAssignment => 'heroicon-o-user-plus',
            self::HelpdeskNotices => 'heroicon-o-lifebuoy',
            self::ContadoQuotePayment => 'heroicon-o-banknotes',
            self::CourtesyFinance => 'heroicon-o-gift',
            self::NewQuoteAnalysts => 'heroicon-o-document-text',
            self::CommercialNetworkActivity => 'heroicon-o-arrows-right-left',
            self::DressTaylorRequest => 'heroicon-o-scissors',
            self::GuiaChatFeedback => 'heroicon-o-chat-bubble-left-right',
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
            self::OperationInventoryLowStock => [],
            self::TelemedicineCaseReversal => [],
            self::BirthdayNotificationWitnessCopy => [
                'solrodriguez@tudrencasa.com',
            ],
            self::BirthdayNotificationSummary => [],
            self::TdevRegistration => [],
            self::CompanyAssociateIlsCoverage => [],
            self::TelemedicineServiceLimitOverride => [],
            self::StorefrontQuotePaymentReceipt => [],
            self::CorporatePaymentFrequencyChange => [],
            self::LiveSecurityAlert => [],
            self::LiveSystemAlert => [],
            self::OperationsAffiliateUpdate => [],
            self::EarlyRenovationAcceptance => [],
            self::CorporatePopulationUpdate => [],
            self::TelemedicineCaseAgentNotice => [],
            self::SaleReceiptWitnessCopy => [
                'afiliaciones@tudrencasa.com',
                'administracion@tudrencasa.com',
                'solrodriguez@tudrencasa.com',
                'hsanchez@tudrencasa.com',
            ],
            self::CollectionNoticeWitnessCopy => [
                'administracion@tudrencasa.com',
            ],
            self::TelegramTest => [],
            self::ScheduledTaskSummary => [],
            self::ClinicalUsageAccessOtp => [],
            self::PersonalAssignments => [],
            self::DoctorCaseAssignment => [],
            self::HelpdeskNotices => [],
            self::ContadoQuotePayment => [],
            self::CourtesyFinance => [],
            self::NewQuoteAnalysts => [],
            self::CommercialNetworkActivity => [],
            self::DressTaylorRequest => [],
            self::GuiaChatFeedback => [],
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
            self::OperationInventoryLowStock => [],
            self::TelemedicineCaseReversal => [],
            self::BirthdayNotificationWitnessCopy => [
                '04143027250',
            ],
            self::BirthdayNotificationSummary => [
                '04127018390',
                '04143027250',
            ],
            self::TdevRegistration => [],
            self::CompanyAssociateIlsCoverage => [],
            self::TelemedicineServiceLimitOverride => [],
            self::StorefrontQuotePaymentReceipt => [],
            self::CorporatePaymentFrequencyChange => [],
            self::LiveSecurityAlert => [],
            self::LiveSystemAlert => [],
            self::OperationsAffiliateUpdate => [],
            self::EarlyRenovationAcceptance => [],
            self::CorporatePopulationUpdate => [],
            self::TelemedicineCaseAgentNotice => [],
            self::SaleReceiptWitnessCopy => [
                '04143027250',
            ],
            self::CollectionNoticeWitnessCopy => [],
            self::TelegramTest => [],
            self::ScheduledTaskSummary => ScheduledNotificationPhones::all(),
            self::ClinicalUsageAccessOtp => [],
            self::PersonalAssignments => [],
            self::DoctorCaseAssignment => [],
            self::HelpdeskNotices => [],
            self::ContadoQuotePayment => ['04242875732', '04143027250'],
            self::CourtesyFinance => ['04242875732', '04143027250'],
            self::NewQuoteAnalysts => self::phonesWithQuotesLine(ScheduledNotificationPhones::all()),
            self::CommercialNetworkActivity => self::phonesWithQuotesLine([]),
            self::DressTaylorRequest => ['+584241869168'],
            self::GuiaChatFeedback => ['04127018390', '04121931865', '04143027250'],
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
            self::OperationInventoryLowStock => 'Sin destinatarios configurados. El cron revisará el stock, pero no se enviará la alerta diaria.',
            self::TelemedicineCaseReversal => 'Sin destinatarios configurados. El médico podrá reversar el caso, pero no se enviará la alerta por correo ni WhatsApp.',
            self::BirthdayNotificationWitnessCopy => 'Sin copias testigo. Las tarjetas seguirán enviándose a los cumpleañeros, pero no habrá copia WhatsApp ni CC en el correo.',
            self::BirthdayNotificationSummary => 'Sin destinatarios de resumen. Las tarjetas se enviarán igual, pero no se notificará el resultado de la corrida.',
            self::TdevRegistration => 'Aún no hay destinatarios configurados. Agregue al menos un correo o un teléfono para activar las alertas de registros TDEV.',
            self::CompanyAssociateIlsCoverage => 'Aún no hay destinatarios configurados. El analista podrá confirmar la cobertura, pero no se enviará el aviso con el voucher.',
            self::TelemedicineServiceLimitOverride => 'Aún no hay destinatarios. El médico verá el cupo agotado, pero no podrá pedir autorización fuera de límite hasta que agregue al menos un correo o un teléfono.',
            self::StorefrontQuotePaymentReceipt => 'Aún no hay destinatarios de Administración. El cliente podrá cargar el comprobante, pero no se enviará el aviso ni habrá WhatsApp de contacto en la PWA.',
            self::CorporatePaymentFrequencyChange => 'Sin contactos del departamento configurados. Igual se avisará por correo, WhatsApp y panel a los usuarios activos de Administración.',
            self::LiveSecurityAlert => 'Sin contactos adicionales. Igual se avisará a los usuarios con acceso al monitor en vivo.',
            self::LiveSystemAlert => 'Sin contactos adicionales. Igual se avisará a los usuarios con acceso al monitor en vivo.',
            self::OperationsAffiliateUpdate => 'Aún no hay destinatarios. Operaciones podrá actualizar los datos del afiliado, pero el equipo de Afiliaciones no recibirá el aviso.',
            self::EarlyRenovationAcceptance => 'Sin contactos adicionales. Igual se avisará por correo y WhatsApp a los usuarios activos con SUPERADMIN.',
            self::CorporatePopulationUpdate => 'Aún no hay destinatarios. El analista podrá analizar archivos, pero no podrá aplicar ni revertir una actualización hasta que agregue al menos un correo o un teléfono.',
            self::TelemedicineCaseAgentNotice => 'Sin copias. El aviso igual le llega al agente o a la agencia dueña de la afiliación.',
            self::SaleReceiptWitnessCopy => 'Sin testigos. Los recibos se envían igual al afiliado y al agente / agencia, sin copias internas.',
            self::CollectionNoticeWitnessCopy => 'Sin testigos. Los avisos de cobro se envían igual al afiliado y al agente / agencia, sin copias internas.',
            self::TelegramTest => 'Sin contactos. Puede enviar la prueba solo a grupos de Telegram, o agregar correos y teléfonos de personas.',
            self::ScheduledTaskSummary => 'Sin destinatarios. Las tareas programadas corren igual, pero nadie recibe su resumen.',
            self::ClinicalUsageAccessOtp => 'La clave va siempre a los SUPERADMIN activos: no hace falta agregar contactos.',
            self::PersonalAssignments => 'Cada aviso va a la persona que corresponde: no hace falta agregar contactos.',
            self::DoctorCaseAssignment => 'El aviso va siempre al médico asignado: no hace falta agregar contactos.',
            self::HelpdeskNotices => 'Cada aviso va a las personas del ticket: no hace falta agregar contactos.',
            self::ContadoQuotePayment => 'Sin destinatarios. El aviso no le llega a nadie por WhatsApp ni Telegram (el correo a Administración sale igual).',
            self::CourtesyFinance => 'Sin destinatarios. El aviso no le llega a nadie por WhatsApp ni Telegram (el correo a Administración sale igual).',
            self::NewQuoteAnalysts => 'Sin destinatarios. Los avisos no le llegan a nadie hasta que agregue al menos un teléfono.',
            self::CommercialNetworkActivity => 'Sin destinatarios. Los avisos no le llegan a nadie hasta que agregue al menos un teléfono.',
            self::DressTaylorRequest => 'Sin destinatarios. Los avisos no le llegan a nadie hasta que agregue al menos un teléfono.',
            self::GuiaChatFeedback => 'Sin destinatarios. Los avisos no le llegan a nadie hasta que agregue al menos un teléfono.',
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
            self::OperationInventoryLowStock => $empty
                ? 'Sin destinatarios. El cron revisará el stock, pero no se enviará la alerta.'
                : 'La alerta diaria de stock bajo se enviará a los contactos configurados cuando haya productos bajo el umbral.',
            self::TelemedicineCaseReversal => $empty
                ? 'Sin destinatarios. El reverso del caso seguirá eliminándolo, pero no se enviará la alerta.'
                : 'La alerta de reverso de caso se enviará por correo y WhatsApp a los contactos configurados.',
            self::BirthdayNotificationWitnessCopy => $empty
                ? 'Sin copias testigo. Las tarjetas seguirán enviándose a los cumpleañeros.'
                : 'Cada tarjeta enviada generará copia WhatsApp y/o CC en correo para los contactos configurados.',
            self::BirthdayNotificationSummary => $empty
                ? 'Sin destinatarios. El resumen de la corrida no se enviará.'
                : 'El resumen de ejecución de tarjetas de cumpleaños se enviará a los contactos configurados.',
            self::TdevRegistration => $empty
                ? 'No hay destinatarios activos. Las notificaciones de registros TDEV quedarán en pausa hasta que agregue contactos.'
                : 'Se notificará por correo y WhatsApp a los contactos configurados cuando haya un registro TDEV público.',
            self::CompanyAssociateIlsCoverage => $empty
                ? 'No hay destinatarios activos. El voucher se guardará igual, pero nadie recibirá la confirmación de cobertura.'
                : 'Se notificará por correo y WhatsApp, con el voucher adjunto, a los contactos configurados cuando el analista confirme la cobertura.',
            self::TelemedicineServiceLimitOverride => $empty
                ? 'No hay destinatarios. El médico no podrá autorizar un servicio fuera de límite.'
                : 'La clave OTP se enviará por correo y WhatsApp a estos contactos cuando un médico pida un servicio extra.',
            self::StorefrontQuotePaymentReceipt => $empty
                ? 'No hay destinatarios de Administración. El comprobante se guardará, pero nadie recibirá el aviso.'
                : 'Se notificará por correo y WhatsApp a Administración cuando un cliente cargue el comprobante desde la PWA.',
            self::CorporatePaymentFrequencyChange => $empty
                ? 'Sin contactos del departamento. Se avisará igual a los usuarios activos de Administración.'
                : 'Se avisará a estos contactos y a los usuarios activos de Administración en cada cambio de frecuencia y en cada reverso.',
            self::LiveSecurityAlert => $empty
                ? 'Sin contactos adicionales. Se avisará solo a los usuarios del monitor en vivo.'
                : 'Estos contactos y los usuarios del monitor recibirán las alertas de seguridad críticas.',
            self::LiveSystemAlert => $empty
                ? 'Sin contactos adicionales. Se avisará solo a los usuarios del monitor en vivo.'
                : 'Estos contactos y los usuarios del monitor recibirán las alertas de colas y errores.',
            self::OperationsAffiliateUpdate => $empty
                ? 'Sin destinatarios. Los cambios de Operaciones se guardarán, pero Afiliaciones no recibirá el aviso.'
                : 'Afiliaciones recibirá por correo y WhatsApp el detalle de cada actualización que haga Operaciones.',
            self::EarlyRenovationAcceptance => $empty
                ? 'Sin contactos adicionales. Se avisará solo a los usuarios activos con SUPERADMIN.'
                : 'Estos contactos y los usuarios activos con SUPERADMIN recibirán el aviso de cada renovación anticipada.',
            self::CorporatePopulationUpdate => $empty
                ? 'No hay destinatarios. Nadie podrá aplicar ni revertir actualizaciones de población.'
                : 'La clave OTP se enviará por correo y WhatsApp a estos contactos cada vez que se aplique o revierta una actualización de población.',
            self::TelemedicineCaseAgentNotice => $empty
                ? 'Sin copias. El aviso de cada caso nuevo seguirá llegando al agente o a la agencia de la afiliación.'
                : 'Estos números recibirán una copia del aviso que llega al agente o a la agencia por cada caso nuevo.',
            self::SaleReceiptWitnessCopy => $empty
                ? 'Sin testigos. Los recibos de pago saldrán sin copias internas.'
                : 'Cada recibo de pago enviado llegará a estos testigos: por correo en CC o CCO y por WhatsApp con el PDF.',
            self::CollectionNoticeWitnessCopy => $empty
                ? 'Sin testigos. Los avisos de cobro saldrán sin copias internas.'
                : 'Cada aviso de cobro enviado llegará a estos testigos: por correo en CC o CCO y por WhatsApp con el PDF.',
            self::TelegramTest => $empty
                ? 'Sin contactos. La prueba solo llegará a los grupos de Telegram elegidos.'
                : 'La prueba llegará a estos contactos: por Telegram a los vinculados y por WhatsApp o correo a los demás.',
            self::ScheduledTaskSummary => $empty
                ? 'Sin destinatarios. Las tareas corren igual, pero nadie recibe su resumen.'
                : 'Estos contactos recibirán el resumen de cada tarea programada sin aviso propio.',
            self::ClinicalUsageAccessOtp => 'La clave seguirá llegando a todos los SUPERADMIN activos; por Telegram a los vinculados si eligió «Persona».',
            self::PersonalAssignments => 'Cada aviso seguirá llegando a la persona que corresponde; por Telegram si está vinculada y eligió «Persona».',
            self::DoctorCaseAssignment => 'El aviso seguirá llegando al médico asignado; por Telegram si está vinculado y eligió «Persona».',
            self::HelpdeskNotices => 'Los avisos seguirán llegando a las personas del ticket; por Telegram si están vinculadas y eligió «Persona».',
            self::ContadoQuotePayment => $empty
                ? 'Sin destinatarios. Solo saldrá el correo a Administración.'
                : 'Estos contactos recibirán cada pago de contado urgente.',
            self::CourtesyFinance => $empty
                ? 'Sin destinatarios. Solo saldrá el correo a Administración.'
                : 'Estos contactos recibirán cada servicio marcado como cortesía.',
            self::NewQuoteAnalysts => $empty
                ? 'Sin destinatarios. Nadie recibirá el aviso de nuevas cotizaciones.'
                : 'Estos contactos recibirán el aviso de cada cotización nueva, con su PDF por WhatsApp.',
            self::CommercialNetworkActivity => $empty
                ? 'Sin destinatarios. Los movimientos de la red no le llegarán a nadie.'
                : 'Estos contactos recibirán los movimientos de la red comercial.',
            self::DressTaylorRequest => $empty
                ? 'Sin destinatarios. Las solicitudes Dress Taylor no le llegarán a nadie.'
                : 'Estos contactos recibirán cada solicitud Dress Taylor.',
            self::GuiaChatFeedback => $empty
                ? 'Sin destinatarios. Solo se enviará el correo a soporte.'
                : 'Estos contactos recibirán cada feedback de la Guía Chat.',
        };
    }

    public function pausesScheduledTask(): bool
    {
        return ! in_array($this, [
            self::CompanyAssociateRegistration,
            self::TelemedicineCaseReversal,
            self::BirthdayNotificationWitnessCopy,
            self::BirthdayNotificationSummary,
            self::TdevRegistration,
            self::CompanyAssociateIlsCoverage,
            self::TelemedicineServiceLimitOverride,
            self::StorefrontQuotePaymentReceipt,
            self::CorporatePaymentFrequencyChange,
            self::LiveSecurityAlert,
            self::LiveSystemAlert,
            self::OperationsAffiliateUpdate,
            self::EarlyRenovationAcceptance,
            self::CorporatePopulationUpdate,
            self::TelemedicineCaseAgentNotice,
            self::SaleReceiptWitnessCopy,
            self::CollectionNoticeWitnessCopy,
            self::TelegramTest,
            self::ScheduledTaskSummary,
            self::ClinicalUsageAccessOtp,
            self::PersonalAssignments,
            self::DoctorCaseAssignment,
            self::HelpdeskNotices,
            self::ContadoQuotePayment,
            self::CourtesyFinance,
            self::NewQuoteAnalysts,
            self::CommercialNetworkActivity,
            self::DressTaylorRequest,
            self::GuiaChatFeedback,
            self::PersonalAssignments,
            self::DoctorCaseAssignment,
            self::HelpdeskNotices,
            self::ContadoQuotePayment,
            self::CourtesyFinance,
        ], true);
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
            self::OperationInventoryLowStock => 'Si está inactiva, no se ejecutará la revisión diaria ni se enviará la alerta de stock bajo.',
            self::TelemedicineCaseReversal => 'Si está inactiva, el médico podrá reversar el caso, pero no se enviará la alerta por correo ni WhatsApp.',
            self::BirthdayNotificationWitnessCopy => 'Si está inactiva, las tarjetas seguirán enviándose a los cumpleañeros, pero no se enviarán copias testigo.',
            self::BirthdayNotificationSummary => 'Si está inactiva, las tarjetas seguirán enviándose, pero no se enviará el resumen de la corrida.',
            self::TdevRegistration => 'Si está inactiva, los registros públicos TDEV seguirán guardándose, pero no se enviará correo ni WhatsApp.',
            self::CompanyAssociateIlsCoverage => 'Si está inactiva, el analista podrá guardar el voucher y confirmar la cobertura, pero no se enviará el aviso por correo ni WhatsApp.',
            self::TelemedicineServiceLimitOverride => 'Si está inactiva, el médico verá el cupo agotado y no podrá pedir la clave OTP. El servicio extra queda bloqueado.',
            self::StorefrontQuotePaymentReceipt => 'Si está inactiva, el cliente podrá cargar el comprobante en la app, pero no se enviará correo ni WhatsApp a Administración.',
            self::CorporatePaymentFrequencyChange => 'Si está inactiva, el cambio y su reverso se aplican y quedan registrados, y los usuarios de Administración reciben la notificación del panel, pero no se envía correo ni WhatsApp.',
            self::LiveSecurityAlert => 'Si está inactiva, el monitor sigue detectando y bloqueando temporalmente las cuentas atacadas, pero no se envía WhatsApp ni correo.',
            self::LiveSystemAlert => 'Si está inactiva, el monitor sigue mostrando colas, fallidos y errores, pero no se envía WhatsApp ni correo.',
            self::OperationsAffiliateUpdate => 'Si está inactiva, Operaciones sigue pudiendo actualizar los datos del afiliado y el cambio queda en la auditoría, pero no se envía correo ni WhatsApp a Afiliaciones.',
            self::EarlyRenovationAcceptance => 'Si está inactiva, el analista con permiso igual puede renovar antes de tiempo y la renovación queda marcada en el histórico y en la auditoría, pero no se envía WhatsApp ni correo a nadie.',
            self::CorporatePopulationUpdate => 'Si está inactiva, el analista puede subir y revisar archivos, pero no puede aplicar ni revertir la actualización: no se emite la clave OTP.',
            self::TelemedicineCaseAgentNotice => 'Si está inactiva, los casos se registran igual, pero no se envía el WhatsApp al agente ni las copias. Los casos registrados mientras estuvo inactiva no se avisan después.',
            self::SaleReceiptWitnessCopy => 'Si está inactiva, los recibos se envían igual al afiliado y al agente / agencia, pero sin copias testigo (ni correo ni WhatsApp).',
            self::CollectionNoticeWitnessCopy => 'Si está inactiva, los avisos de cobro se envían igual al afiliado y al agente / agencia, pero sin copias testigo (ni correo ni WhatsApp).',
            self::TelegramTest => 'Si está inactiva, el botón «Enviar prueba» no envía nada.',
            self::ScheduledTaskSummary => 'Si está inactiva, las tareas programadas corren igual, pero no se envía su resumen a nadie.',
            self::ClinicalUsageAccessOtp => 'Si está inactiva, la clave sigue llegando a los SUPERADMIN por WhatsApp y correo: solo deja de usar Telegram. El acceso a uso clínico no se bloquea.',
            self::PersonalAssignments => 'Si está inactiva, los avisos siguen llegando por WhatsApp y correo: solo dejan de usar Telegram.',
            self::DoctorCaseAssignment => 'Si está inactiva, el aviso sigue llegando al médico por WhatsApp y correo: solo deja de usar Telegram.',
            self::HelpdeskNotices => 'Si está inactiva, los avisos del Helpdesk siguen llegando por WhatsApp y correo: solo dejan de usar Telegram.',
            self::ContadoQuotePayment => 'Si está inactiva, Operaciones trabaja igual y el correo a Administración sale, pero no se avisa por WhatsApp ni Telegram.',
            self::CourtesyFinance => 'Si está inactiva, Operaciones trabaja igual y el correo a Administración sale, pero no se avisa por WhatsApp ni Telegram.',
            self::NewQuoteAnalysts => 'Si está inactiva, las cotizaciones se crean igual, pero nadie recibe el aviso ni el PDF.',
            self::CommercialNetworkActivity => 'Si está inactiva, la red comercial sigue trabajando igual, pero estos avisos no le llegan a nadie.',
            self::DressTaylorRequest => 'Si está inactiva, la solicitud se registra igual, pero nadie recibe el aviso.',
            self::GuiaChatFeedback => 'Si está inactiva, el comentario se guarda y el correo a soporte sale igual, pero no se avisa por WhatsApp ni Telegram.',
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
            self::OperationInventoryLowStock,
            self::TelemedicineCaseReversal,
            self::BirthdayNotificationWitnessCopy,
            self::BirthdayNotificationSummary,
            self::TdevRegistration,
            self::CompanyAssociateIlsCoverage,
            self::TelemedicineServiceLimitOverride,
            self::StorefrontQuotePaymentReceipt,
            self::CorporatePaymentFrequencyChange,
            self::LiveSecurityAlert,
            self::LiveSystemAlert,
            self::OperationsAffiliateUpdate,
            self::EarlyRenovationAcceptance,
            self::CorporatePopulationUpdate,
            self::TelemedicineCaseAgentNotice,
            self::SaleReceiptWitnessCopy,
            self::CollectionNoticeWitnessCopy,
            self::TelegramTest,
            self::ScheduledTaskSummary,
            self::ClinicalUsageAccessOtp,
            self::PersonalAssignments,
            self::DoctorCaseAssignment,
            self::HelpdeskNotices,
            self::ContadoQuotePayment,
            self::CourtesyFinance,
            self::NewQuoteAnalysts,
            self::CommercialNetworkActivity,
            self::DressTaylorRequest,
            self::GuiaChatFeedback,
        ];
    }

    /**
     * Las notificaciones que solo salen por WhatsApp ocultan la sección de
     * correos en el Centro de notificaciones.
     */
    public function usesEmailRecipients(): bool
    {
        return ! in_array($this, [
            self::TelemedicineCaseAgentNotice,
            self::ClinicalUsageAccessOtp,
            self::PersonalAssignments,
            self::DoctorCaseAssignment,
            self::HelpdeskNotices,
            self::NewQuoteAnalysts,
            self::CommercialNetworkActivity,
            self::DressTaylorRequest,
            self::GuiaChatFeedback,
            self::ContadoQuotePayment,
            self::CourtesyFinance,
        ], true);
    }

    /**
     * Avisos cuyos destinatarios no se configuran aquí (salen de los usuarios, por
     * ejemplo todos los SUPERADMIN): la pantalla oculta la sección de teléfonos.
     */
    public function usesPhoneRecipients(): bool
    {
        return ! in_array($this, [self::ClinicalUsageAccessOtp, self::PersonalAssignments, self::DoctorCaseAssignment, self::HelpdeskNotices], true);
    }

    /**
     * Notificaciones cuyos correos se copian a un envío ajeno (testigos): cada correo se marca
     * como copia visible (CC) u oculta (CCO).
     */
    public function supportsEmailCopyModes(): bool
    {
        return in_array($this, [self::SaleReceiptWitnessCopy, self::CollectionNoticeWitnessCopy], true);
    }

    /**
     * Avisos que ya se reparten con InternalNotificationDispatcher y por eso
     * muestran la sección de Telegram en el Centro de notificaciones. Los demás
     * se irán sumando a medida que se migren; mientras tanto salen como siempre.
     */
    public function supportsTelegram(): bool
    {
        return in_array($this, [
            self::TelegramTest,
            self::LiveSystemAlert,
            self::LiveSecurityAlert,
            self::DatabaseBackup,
            self::StructureBackup,
            self::AgentQuoteAnulation,
            self::DailyAuditSummary,
            self::OperationInventoryLowStock,
            self::ScheduledTaskSummary,
            self::TelemedicineServiceLimitOverride,
            self::CorporatePopulationUpdate,
            self::ClinicalUsageAccessOtp,
            self::CompanyAssociateRegistration,
            self::CompanyAssociateIlsCoverage,
            self::TdevRegistration,
            self::IndividualQuoteFollowUp,
            self::EarlyRenovationAcceptance,
            self::NewQuoteAnalysts,
            self::CommercialNetworkActivity,
            self::DressTaylorRequest,
            self::GuiaChatFeedback,
            self::PersonalAssignments,
            self::StorefrontQuotePaymentReceipt,
            self::CorporatePaymentFrequencyChange,
            self::OperationsAffiliateUpdate,
            self::ContadoQuotePayment,
            self::CourtesyFinance,
            self::SaleReceiptWitnessCopy,
            self::CollectionNoticeWitnessCopy,
            self::TelemedicineCaseReversal,
            self::TelemedicineCaseAgentNotice,
            self::DoctorCaseAssignment,
            self::HelpdeskNotices,
            self::BirthdayNotificationWitnessCopy,
            self::BirthdayNotificationSummary,
        ], true);
    }

    /**
     * Las claves OTP nunca van a un grupo: cualquiera del grupo la vería.
     */
    public function allowsTelegramGroups(): bool
    {
        return ! in_array($this, [
            self::TelemedicineServiceLimitOverride,
            self::CorporatePopulationUpdate,
            self::ClinicalUsageAccessOtp,
            self::PersonalAssignments,
            self::DoctorCaseAssignment,
            self::HelpdeskNotices,
        ], true);
    }

    /** Áreas en el orden en que se muestran en el Centro de notificaciones. */
    public const AREAS = [
        'sistema' => 'Sistema',
        'seguridad' => 'Seguridad y claves OTP',
        'negocios' => 'Negocios',
        'administracion' => 'Administración',
        'operaciones' => 'Operaciones',
        'telemedicina' => 'Telemedicina',
        'helpdesk' => 'Helpdesk',
        'rrhh' => 'Marketing y RRHH',
    ];

    /**
     * Área de negocio del aviso, para agruparlo en el Centro de notificaciones.
     */
    public function area(): string
    {
        return match ($this) {
            self::DatabaseBackup, self::StructureBackup, self::DailyAuditSummary, self::LiveSystemAlert,
            self::ScheduledTaskSummary, self::TelegramTest => 'sistema',
            self::LiveSecurityAlert, self::TelemedicineServiceLimitOverride, self::CorporatePopulationUpdate,
            self::ClinicalUsageAccessOtp => 'seguridad',
            self::CompanyAssociateRegistration, self::CompanyAssociateIlsCoverage, self::TdevRegistration,
            self::IndividualQuoteFollowUp, self::AgentQuoteAnulation, self::EarlyRenovationAcceptance,
            self::NewQuoteAnalysts, self::CommercialNetworkActivity, self::DressTaylorRequest,
            self::GuiaChatFeedback, self::PersonalAssignments => 'negocios',
            self::StorefrontQuotePaymentReceipt, self::CorporatePaymentFrequencyChange, self::SaleReceiptWitnessCopy,
            self::CollectionNoticeWitnessCopy, self::ContadoQuotePayment, self::CourtesyFinance => 'administracion',
            self::OperationInventoryLowStock, self::OperationsAffiliateUpdate => 'operaciones',
            self::TelemedicineCaseReversal, self::TelemedicineCaseAgentNotice, self::DoctorCaseAssignment => 'telemedicina',
            self::BirthdayNotificationWitnessCopy, self::BirthdayNotificationSummary => 'rrhh',
            self::HelpdeskNotices => 'helpdesk',
        };
    }

    /**
     * Entrega del plan en la que este aviso pasa a Telegram, mientras todavía sale
     * solo por WhatsApp. null si ya puede usar Telegram.
     */
    public function telegramMigrationStage(): ?string
    {
        if ($this->supportsTelegram()) {
            return null;
        }

        return match ($this->area()) {
            'negocios' => 'B3',
            'administracion' => 'B4',
            'operaciones' => 'B4',
            'telemedicina' => 'B5',
            'rrhh' => 'B7',
            default => 'B3',
        };
    }

    /**
     * Números iniciales de los avisos de cotizaciones: los que estaban fijos en el
     * sistema más la línea de cotizaciones y afiliaciones (config/parameters.php).
     *
     * @param  list<string>  $phones
     * @return list<string>
     */
    private static function phonesWithQuotesLine(array $phones): array
    {
        $quotesLine = trim((string) config('parameters.PHONE_COTIZACIONES_AFILIACIONES'));

        if ($quotesLine !== '') {
            $phones[] = $quotesLine;
        }

        return array_values(array_unique($phones));
    }

    /**
     * Avisos que van a una persona concreta que decide cada caso (el asignado o el creador
     * del ticket, el invitado, el médico del caso): no tienen lista de contactos ni grupos.
     */
    public function isPersonTargeted(): bool
    {
        return in_array($this, [self::PersonalAssignments, self::DoctorCaseAssignment, self::HelpdeskNotices], true);
    }

    /**
     * Avisos que llevan una clave OTP: nunca pueden ir a un grupo.
     */
    public function carriesOtp(): bool
    {
        return in_array($this, [self::TelemedicineServiceLimitOverride, self::CorporatePopulationUpdate, self::ClinicalUsageAccessOtp], true);
    }

    /**
     * Explicación del selector de Telegram en el Centro de notificaciones según el tipo de aviso.
     */
    public function telegramModeHelp(): string
    {
        return match (true) {
            $this === self::HelpdeskNotices => 'Cada aviso va solo a la persona que le toca: al crear o asignar el ticket, a los asignados; cuando un asignado responde o actualiza, al creador; cuando el creador cierra o cambia la prioridad, a los asignados. Con «Persona», si esa persona tiene Telegram vinculado le llega allí; si no, por WhatsApp. Nadie más lo recibe.',
            $this->isPersonTargeted() => 'Cada aviso va solo a la persona que le corresponde. Con «Persona», si esa persona tiene Telegram vinculado le llega allí; si no, por WhatsApp. Nadie más lo recibe.',
            $this === self::ClinicalUsageAccessOtp => 'La clave va siempre a todos los SUPERADMIN activos. Con «Persona», los que tengan Telegram vinculado la reciben allí; los demás, por WhatsApp. Una clave OTP nunca va a un grupo.',
            $this->carriesOtp() => 'Este aviso lleva una clave OTP: solo puede ir a personas, nunca a un grupo. Con «Persona», los contactos de arriba que tengan Telegram vinculado la reciben allí; los demás, por WhatsApp.',
            default => 'Persona: a cada contacto de arriba que tenga Telegram vinculado. Grupo: solo a los grupos elegidos; los teléfonos dejan de recibir WhatsApp y solo lo reciben si el grupo falla. Ambos: las dos cosas.',
        };
    }

    /**
     * Texto de la opción «Persona» del selector de Telegram.
     */
    public function telegramPersonLabel(): string
    {
        return match (true) {
            $this->isPersonTargeted() => 'Persona · a quien le corresponde el aviso',
            $this === self::ClinicalUsageAccessOtp => 'Persona · a cada SUPERADMIN vinculado',
            default => 'Persona · a cada contacto vinculado',
        };
    }
}
