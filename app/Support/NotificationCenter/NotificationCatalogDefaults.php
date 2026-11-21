<?php

declare(strict_types=1);

namespace App\Support\NotificationCenter;

use App\Enums\NotificationAudience;
use App\Enums\SystemNotificationKey;

/**
 * Carga inicial del catálogo del Centro de notificaciones.
 *
 * Cada tipo se reconoce por su «origen»: el método que arma el mensaje
 * (Clase::método) o, en los correos, «mail:Clase» del Mailable. Lo que no esté
 * aquí no se pierde: el guardián lo agrega solo como «Sin clasificar» la primera
 * vez que sale, y el analista lo nombra y clasifica desde la pantalla.
 *
 * La carga es aditiva: nunca pisa nombres, áreas ni interruptores que el analista
 * ya cambió; solo suma orígenes nuevos.
 */
final class NotificationCatalogDefaults
{
    /** Áreas en el orden en que se muestran. */
    public const AREAS = [
        'sistema' => 'Sistema',
        'seguridad' => 'Seguridad y claves OTP',
        'negocios' => 'Negocios y cotizaciones',
        'red-comercial' => 'Red comercial',
        'afiliaciones' => 'Afiliaciones y empresas',
        'administracion' => 'Administración y cobranza',
        'operaciones' => 'Operaciones y proveedores',
        'telemedicina' => 'Telemedicina',
        'helpdesk' => 'Helpdesk',
        'rrhh' => 'Marketing y RRHH',
        'marketing' => 'Campañas masivas',
        'sin-clasificar' => 'Sin clasificar',
    ];

    /** Avisos internos que no obedecen el interruptor general de un canal. */
    public const CRITICAL_SYSTEM_KEYS = [
        'live_security_alert',
        'live_system_alert',
        'corporate_population_update',
        'telemedicine_service_limit_override',
        'clinical_usage_access_otp',
    ];

