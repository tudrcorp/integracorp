<?php

declare(strict_types=1);

use App\Http\Controllers\NotificationController;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Sale;
use App\Support\Filament\Administration\SaleReciboPagoWhatsAppRecipients;

uses(Tests\TestCase::class);

it('resuelve telefonos del agente agencia y sol rodriguez', function (): void {
    $sale = new Sale([
        'agent_id' => 10,
        'code_agency' => 'AC-001',
        'invoice_number' => 'INV-WA-TEST',
    ]);

    $sale->setRelation('agent', new Agent([
        'name' => 'Agente Demo',
        'phone' => '0424-1112233',
    ]));

    $sale->setRelation('agency', new Agency([
        'code' => 'AC-001',
        'name_corporative' => 'Agencia Demo',
        'phone' => '0212-5556677',
    ]));

    $recipients = SaleReciboPagoWhatsAppRecipients::resolve($sale);

    expect($recipients['agent']['phone'])->toBe('04241112233')
        ->and($recipients['agency']['phone'])->toBe('02125556677')
        ->and($recipients['targets'])->toHaveCount(3)
        ->and(collect($recipients['targets'])->pluck('phone')->all())->toContain('04143027250');
});

it('incluye siempre el telefono de sol rodriguez aunque falten agente y agencia', function (): void {
    $sale = new Sale([
        'invoice_number' => 'INV-WA-SOL',
    ]);

    $recipients = SaleReciboPagoWhatsAppRecipients::resolve($sale);

    expect($recipients['has_recipients'])->toBeTrue()
        ->and($recipients['targets'])->toHaveCount(1)
        ->and($recipients['targets'][0]['phone'])->toBe('04143027250')
        ->and($recipients['targets'][0]['name'])->toBe('Sol Rodriguez');
});

it('expone el metodo de envio de documento por whatsapp para recibos de pago', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/NotificationController.php');

    expect(method_exists(NotificationController::class, 'sendReciboDePago'))->toBeTrue()
        ->and($source)->toContain('/reciboDePago/')
        ->and($source)->toContain('RDP-');
});

it('expone la accion de whatsapp en view sale y sales table', function (): void {
    $viewSaleSource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Pages/ViewSale.php');
    $salesTableSource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Tables/SalesTable.php');

    $testFormSource = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/Administration/SaleReciboPagoTestDeliveryForm.php');

    expect($viewSaleSource)->toContain('sendReciboPagoDeliveryAction')
        ->and($salesTableSource)
        ->toContain('SaleReciboPagoWhatsAppRecipients::send')
        ->toContain('sendReciboPagoDeliveryAction')
        ->toContain('SaleReciboPagoTestDeliveryForm::unifiedActionSchema')
        ->toContain('normalizeWhatsAppFormData')
        ->and($testFormSource)
        ->toContain('recibo-pago-whatsapp-modal')
        ->toContain('use_test_delivery');
});
