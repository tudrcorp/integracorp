<?php

declare(strict_types=1);

use App\Mail\CourtesyFinanceMail;
use App\Mail\OperationServiceOrderPdfMail;
use App\Mail\TelemedicineCaseDocumentMail;
use App\Mail\TelemedicineConsultationDocumentsMail;
use App\Models\OperationServiceOrder;

uses(Tests\TestCase::class);

/**
 * Plantillas de los correos de Telemedicina y Operaciones que llevan el pie
 * corporativo. Helpdesk queda fuera a propósito: se envía desde cuatro paneles.
 *
 * @return list<string>
 */
function correosConPieCorporativo(): array
{
    return [
        'affiliate-updated-by-operations',
        'appointment-reschedule',
        'assigned-case',
        'clinical-service-override-otp',
        'clinical-usage-access-otp',
        'courtesy-finance',
        'operation-inventory-low-stock',
        'operation-service-order-pdf',
        'suppliers-report',
        'telemedicine-case-document',
        'telemedicine-case-reversed',
        'telemedicine-consultation-documents',
    ];
}

it('el pie corporativo trae los datos de la empresa, centrado y con enlaces', function (): void {
    $html = view('mails.partials.tdg-corporate-footer')->render();

    expect($html)
        ->toContain('TU DOCTOR EN CASA, C. A. RIF.: J-50358368-1')
        ->toContain('Av. Francisco de Miranda, Centro Lido, Torre A, Piso 12, Oficina 124. El Rosal, Caracas.')
        ->toContain('Teléfono MediChat atención 24 horas: (0424) 213 21 12')
        ->toContain('Celular Coordinación de servicios: (0414) 901 03 52')
        ->toContain('href="mailto:24H@tudrencasa.com"')
        ->toContain('href="https://tudrencasa.com/"')
        ->toContain('@tudrencasa')
        ->toContain('align="center"')
        ->toContain('text-align:center')
        ->toContain('margin:0 auto');
});

it('cada correo de telemedicina y operaciones incluye el pie una sola vez, al final', function (string $template): void {
    $source = file_get_contents(dirname(__DIR__, 2)."/resources/views/mails/{$template}.blade.php");

    expect(substr_count($source, "@include('mails.partials.tdg-corporate-footer')"))->toBe(1)
        ->and(strpos($source, 'tdg-corporate-footer'))->toBeLessThan(strpos($source, '</body>'));
})->with(correosConPieCorporativo());

it('los correos de helpdesk no llevan el pie', function (string $template): void {
    expect(file_get_contents(dirname(__DIR__, 2)."/resources/views/mails/{$template}.blade.php"))
        ->not->toContain('tdg-corporate-footer');
})->with(['helpdesk-ticket-assigned', 'helpdesk-ticket-reverted']);

it('el correo ya renderizado lleva el pie', function (object $mail): void {
    expect($mail->render())
        ->toContain('TU DOCTOR EN CASA, C. A. RIF.: J-50358368-1')
        ->toContain('24H@tudrencasa.com');
})->with([
    'documentos de la consulta' => fn (): object => new TelemedicineConsultationDocumentsMail('María Rivas', []),
    'documento del caso' => fn (): object => new TelemedicineCaseDocumentMail('María Rivas', 'Informe médico', 'telemedicina-doc/x.pdf'),
    'cortesía a finanzas' => fn (): object => new CourtesyFinanceMail('Orden de servicio OS-1', ['Paciente' => 'María Rivas']),
    'orden de servicio' => fn (): object => new OperationServiceOrderPdfMail(new OperationServiceOrder(['order_number' => 'OS-1'])),
]);
