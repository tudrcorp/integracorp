<?php

declare(strict_types=1);

use App\Mail\CompanyPublicRegistrationLinkEmail;

it('incluye mailable y plantilla para enlace público de empresa', function (): void {
    $mailable = file_get_contents(dirname(__DIR__, 2).'/app/Mail/CompanyPublicRegistrationLinkEmail.php');
    $blade = file_get_contents(dirname(__DIR__, 2).'/resources/views/mails/company-public-registration-link.blade.php');

    expect($mailable)
        ->toContain('CompanyPublicRegistrationLinkEmail')
        ->toContain('company-public-registration-link')
        ->toContain('Enlace de registro de asociados');

    expect($blade)
        ->toContain('Enlace público de registro')
        ->toContain('$content[\'link\']')
        ->toContain('$content[\'company_name\']');
});

it('sender usa registro público y canales de notificación', function (): void {
    $sender = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyPublicRegistrationLinkSender.php');

    expect($sender)
        ->toContain('CompanyAssociateRegistrar::publicRegistrationUrl')
        ->toContain('CompanyPublicRegistrationLinkEmail')
        ->toContain('HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp')
        ->toContain('AUDIT_BUSINESS_COMPANY_PUBLIC_LINK_EMAIL_SENT')
        ->toContain('AUDIT_BUSINESS_COMPANY_PUBLIC_LINK_WHATSAPP_SENT');
});

it('view company incluye acción para enviar enlace público', function (): void {
    $viewPage = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Companies/Pages/ViewCompany.php');
    $tableActions = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Companies/Actions/CompanyTableActions.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Companies/Tables/CompaniesTable.php');

    expect($viewPage)
        ->toContain('CompanyTableActions::sendPublicRegistrationLinkAction');

    expect($tableActions)
        ->toContain('sendPublicRegistrationLink')
        ->toContain('CompanyPublicRegistrationLinkSender::sendEmail')
        ->toContain('CompanyPublicRegistrationLinkSender::sendWhatsApp')
        ->toContain('Enviar enlace público');

    expect($table)
        ->toContain('CompanyTableActions::sendPublicRegistrationLinkAction');
});

it('mailable expone asunto con nombre de empresa', function (): void {
    $email = new CompanyPublicRegistrationLinkEmail([
        'link' => 'https://example.test/nb/token',
        'company_name' => 'Acme Corp',
        'sent_at' => now(),
    ]);

    expect($email->envelope()->subject)->toBe('Enlace de registro de asociados — Acme Corp');
});
