<?php

declare(strict_types=1);

use App\Models\CompanyAssociate;
use App\Models\CompanyResponsible;
use App\Support\Companies\CompanyAssociateDocumentsDeliverer;

uses(Tests\TestCase::class);

/**
 * @return array<string, string>
 */
function invokeCompanyAssociateDocumentsDelivererPrivateMethod(string $method, CompanyAssociate $associate, bool $includeAnalystRecipients = true): array
{
    $reflection = new \ReflectionMethod(CompanyAssociateDocumentsDeliverer::class, $method);
    $reflection->setAccessible(true);

    /** @var array<string, string> $result */
    $result = $reflection->invoke(null, $associate, $includeAnalystRecipients);

    return $result;
}

it('incluye al responsable en copia del correo del asociado', function (): void {
    $associate = new CompanyAssociate([
        'email' => 'asociado@example.com',
        'full_name' => 'Juan Pérez',
    ]);

    $associate->setRelation('responsible', new CompanyResponsible([
        'full_name' => 'María Responsable',
        'email' => 'responsable@example.com',
        'phone' => '04121234567',
    ]));

    expect(invokeCompanyAssociateDocumentsDelivererPrivateMethod('responsibleEmailCc', $associate))
        ->toBe(['responsable@example.com' => 'María Responsable']);
});

it('no incluye analistas en envio manual de documentos', function (): void {
    $associate = new CompanyAssociate([
        'phone' => '04121111111',
        'full_name' => 'Juan Pérez',
    ]);

    expect(invokeCompanyAssociateDocumentsDelivererPrivateMethod('phoneRecipients', $associate, false))
        ->toBe(['04121111111' => 'Juan Pérez']);
});

it('no duplica copia si el responsable usa el mismo correo del asociado', function (): void {
    $associate = new CompanyAssociate([
        'email' => 'mismo@example.com',
        'full_name' => 'Juan Pérez',
    ]);

    $associate->setRelation('responsible', new CompanyResponsible([
        'full_name' => 'María Responsable',
        'email' => 'mismo@example.com',
    ]));

    expect(invokeCompanyAssociateDocumentsDelivererPrivateMethod('responsibleEmailCc', $associate))
        ->toBe([]);
});

it('incluye el telefono del responsable entre los destinatarios de whatsapp', function (): void {
    $associate = new CompanyAssociate([
        'phone' => '04121111111',
        'full_name' => 'Juan Pérez',
    ]);

    $associate->setRelation('responsible', new CompanyResponsible([
        'full_name' => 'María Responsable',
        'phone' => '04242222222',
    ]));

    expect(invokeCompanyAssociateDocumentsDelivererPrivateMethod('phoneRecipients', $associate))
        ->toBe([
            '04121111111' => 'Juan Pérez',
            '04242222222' => 'María Responsable',
        ]);
});

it('el deliverer referencia copia al responsable y su telefono', function (): void {
    $deliverer = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyAssociateDocumentsDeliverer.php');
    $mail = file_get_contents(dirname(__DIR__, 2).'/app/Mail/CompanyAssociateDocumentsMail.php');

    expect($deliverer)
        ->toContain('responsibleEmailCc')
        ->toContain('$associate->responsible')
        ->toContain('ccRecipients: $ccRecipients');

    expect($mail)
        ->toContain('ccRecipients')
        ->toContain('cc: $cc');
});

it('el correo de documentos incluye logo y diseno mejorado', function (): void {
    $template = file_get_contents(dirname(__DIR__, 2).'/resources/views/mails/company-associate-documents.blade.php');

    expect($template)
        ->toContain("public_path('image/logoNewPdf.png')")
        ->toContain('$message->embed($logoPath)')
        ->toContain('Tu tarjeta de afiliado está lista')
        ->toContain('Resumen del afiliado')
        ->toContain('Documentos adjuntos')
        ->toContain('WhatsApp')
        ->toContain('$associate->full_name')
        ->toContain('$validity[\'desde\']');
});
