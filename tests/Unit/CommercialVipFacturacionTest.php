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

it('coloca las estrellas VIP arriba del nombre y marca línea directa', function (): void {
    $html = (string) CommercialVipFacturacion::nameWithVipStarsHtml('Agente Demo', 150_000, true);

    expect($html)
        ->toContain('★★★')
        ->toContain('Línea directa')
        ->toContain('Agente Demo')
        ->toContain('flex-col')
        ->and(strpos($html, '★★★'))->toBeLessThan(strpos($html, 'Agente Demo'));
});

it('arma la fila VIP del título de ficha arriba del nombre', function (): void {
    $html = CommercialVipFacturacion::pageTitleVipRowHtml(1_250_000, true);

    expect($html)
        ->toContain('★★★★★')
        ->toContain('Línea directa')
        ->and(CommercialVipFacturacion::pageTitleVipRowHtml(0, false))->toBe('');
});

it('las tablas de negocios muestran VIP de facturación y línea directa en agentes y agencias', function (): void {
    $agents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Tables/AgentsTable.php');
    $agencies = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Tables/AgenciesTable.php');

    expect($agents)->toContain('CommercialVipFacturacion::appendAgentBillingSubquery')
        ->and($agents)->toContain("->label('VIP (facturación)')")
        ->and($agents)->toContain("->label('Línea directa')")
        ->and($agents)->toContain('starsGlyphLine')
        ->and($agents)->not->toContain("->icon('heroicon-s-star')")
        ->and($agents)->not->toContain('nameWithVipStarsHtml')
        ->and($agents)->toContain('orderByPriorityThenCreatedAt')
        ->and($agents)->toContain('constrainAgentToLineaDirecta')
        ->and($agents)->toContain('lineaDirectaFromRecord')
        ->and($agencies)->toContain('CommercialVipFacturacion::appendAgencyBillingSubquery')
        ->and($agencies)->toContain("->label('VIP (facturación)')")
        ->and($agencies)->toContain("->label('Línea directa')")
        ->and($agencies)->toContain('starsGlyphLine')
        ->and($agencies)->not->toContain("->icon('heroicon-s-star')")
        ->and($agencies)->not->toContain('nameWithVipStarsHtml')
        ->and($agencies)->toContain('orderByPriorityThenCreatedAt')
        ->and($agencies)->toContain('constrainAgencyToLineaDirecta');
});

it('las fichas y centros de acción de negocios muestran estrellas arriba del nombre', function (): void {
    $agentView = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Pages/ViewAgent.php');
    $agencyView = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Pages/ViewAgency.php');
    $agentCenter = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/agents/agent-command-center.blade.php');
    $agencyCenter = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/agencies/agency-command-center.blade.php');
    $agentInfolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgentInfolist.php');
    $agencyInfolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgencyInfolist.php');

    expect($agentView)->toContain('pageTitleVipRowHtml')
        ->and($agencyView)->toContain('pageTitleVipRowHtml')
        ->and($agentCenter)->toContain('$vipStarsLine')
        ->and($agentCenter)->toContain('Línea directa')
        ->and($agencyCenter)->toContain('$vipStarsLine')
        ->and($agencyCenter)->toContain('Línea directa')
        ->and($agentInfolist)->toContain("->label('VIP (facturación)')")
        ->and($agentInfolist)->toContain("->label('Línea directa')")
        ->and($agencyInfolist)->toContain("->label('VIP (facturación)')")
        ->and($agencyInfolist)->toContain("->label('Línea directa')");
});
