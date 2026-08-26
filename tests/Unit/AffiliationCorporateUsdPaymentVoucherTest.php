<?php

declare(strict_types=1);

use App\Http\Controllers\AffiliationCorporateController;

it('reconoce transferencia US$ y link de pago como metodos USD corporativos', function (): void {
    expect(AffiliationCorporateController::isUsdPaymentMethod('EFECTIVO US$'))->toBeTrue()
        ->and(AffiliationCorporateController::isUsdPaymentMethod('ZELLE'))->toBeTrue()
        ->and(AffiliationCorporateController::isUsdPaymentMethod('TRANSFERENCIA US$'))->toBeTrue()
        ->and(AffiliationCorporateController::isUsdPaymentMethod('LINK DE PAGO'))->toBeTrue()
        ->and(AffiliationCorporateController::isUsdPaymentMethod('TRANSFERENCIA VES'))->toBeFalse()
        ->and(AffiliationCorporateController::isUsdPaymentMethod('PAGO MOVIL VES'))->toBeFalse()
        ->and(AffiliationCorporateController::isUsdPaymentMethod('MULTIPLE'))->toBeFalse()
        ->and(AffiliationCorporateController::isUsdPaymentMethod(null))->toBeFalse();
});

it('persiste metodos USD corporativos en las cuatro frecuencias y no finge exito', function (): void {
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/AffiliationCorporateController.php');

    expect(substr_count($controller, 'self::isUsdPaymentMethod($data[\'payment_method\'] ?? null)'))->toBe(4)
        ->and($controller)->toContain("'TRANSFERENCIA US$'")
        ->and($controller)->toContain("'LINK DE PAGO'")
        ->and($controller)->toContain('unsupported_payment_method_or_frequency')
        ->and($controller)->toContain('return false;')
        ->and($controller)->not->toContain('dd($th)')
        ->and($controller)->toContain("'date_payment_voucher' => \$data['date_payment_voucher'] ?? null");
});

it('muestra error visible en administracion si el comprobante corporativo no se guarda', function (): void {
    $table = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php'
    );

    expect($table)
        ->toContain("->title('No se registró el comprobante')")
        ->toContain('controller_returned_false')
        ->and(substr_count($table, "->title('No se registró el comprobante')"))->toBe(2);
});

it('no envia correo de exito cuando falla la carga en paneles comerciales', function (): void {
    $root = dirname(__DIR__, 2);
    $panels = [
        $root.'/app/Filament/Agents/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php',
        $root.'/app/Filament/Master/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php',
        $root.'/app/Filament/General/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php',
        $root.'/app/Filament/Business/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php',
    ];

    foreach ($panels as $path) {
        $contents = file_get_contents($path);

        expect($contents)
            ->toContain("->title('No se registró el comprobante')")
            ->toContain('Mail::to($info[\'email\'])->send(new UploadPayment($info))');

        $successStart = strpos($contents, 'if ($upload)');
        $mailPos = strpos($contents, 'Mail::to($info[\'email\'])->send(new UploadPayment($info))');
        $errorPos = strpos($contents, "->title('No se registró el comprobante')");

        expect($successStart)->not->toBeFalse()
            ->and($mailPos)->toBeGreaterThan($successStart)
            ->and($errorPos)->toBeGreaterThan($mailPos);
    }
});