    /**
     * Orígenes que arman los avisos internos del Centro (para reconocer su WhatsApp
     * y su correo cuando no pasan por el repartidor interno).
     *
     * @var array<string, list<string>>
     */
    public const SYSTEM_KEY_ORIGINS = [
        'new_quote_analysts' => [
            'App\Http\Controllers\NotificationController::createdIndividualQuote',
            'App\Http\Controllers\NotificationController::createdCorporateQuote',
        ],
        'commercial_network_activity' => [
            'App\Http\Controllers\NotificationController::sendUploadDataCorporate',
            'App\Http\Controllers\NotificationController::uploadVoucherOfPayment',
            'App\Http\Controllers\NotificationController::createdIndividualPreAfilliation',
            'App\Http\Controllers\NotificationController::saddObervationToIndividualQuote',
            'App\Http\Controllers\NotificationController::saddObervationToCorporateQuote',
        ],
        'dress_taylor_request' => ['App\Http\Controllers\NotificationController::createdRequestDressTaylor'],
        'personal_assignments' => [
            'App\Services\CorporateAgendaInvitationWhatsAppService::dispatchInvitationToParticipant',
            'App\Services\CorporateAgendaInvitationWhatsAppService::notifyCreatorAboutInvitationResponse',
            'App\Support\ProspectAgents\ProspectAgentTaskAssigneeNotifier::sendWhatsApp',
            'mail:App\Mail\ProspectAgentTaskAssignedMail',
        ],
        'helpdesk_notices' => [
            'App\Services\HelpdeskTicketAssigneeWhatsAppService::dispatchInternalWhatsApp',
            'mail:App\Mail\SendEmailCreateTicketAndAssigned',
            'mail:App\Mail\SendEmailHelpdeskTicketReverted',
        ],
        'daily_audit_summary' => ['App\Jobs\SendDailyAuditSummary::dispatchWhatsApp', 'mail:App\Mail\AuditCompletionSummaryMail'],
        'operation_inventory_low_stock' => [
            'App\Jobs\SendOperationInventoryLowStockAlert::dispatchWhatsApp',
            'App\Jobs\NotifyOperationInventoryProductLowStockJob::dispatchWhatsApp',
            'mail:App\Mail\OperationInventoryLowStockMail',
        ],
        'company_associate_registration' => [
            'App\Jobs\NotifyAnalystsOfCompanyAssociateRegistrationJob::handle',
            'mail:App\Mail\CompanyAssociateRegisteredAnalystMail',
        ],
        'storefront_quote_payment_receipt' => [
            'App\Jobs\NotifyAdministrationOfStorefrontQuotePaymentReceiptJob::sendWhatsApps',
            'mail:App\Mail\StorefrontQuotePaymentReceiptMail',
        ],
        'operations_affiliate_update' => [
            'App\Jobs\NotifyAffiliationsTeamOfAffiliateUpdateJob::handle',
            'mail:App\Mail\AffiliateUpdatedByOperationsMail',
        ],
        'doctor_case_assignment' => ['App\Http\Controllers\NotificationController::assignedCase', 'mail:App\Mail\MailAssignedCase'],
        'tdev_registration' => [
            'App\Jobs\NotifyAnalystsOfTdevRegistrationJob::sendWhatsAppWithBrandFallback',
            'App\Jobs\NotifyAnalystsOfTdevRegistrationJob::handle',
            'mail:App\Mail\TdevRegisteredAnalystMail',
        ],
        'agent_quote_anulation' => ['App\Jobs\AnulateAgentQuotes::notifyRecipients', 'mail:App\Mail\AnulatedQuotesNotificationMail'],
        'early_renovation_acceptance' => ['App\Jobs\NotifySuperAdminsOfEarlyRenovationJob::handle', 'mail:App\Mail\EarlyRenovationAcceptedMail'],
        'company_associate_ils_coverage' => [
            'App\Jobs\NotifyCompanyAssociateIlsCoverageConfirmedJob::sendWhatsApps',
            'mail:App\Mail\CompanyAssociateIlsCoverageConfirmedMail',
        ],
        'live_security_alert' => ['App\Jobs\NotifyLiveSecurityAlertJob::handle', 'mail:App\Mail\LiveSecurityAlertMail'],
        'corporate_payment_frequency_change' => [
            'App\Jobs\NotifyAdministrationOfCorporatePaymentFrequencyChangeJob::sendWhatsApps',
            'mail:App\Mail\CorporatePaymentFrequencyChangeMail',
        ],
        'telemedicine_case_reversal' => ['App\Jobs\NotifyTelemedicineCaseReversalJob::handle', 'mail:App\Mail\TelemedicineCaseReversedMail'],
        'corporate_population_update' => ['App\Support\AffiliationCorporates\Population\CorporatePopulationOtp::deliver', 'mail:App\Mail\CorporatePopulationOtpMail'],
        'individual_quote_follow_up' => [
            'App\Support\IndividualQuotes\IndividualQuoteFollowUpInternalCopies::dispatch',
            'mail:App\Mail\IndividualQuoteFollowUpInternalCopyMail',
        ],
        'contado_quote_payment' => ['App\Support\Operations\ContadoQuotePaymentNotifier::dispatchForQuote', 'mail:App\Mail\ContadoQuotePaymentMail'],
        'courtesy_finance' => ['App\Support\Operations\CourtesyFinanceNotifier::dispatchNotifications', 'mail:App\Mail\CourtesyFinanceMail'],
        'guia_chat_feedback' => ['App\Support\GuiaChat\GuiaChatFeedbackNotifier::dispatchForFeedback', 'mail:App\Mail\GuiaChatFeedbackMail'],
        'telemedicine_service_limit_override' => [
            'App\Support\ClinicalEntitlements\ClinicalServiceOverrideOtp::deliver',
            'mail:App\Mail\ClinicalServiceOverrideOtpMail',
        ],
        'clinical_usage_access_otp' => [
            'App\Support\ClinicalEntitlements\ClinicalUsageAccessOtp::deliver',
            'App\Jobs\SendOtpSelfAuthorizationNoticeJob::handle',
            'mail:App\Mail\ClinicalUsageAccessOtpMail',
            'mail:App\Mail\OtpSelfAuthorizationNoticeMail',
        ],
        'birthday_notification_summary' => ['App\Support\BirthdayNotificationRunReport::finishAndNotify', 'mail:App\Mail\BirthdayNotificationSummaryMail'],
        'birthday_notification_witness_copy' => ['App\Support\BirthdayNotificationRunReport::queueWitnessWhatsAppCopies'],
        'sale_receipt_witness_copy' => ['App\Support\Filament\Administration\SaleReciboPagoWhatsAppRecipients::sendWitnessCopiesByTelegram'],
        'collection_notice_witness_copy' => ['mail:App\Mail\CollectionNoticeCopyMail'],
        'scheduled_task_summary' => [
            'App\Support\ScheduledTaskRunReport::notifySummaryToPhone',
            'App\Support\ScheduledTaskRunReport::sendDocumentToPhone',
            'App\Support\ScheduledTaskRunReport::finishAndNotify',
            'mail:App\Mail\ScheduledTaskRunReportMail',
        ],
        'telegram_test' => [
            'App\Filament\Business\Pages\ManageCompanyAssociateNotifications::sendTelegramTest',
            'App\Support\InternalNotifications\InternalNotificationDispatcher::sendTestTo',
        ],
    ];

