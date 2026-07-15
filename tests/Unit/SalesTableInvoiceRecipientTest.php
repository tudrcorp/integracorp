<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\Sales\Tables\SalesTable;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Sale;

uses(Tests\TestCase::class);

it('la accion generar factura incluye opciones de titular, tomador y personalizada', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Tables/SalesTable.php');

    expect($source)
        ->toContain("Radio::make('invoice_in_name_of')")
        ->toContain("'titular' => 'A nombre del Titular'")
        ->toContain("'tomador' => 'A nombre del Tomador'")
        ->toContain("'custom' => 'Factura personalizada'")
        ->toContain('resolveInvoiceBillingParty');
});

it('resuelve datos de factura a nombre del titular individual', function (): void {
    $sale = new Sale([
        'affiliate_full_name' => 'JUAN TITULAR',
        'affiliate_ci_rif' => '12345678',
        'type' => 'AFILIACION INDIVIDUAL',
    ]);

    $affiliation = new Affiliation([
        'full_name_ti' => 'JUAN TITULAR',
        'nro_identificacion_ti' => '12345678',
        'adress_ti' => 'Calle 1',
        'phone_ti' => '04141234567',
        'email_ti' => 'titular@example.com',
        'full_name_payer' => 'PEDRO TOMADOR',
        'nro_identificacion_payer' => '87654321',
        'phone_payer' => '04147654321',
        'email_payer' => 'tomador@example.com',
    ]);

    expect(SalesTable::resolveInvoiceBillingParty('titular', $sale, $affiliation))
        ->toMatchArray([
            'full_name_ti' => 'JUAN TITULAR',
            'ci_rif_ti' => '12345678',
            'address_ti' => 'Calle 1',
            'phone_ti' => '04141234567',
            'email_ti' => 'titular@example.com',
        ]);
});

it('resuelve datos de factura a nombre del tomador individual', function (): void {
    $sale = new Sale([
        'affiliate_full_name' => 'JUAN TITULAR',
        'affiliate_ci_rif' => '12345678',
        'type' => 'AFILIACION INDIVIDUAL',
    ]);

    $affiliation = new Affiliation([
        'full_name_ti' => 'JUAN TITULAR',
        'nro_identificacion_ti' => '12345678',
        'adress_ti' => 'Calle 1',
        'phone_ti' => '04141234567',
        'email_ti' => 'titular@example.com',
        'full_name_payer' => 'PEDRO TOMADOR',
        'nro_identificacion_payer' => '87654321',
        'phone_payer' => '04147654321',
        'email_payer' => 'tomador@example.com',
    ]);

    expect(SalesTable::resolveInvoiceBillingParty('tomador', $sale, $affiliation))
        ->toMatchArray([
            'full_name_ti' => 'PEDRO TOMADOR',
            'ci_rif_ti' => '87654321',
            'address_ti' => 'Calle 1',
            'phone_ti' => '04147654321',
            'email_ti' => 'tomador@example.com',
        ]);
});

it('resuelve datos de factura personalizada', function (): void {
    $sale = new Sale([
        'affiliate_full_name' => 'JUAN TITULAR',
        'affiliate_ci_rif' => '12345678',
    ]);

    expect(SalesTable::resolveInvoiceBillingParty('custom', $sale, null, [
        'custom_full_name' => 'EMPRESA XYZ',
        'custom_ci_rif' => 'J123456789',
        'custom_address' => 'Av. Principal',
        'custom_phone' => '02121234567',
        'custom_email' => 'facturas@xyz.com',
    ]))->toMatchArray([
        'full_name_ti' => 'EMPRESA XYZ',
        'ci_rif_ti' => 'J123456789',
        'address_ti' => 'Av. Principal',
        'phone_ti' => '02121234567',
        'email_ti' => 'facturas@xyz.com',
    ]);
});

it('resuelve datos de factura corporativa a nombre del titular y tomador', function (): void {
    $sale = new Sale([
        'type' => 'AFILIACION CORPORATIVA',
    ]);

    $affiliation = new AffiliationCorporate([
        'name_corporate' => 'CORP SA',
        'rif' => 'J000111222',
        'address' => 'Zona Industrial',
        'phone' => '02120001111',
        'email' => 'corp@example.com',
        'full_name_contact' => 'ANA CONTACTO',
        'nro_identificacion_contact' => '11223344',
        'phone_contact' => '04141112233',
        'email_contact' => 'ana@example.com',
    ]);

    expect(SalesTable::resolveInvoiceBillingParty('titular', $sale, $affiliation))
        ->toMatchArray([
            'full_name_ti' => 'CORP SA',
            'ci_rif_ti' => 'J000111222',
            'address_ti' => 'Zona Industrial',
            'phone_ti' => '02120001111',
            'email_ti' => 'corp@example.com',
        ]);

    expect(SalesTable::resolveInvoiceBillingParty('tomador', $sale, $affiliation))
        ->toMatchArray([
            'full_name_ti' => 'ANA CONTACTO',
            'ci_rif_ti' => '11223344',
            'address_ti' => 'Zona Industrial',
            'phone_ti' => '04141112233',
            'email_ti' => 'ana@example.com',
        ]);
});
