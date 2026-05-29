<?php

declare(strict_types=1);

use App\Mail\MailSaleReciboPago;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Sale;
use App\Support\Filament\Administration\SaleReciboPagoEmailRecipients;
use Illuminate\Support\Facades\Mail;

uses(Tests\TestCase::class);

it('resuelve correos del agente y la agencia cuando estan cargados en la venta', function (): void {
    $agent = new Agent([
        'name' => 'Agente Demo',
        'code_agent' => 'AG-001',
        'email' => 'agente@example.com',
    ]);

    $agency = new Agency([
        'code' => 'AC-001',
        'name_corporative' => 'Agencia Demo',
        'email' => 'agencia@example.com',
    ]);

    $sale = new Sale([
        'agent_id' => 10,
        'code_agency' => 'AC-001',
        'invoice_number' => 'INV-EMAIL-TEST',
    ]);

    $sale->setRelation('agent', $agent);
    $sale->setRelation('agency', $agency);

    $recipients = SaleReciboPagoEmailRecipients::resolve($sale);

    expect($recipients['agent']['email'])->toBe('agente@example.com')
        ->and($recipients['agency']['email'])->toBe('agencia@example.com')
        ->and($recipients['emails'])->toBe(['agente@example.com', 'agencia@example.com'])
        ->and($recipients['cc_emails'])->toBe(SaleReciboPagoEmailRecipients::CC_RECIPIENTS)
        ->and($recipients['has_recipients'])->toBeTrue();
});

it('omite correos invalidos cuando faltan destinatarios', function (): void {
    $agent = new Agent([
        'name' => 'Agente Sin Correo',
        'code_agent' => 'AG-002',
        'email' => 'correo-invalido',
    ]);

    $sale = new Sale([
        'agent_id' => 11,
        'code_agency' => 'AC-404',
        'invoice_number' => 'INV-EMAIL-WARN',
    ]);

    $sale->setRelation('agent', $agent);
    $sale->setRelation('agency', null);

    $recipients = SaleReciboPagoEmailRecipients::resolve($sale);

    expect($recipients['emails'])->toBe([])
        ->and($recipients['has_recipients'])->toBeFalse();
});

it('envia el recibo en modo prueba solo al correo indicado', function (): void {
    Mail::fake();

    $directory = public_path('storage/reciboDePago');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $sale = new Sale([
        'invoice_number' => 'INV-EMAIL-TEST-'.uniqid(),
    ]);

    $path = \App\Filament\Administration\Resources\Sales\Tables\SalesTable::reciboPagoPdfPath($sale);
    file_put_contents($path, '%PDF-1.4 test');

    SaleReciboPagoEmailRecipients::send($sale, testMode: true, testEmail: 'prueba@example.com');

    Mail::assertSent(MailSaleReciboPago::class, function (MailSaleReciboPago $mail): bool {
        return $mail->hasTo('prueba@example.com')
            && $mail->ccRecipients === [];
    });

    @unlink($path);
});

it('envia el recibo con copia al equipo interno', function (): void {
    Mail::fake();

    $directory = public_path('storage/reciboDePago');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $sale = new Sale([
        'agent_id' => 10,
        'code_agency' => 'AC-001',
        'invoice_number' => 'INV-SEND-'.uniqid(),
    ]);

    $sale->setRelation('agent', new Agent([
        'name' => 'Agente Demo',
        'email' => 'agente@example.com',
    ]));

    $sale->setRelation('agency', new Agency([
        'code' => 'AC-001',
        'name_corporative' => 'Agencia Demo',
        'email' => 'agencia@example.com',
    ]));

    $path = \App\Filament\Administration\Resources\Sales\Tables\SalesTable::reciboPagoPdfPath($sale);
    file_put_contents($path, '%PDF-1.4 test');

    SaleReciboPagoEmailRecipients::send($sale);

    Mail::assertSent(MailSaleReciboPago::class, function (MailSaleReciboPago $mail) use ($sale): bool {
        return $mail->hasTo('agente@example.com')
            && $mail->hasTo('agencia@example.com')
            && $mail->hasCc('afiliaciones@tudrencasa.com')
            && $mail->hasCc('administracion@tudrencasa.com')
            && $mail->hasCc('solrodriguez@tudrencasa.com')
            && $mail->hasCc('hsanchez@tudrencasa.com')
            && $mail->invoiceNumber === $sale->invoice_number;
    });

    @unlink($path);
});

it('expone la accion de envio de recibo por correo en view sale y sales table', function (): void {
    $viewSaleSource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Pages/ViewSale.php');
    $salesTableSource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Tables/SalesTable.php');

    $testFormSource = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/Administration/SaleReciboPagoTestDeliveryForm.php');

    expect($viewSaleSource)
        ->toContain('sendReciboPagoDeliveryAction')
        ->and($salesTableSource)
        ->toContain('sendReciboPagoEmail')
        ->toContain('sendReciboPagoDeliveryAction')
        ->toContain('SaleReciboPagoTestDeliveryForm::unifiedActionSchema')
        ->and($testFormSource)
        ->toContain('recibo-pago-email-modal')
        ->toContain('use_test_delivery')
        ->toContain('normalizeEmailFormData');
});

it('define formulario compartido de modo prueba para correo y whatsapp', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/Administration/SaleReciboPagoTestDeliveryForm.php');

    expect($source)
        ->toContain('unifiedActionSchema')
        ->toContain('reciboPagoDeliveryTabs')
        ->toContain('use_test_delivery')
        ->toContain('test_email')
        ->toContain('test_phone')
        ->toContain('Interfaz de prueba');
});