    /**
     * Tipos que no son avisos del Centro: externos, internos sueltos y masivos.
     *
     * @return array<string, array{name: string, area: string, audience: NotificationAudience, origins: list<string>, channels: list<string>, critical?: bool, bulk?: bool, description?: string}>
     */
    public static function entries(): array
    {
        $external = NotificationAudience::External;
        $internal = NotificationAudience::Internal;
        $nc = 'App\Http\Controllers\NotificationController::';

        return [
            // Negocios y cotizaciones
            'ext.quote_pdf' => ['name' => 'Propuesta económica al cliente (PDF)', 'area' => 'negocios', 'audience' => $external, 'channels' => ['whatsapp', 'mail'], 'origins' => [
                $nc.'sendQuote',
                'mail:App\Mail\SendMailPropuestaMultiPlan', 'mail:App\Mail\SendMailPropuestaPlanEspecial', 'mail:App\Mail\SendMailPropuestaPlanIdeal',
                'mail:App\Mail\SendMailPropuestaPlanInicial', 'mail:App\Mail\ResendMailPropuestaEconomica',
            ]],
            'ext.quote_link' => ['name' => 'Enlace de cotización interactiva', 'area' => 'negocios', 'audience' => $external, 'channels' => ['whatsapp', 'mail'], 'origins' => [
                $nc.'sendLinkIndividualQuote', 'App\Jobs\SendStorefrontQuoteShareJob::handle',
                'mail:App\Mail\MailLinkIndividualQuote', 'mail:App\Mail\CotizacionInteractiva',
            ]],
            'ext.quote_followup_ally' => ['name' => 'Follow-up de cotización al agente o agencia (días 3 a 12)', 'area' => 'negocios', 'audience' => $external, 'channels' => ['whatsapp', 'mail'], 'origins' => [
                'App\Jobs\SendIndividualQuoteDayThreeFollowUp::dispatchAllyWhatsAppMessages',
                'App\Jobs\SendIndividualQuoteDayFiveFollowUp::dispatchFollowUpMessages',
                'App\Jobs\SendIndividualQuoteDaySevenFollowUp::dispatchFollowUpMessages',
                'App\Jobs\SendIndividualQuoteDayNineFollowUp::dispatchFollowUpMessages',
                'App\Jobs\SendIndividualQuoteDayTwelveFollowUp::dispatchAllyWhatsAppMessages',
                'mail:App\Mail\IndividualQuoteFollowUpMail',
            ], 'description' => 'El correo de follow-up es el mismo para el agente y el cliente: se controla desde aquí.'],
            'ext.quote_followup_client' => ['name' => 'Follow-up de cotización al cliente (días 3 y 12)', 'area' => 'negocios', 'audience' => $external, 'channels' => ['whatsapp'], 'origins' => [
                'App\Jobs\SendIndividualQuoteDayThreeFollowUp::dispatchClientWhatsApp',
                'App\Jobs\SendIndividualQuoteDayTwelveFollowUp::dispatchClientWhatsApp',
            ]],
            'ext.payment_methods_pwa' => ['name' => 'Formas de pago desde la PWA', 'area' => 'negocios', 'audience' => $external, 'channels' => ['whatsapp', 'mail'], 'origins' => [
                'App\Jobs\SendStorefrontPaymentMethodsShareJob::handle', 'mail:App\Mail\StorefrontPaymentMethodsMail',
            ]],
            'ext.document_reminder' => ['name' => 'Recordatorio de documentos pendientes al agente', 'area' => 'negocios', 'audience' => $external, 'channels' => ['whatsapp'], 'origins' => [$nc.'documentUploadReminder']],
            'ext.capitation_report' => ['name' => 'Reporte de plan de capitación', 'area' => 'negocios', 'audience' => $external, 'channels' => ['whatsapp', 'mail'], 'origins' => [
                'App\Jobs\SendCapitationPlanReportJob::handle', 'mail:App\Mail\CapitationPlanReportMail',
            ]],

            // Red comercial
            'ext.network_welcome' => ['name' => 'Bienvenida y credenciales a agentes y agencias', 'area' => 'red-comercial', 'audience' => $external, 'channels' => ['whatsapp', 'mail'], 'origins' => [
                $nc.'agency_activated', $nc.'agent_activated',
                'mail:App\Mail\MailCartaBienvenidaAgenteAgencia', 'mail:App\Mail\MailCartaBienvenidaAgenteAgenciaTwo',
                'mail:App\Mail\MailCartaBienvenidaEjecutivo', 'mail:App\Mail\SendMailKitBienvenida',
            ]],
            'ext.register_link' => ['name' => 'Enlace de registro para agentes y agencias', 'area' => 'red-comercial', 'audience' => $external, 'channels' => ['whatsapp', 'mail'], 'origins' => [
                $nc.'send_link_agency_register_wp', $nc.'send_link_agent_register_wp',
                'mail:App\Mail\AgencyRegisterEmail', 'mail:App\Mail\AgentRegisterEmail',
            ]],
            'ext.guia_chat_link' => ['name' => 'Enlace a la Guía Chat', 'area' => 'red-comercial', 'audience' => $external, 'channels' => ['whatsapp'], 'origins' => [$nc.'send_guia_chat_link_wp']],
            'ext.chat_registration' => ['name' => 'Registro por el chat (credenciales y carta)', 'area' => 'red-comercial', 'audience' => $external, 'channels' => ['whatsapp'], 'origins' => [
                'App\Services\PublicAiAgent\ChatAgentRegistrationService::sendRegistrationPackageViaWhatsApp',
                'App\Services\PublicAiAgent\ChatAgencyMasterRegistrationService::sendRegistrationPackageViaWhatsApp',
                'App\Services\PublicAiAgent\ChatAgencyGeneralRegistrationService::sendRegistrationPackageViaWhatsApp',
            ]],
            'ext.network_ficha' => ['name' => 'Ficha PDF de agente, agencia o agencia de viajes', 'area' => 'red-comercial', 'audience' => $external, 'channels' => ['whatsapp', 'mail'], 'origins' => [
                'App\Jobs\SendBusinessAgentFichaPdfWhatsAppJob::handle', 'App\Jobs\SendBusinessAgencyFichaPdfWhatsAppJob::handle',
                'App\Jobs\SendBusinessTravelAgencyFichaPdfWhatsAppJob::handle',
                'mail:App\Mail\BusinessAgentFichaPdfMail', 'mail:App\Mail\BusinessAgencyFichaPdfMail', 'mail:App\Mail\BusinessTravelAgencyFichaPdfMail',
            ]],
            'ext.preaffiliation_link' => ['name' => 'Enlace de pre-afiliación', 'area' => 'red-comercial', 'audience' => $external, 'channels' => ['whatsapp'], 'origins' => [$nc.'send_link_preAffiliation']],

            // Afiliaciones y empresas
            'ext.affiliate_ficha' => ['name' => 'Ficha del afiliado', 'area' => 'afiliaciones', 'audience' => $external, 'channels' => ['whatsapp', 'mail'], 'origins' => [
                'App\Jobs\SendAffiliateFichaWhatsAppJob::handle', 'mail:App\Mail\AffiliateFichaMail',
            ]],
            'ext.associate_documents' => ['name' => 'Documentos y carnet al asociado de empresa', 'area' => 'afiliaciones', 'audience' => $external, 'channels' => ['whatsapp', 'mail'], 'origins' => [
                'App\Support\Companies\CompanyAssociateDocumentsDeliverer::deliver', 'mail:App\Mail\CompanyAssociateDocumentsMail',
            ]],
            'ext.company_registration_link' => ['name' => 'Enlace público de registro de empresa', 'area' => 'afiliaciones', 'audience' => $external, 'channels' => ['whatsapp', 'mail'], 'origins' => [
                'App\Support\Companies\CompanyPublicRegistrationLinkSender::sendWhatsApp', 'mail:App\Mail\CompanyPublicRegistrationLinkEmail',
            ]],
            'ext.affiliate_card' => ['name' => 'Carnet y tarjeta del afiliado', 'area' => 'afiliaciones', 'audience' => $external, 'channels' => ['mail'], 'origins' => [
                'mail:App\Mail\SendMailTarjetaAfiliado', 'mail:App\Mail\AffiliateCarnetIssuedMail',
            ]],
            'ext.affiliation_documents' => ['name' => 'Certificados y documentos de afiliación', 'area' => 'afiliaciones', 'audience' => $external, 'channels' => ['mail'], 'origins' => [
                'mail:App\Mail\CertificateEmail', 'mail:App\Mail\AffiliationCertificateDeliveryMail', 'mail:App\Mail\AffiliationDocumentsGeneratedMail',
            ]],
            'ext.renewal_notice' => ['name' => 'Aviso de renovación de afiliación', 'area' => 'afiliaciones', 'audience' => $external, 'channels' => ['mail'], 'origins' => ['mail:App\Mail\NotificationRenewAffiliationMail']],

            // Administración y cobranza
            'ext.sale_receipt' => ['name' => 'Recibo de pago', 'area' => 'administracion', 'audience' => $external, 'channels' => ['whatsapp', 'mail'], 'origins' => [
                $nc.'sendReciboDePago', 'mail:App\Mail\MailSaleReciboPago',
            ]],
            'ext.collection_notice' => ['name' => 'Aviso de cobro', 'area' => 'administracion', 'audience' => $external, 'channels' => ['mail'], 'origins' => [
                'mail:App\Mail\MailAvisoDeCobro', 'mail:App\Mail\AvisoCobroCertificateEmail',
            ]],
            'ext.payment_notice' => ['name' => 'Aviso de pago', 'area' => 'administracion', 'audience' => $external, 'channels' => ['mail'], 'origins' => ['mail:App\Mail\MailAvisoDePago']],
            'ext.white_company_report' => ['name' => 'Reporte de ventas a empresa aliada', 'area' => 'administracion', 'audience' => $external, 'channels' => ['whatsapp', 'mail'], 'origins' => [
                'App\Jobs\SendWhiteCompanySalesReportWhatsAppJob::handle', 'mail:App\Mail\WhiteCompanySalesReportMail',
            ]],

            // Operaciones y proveedores
            'ext.provider_service_order' => ['name' => 'Orden de servicio al proveedor', 'area' => 'operaciones', 'audience' => $external, 'channels' => ['whatsapp', 'mail'], 'origins' => [
                'App\Filament\Operations\Resources\OperationMedicalAppointments\Tables\OperationMedicalAppointmentsTable::{closure}',
                'mail:App\Mail\OperationServiceOrderPdfMail',
            ]],
            'ext.appointment_reschedule' => ['name' => 'Cita reprogramada al proveedor', 'area' => 'operaciones', 'audience' => $external, 'channels' => ['whatsapp', 'mail'], 'origins' => [
                'App\Support\Operations\AppointmentRescheduleNotifier::dispatchForAppointment', 'mail:App\Mail\AppointmentRescheduleMail',
            ]],
            'ext.order_reassignment' => ['name' => 'Orden retirada o reasignada al proveedor', 'area' => 'operaciones', 'audience' => $external, 'channels' => ['whatsapp', 'mail'], 'origins' => [
                'App\Support\Operations\OperationServiceOrderReassignment::notifyWithdrawnProvider',
                'App\Support\Operations\OperationServiceOrderReassignment::notifyAssignedProvider',
                'mail:App\Mail\OperationServiceOrderWithdrawnMail',
            ]],
            'ext.medication_invoice' => ['name' => 'Factura de medicamentos (PDF)', 'area' => 'operaciones', 'audience' => $external, 'channels' => ['whatsapp', 'mail'], 'origins' => [
                'App\Jobs\SendMedicationInvoicePdfJob::handle', 'mail:App\Mail\MedicationInvoicePdfMail',
            ]],
            'ext.quality_control' => ['name' => 'Encuesta de control de calidad (PDF)', 'area' => 'operaciones', 'audience' => $external, 'channels' => ['whatsapp', 'mail'], 'origins' => [
                'App\Jobs\SendQualityControlPdfJob::handle', 'mail:App\Mail\QualityControlPdfMail',
            ]],
            'ext.supplier_report' => ['name' => 'Reporte al proveedor', 'area' => 'operaciones', 'audience' => $external, 'channels' => ['mail'], 'origins' => ['mail:App\Mail\SupplierReportMail']],

            // Telemedicina
            'ext.consultation_documents' => ['name' => 'Documentos de la consulta al paciente', 'area' => 'telemedicina', 'audience' => $external, 'channels' => ['whatsapp', 'mail'], 'origins' => [
                'App\Services\TelemedicineConsultationDocumentsNotificationService::sendWhatsAppDocuments', 'mail:App\Mail\TelemedicineConsultationDocumentsMail',
            ]],
            'ext.case_documents' => ['name' => 'Documentos o bitácora del caso', 'area' => 'telemedicina', 'audience' => $external, 'channels' => ['whatsapp', 'mail'], 'origins' => [
                'App\Services\Telemedicine\TelemedicineCaseDocumentDeliveryService::sendWhatsApp', 'mail:App\Mail\TelemedicineCaseDocumentMail',
            ]],
            'ext.clinical_pdf_legacy' => ['name' => 'Informes clínicos al paciente (envío anterior)', 'area' => 'telemedicina', 'audience' => $external, 'channels' => ['whatsapp'], 'origins' => [
                'App\Services\NotificationTelemedicinaService::sendDocuments', $nc.'previewMessage',
            ]],
            'ext.agent_new_case' => ['name' => 'Caso nuevo al agente de la afiliación', 'area' => 'telemedicina', 'audience' => $external, 'channels' => ['whatsapp'], 'origins' => [
                'App\Jobs\NotifyAgentOfNewTelemedicineCaseJob::handle',
            ], 'description' => 'Incluye las copias internas que salen por WhatsApp desde el mismo envío.'],
            'ext.medication_reminder' => ['name' => 'Recordatorio de medicación al paciente', 'area' => 'telemedicina', 'audience' => $external, 'channels' => ['whatsapp'], 'origins' => [$nc.'rememberMedication']],

            // Internos sueltos (no están en el Centro de avisos)
            'int.admin_failure_alert' => ['name' => 'Aviso al administrador de envíos fallidos', 'area' => 'sistema', 'audience' => $internal, 'channels' => ['whatsapp'], 'origins' => ['App\Http\Controllers\UtilsController::notificacionToAdmin']],
            'int.duplicate_session' => ['name' => 'Aviso de sesión duplicada', 'area' => 'seguridad', 'audience' => $internal, 'channels' => ['whatsapp'], 'origins' => ['App\Http\Controllers\UtilsController::notificacionSesionDuplicada']],
            'int.telegram_link' => ['name' => 'Enlace para vincular Telegram', 'area' => 'sistema', 'audience' => $internal, 'channels' => ['whatsapp'], 'origins' => ['App\Support\Telegram\TelegramLinkDelivery::send', 'mail:App\Mail\InternalNotificationMail']],
            'int.collaborator_anniversary' => ['name' => 'Aniversario de colaboradores', 'area' => 'rrhh', 'audience' => $internal, 'channels' => ['whatsapp', 'mail'], 'origins' => [
                'App\Jobs\SendCollaboratorAnniversaryNotification::handle', 'mail:App\Mail\CollaboratorAnniversaryMail',
            ]],
            'int.framework_auth' => ['name' => 'Acceso al sistema (restablecer contraseña y verificaciones)', 'area' => 'seguridad', 'audience' => $internal, 'channels' => ['mail'], 'critical' => true, 'origins' => [
                'mail:Filament\Auth\Notifications\ResetPassword', 'mail:Illuminate\Auth\Notifications\ResetPassword',
                'mail:Filament\Auth\Notifications\VerifyEmail', 'mail:Illuminate\Auth\Notifications\VerifyEmail',
            ]],

            // Masivos: tienen interruptor, pero no van a la bitácora.
            'bulk.mass_campaigns' => ['name' => 'Campañas masivas de Marketing', 'area' => 'marketing', 'audience' => $external, 'channels' => ['whatsapp', 'mail'], 'bulk' => true, 'origins' => [
                'App\Support\MassNotificationWhatsAppSender::send',
                $nc.'sendNotificationWpSingle', $nc.'massNotificacionSend',
                'App\Services\NotificationMasiveService::sendVideo', 'App\Services\NotificationMasiveService::sendUrl',
                'mail:App\Mail\MailMasive', 'mail:App\Mail\NotificationMasiveMail', 'mail:App\Mail\SendNotificationMailSingle',
            ], 'description' => 'Sus envíos se revisan en Marketing → Notificaciones masivas.'],
            'bulk.birthdays' => ['name' => 'Tarjetas de cumpleaños', 'area' => 'marketing', 'audience' => $external, 'channels' => ['whatsapp', 'mail'], 'bulk' => true, 'origins' => [
                'App\Jobs\WhatsAppBirthdayNotification::handle', 'mail:App\Mail\NotificationMasiveMailBirthday',
            ], 'description' => 'Sus envíos se revisan en Marketing → Cumpleaños.'],
        ];
    }

    /**
     * Avisos internos del Centro como entradas del catálogo.
     *
     * @return array<string, array{name: string, area: string, audience: NotificationAudience, origins: list<string>, channels: list<string>, critical: bool, system_key: string}>
     */
    public static function systemEntries(): array
    {
        $entries = [];

        foreach (SystemNotificationKey::managed() as $key) {
            $entries['internal:'.$key->value] = [
                'name' => $key->label(),
                'area' => $key->area(),
                'audience' => NotificationAudience::Internal,
                'origins' => self::SYSTEM_KEY_ORIGINS[$key->value] ?? [],
                'channels' => array_values(array_filter(['telegram' => $key->supportsTelegram() ? 'telegram' : null, 'whatsapp' => 'whatsapp', 'mail' => 'mail'])),
                'critical' => in_array($key->value, self::CRITICAL_SYSTEM_KEYS, true),
                'system_key' => $key->value,
            ];
        }

        return $entries;
    }
}
