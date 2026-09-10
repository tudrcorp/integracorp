<?php

declare(strict_types=1);

use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Support\Affiliations\WelcomeKitAttachments;

uses(Tests\TestCase::class);

/**
 * Solo se leen datos y se crean archivos temporales en el disco público, que se borran al
 * terminar: ninguna prueba de este archivo escribe en base de datos.
 */
function welcomeKitAffiliation(string $code, bool $titularEnAfiliados = true): Affiliation
{
    $affiliation = new Affiliation([
        'code' => $code,
        'plan_id' => 3,
    ]);
    $affiliation->forceFill([
        'nro_identificacion_ti' => 'V-12345678',
        'code_agency' => null,
    ]);

    $affiliate = new Affiliate;
    $affiliate->forceFill([
        'id' => 440,
        'nro_identificacion' => $titularEnAfiliados ? 'V-12345678' : 'V-99999999',
    ]);

    $affiliation->setRelation('affiliates', collect([$affiliate]));

    return $affiliation;
}

function welcomeKitTouch(string $relativePath): string
{
    $path = public_path('storage/'.$relativePath);

    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }

    file_put_contents($path, '%PDF-1.4 test');

    return $path;
}

afterEach(function (): void {
    foreach (glob(public_path('storage/tarjeta-afiliacion/TAR-TEST-KIT-*.pdf')) ?: [] as $file) {
        @unlink($file);
    }

    foreach (glob(public_path('storage/certificados-doc/CER-TEST-KIT-*.pdf')) ?: [] as $file) {
        @unlink($file);
    }
});

it('resuelve la tarjeta con el nombre por afiliado, que es el que se genera hoy', function (): void {
    $affiliation = welcomeKitAffiliation('TEST-KIT-001');

    welcomeKitTouch('certificados-doc/CER-TEST-KIT-001.pdf');
    $tarjeta = welcomeKitTouch('tarjeta-afiliacion/TAR-TEST-KIT-001-440.pdf');

    $kit = WelcomeKitAttachments::forAffiliation($affiliation);

    expect($kit->cardPath)->toBe($tarjeta)
        ->and($kit->isComplete())->toBeTrue()
        ->and($kit->paths())->toHaveCount(3)
        ->and($kit->filenames())->toContain('TAR-TEST-KIT-001-440.pdf');
});

it('acepta la tarjeta con el nombre legado cuando es la que existe', function (): void {
    $affiliation = welcomeKitAffiliation('TEST-KIT-002', titularEnAfiliados: false);

    welcomeKitTouch('certificados-doc/CER-TEST-KIT-002.pdf');
    $tarjeta = welcomeKitTouch('tarjeta-afiliacion/TAR-TEST-KIT-002.pdf');

    $kit = WelcomeKitAttachments::forAffiliation($affiliation);

    expect($kit->cardPath)->toBe($tarjeta)
        ->and($kit->isComplete())->toBeTrue();
});

it('detecta la tarjeta faltante en vez de dejar que el envío muera en la cola', function (): void {
    $affiliation = welcomeKitAffiliation('TEST-KIT-003');

    welcomeKitTouch('certificados-doc/CER-TEST-KIT-003.pdf');

    $kit = WelcomeKitAttachments::forAffiliation($affiliation);

    expect($kit->cardPath)->toBeNull()
        ->and($kit->isComplete())->toBeFalse()
        ->and($kit->missingLabels())->toBe(['la tarjeta del titular'])
        ->and($kit->missingSummary())->toBe('Falta la tarjeta del titular.')
        ->and($kit->paths())->not->toContain(null);
});

it('enumera en español todos los documentos que faltan', function (): void {
    $kit = WelcomeKitAttachments::forAffiliation(welcomeKitAffiliation('TEST-KIT-004'));

    expect($kit->missingLabels())->toBe([
        'el certificado de afiliación',
        'la tarjeta del titular',
    ])
        ->and($kit->missingSummary())->toBe('Faltan el certificado de afiliación y la tarjeta del titular.');
});

it('el mailable solo adjunta archivos existentes y ya no arma la ruta a ciegas', function (): void {
    $src = (string) file_get_contents(dirname(__DIR__, 2).'/app/Mail/SendMailKitBienvenida.php');

    expect($src)
        ->toContain('public array $attachmentPaths')
        ->and($src)->toContain('is_file($path)')
        ->and($src)->toContain('private function legacyAttachmentPaths()')
        ->and($src)->toContain('public function failed(\Throwable $exception): void');
});

it('el controlador verifica el kit antes de actuar y envía sin cola para confirmar el resultado', function (): void {
    $src = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/AffiliationController.php');

    expect($src)
        ->toContain('WelcomeKitAttachments::forAffiliation($record)')
        ->and($src)->toContain('if (! $kit->isComplete())')
        ->and($src)->toContain('El kit está incompleto')
        ->and($src)->toContain('->sendNow(new SendMailKitBienvenida(')
        ->and($src)->toContain('$kit->paths()')
        ->and($src)->not->toContain('->send(new SendMailKitBienvenida($code, $condicionado))');
});

it('la acción de Filament audita según el resultado real de la operación', function (): void {
    $src = (string) file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Tables/AffiliationsTable.php'
    );

    expect($src)
        ->toContain('$sent = AffiliationController::downloadResendKit($record, $data) === true;')
        ->toContain("? 'AUDIT_BUSINESS_AFFILIATION_WELCOME_KIT_RESENT'")
        ->toContain(": 'AUDIT_BUSINESS_AFFILIATION_WELCOME_KIT_FAILED'")
        ->toContain("if (! is_string(\$path) || \$path === '')");
});
