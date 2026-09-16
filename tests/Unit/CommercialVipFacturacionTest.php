<?php

declare(strict_types=1);

use App\Support\CommercialStructure\CommercialVipFacturacion;

it('calcula estrellas VIP según rangos de facturación acumulada', function (): void {
    expect(CommercialVipFacturacion::starCountFromAmount(0))->toBe(0)
        ->and(CommercialVipFacturacion::starCountFromAmount(999.99))->toBe(0)
        ->and(CommercialVipFacturacion::starCountFromAmount(1_000))->toBe(1)
        ->and(CommercialVipFacturacion::starCountFromAmount(9_999.99))->toBe(1)
        ->and(CommercialVipFacturacion::starCountFromAmount(10_000))->toBe(2)
        ->and(CommercialVipFacturacion::starCountFromAmount(99_999.99))->toBe(2)
        ->and(CommercialVipFacturacion::starCountFromAmount(100_000))->toBe(3)
        ->and(CommercialVipFacturacion::starCountFromAmount(499_999.99))->toBe(3)
        ->and(CommercialVipFacturacion::starCountFromAmount(500_000))->toBe(4)
        ->and(CommercialVipFacturacion::starCountFromAmount(999_999.99))->toBe(4)
        ->and(CommercialVipFacturacion::starCountFromAmount(1_000_000))->toBe(5)
        ->and(CommercialVipFacturacion::starCountFromAmount(5_000_000))->toBe(5);
});

it('las tablas de negocios muestran VIP de facturación en agentes y agencias', function (): void {
    $agents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Tables/AgentsTable.php');
    $agencies = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Tables/AgenciesTable.php');

    expect($agents)->toContain('CommercialVipFacturacion::appendAgentBillingSubquery')
        ->and($agents)->toContain("->label('VIP (facturación)')")
        ->and($agencies)->toContain('CommercialVipFacturacion::appendAgencyBillingSubquery')
        ->and($agencies)->toContain("->label('VIP (facturación)')");
});
