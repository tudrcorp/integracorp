<?php

declare(strict_types=1);

use App\Models\Agency;
use App\Models\Agent;
use App\Models\Commission;
use App\Support\CommercialStructure\CommissionReferidorPercentage;

it('usa el porcentaje del referidor asignado al agente', function (): void {
    $referrer = new Agency([
        'is_referidor' => true,
        'referidor_percentage' => '7.50',
    ]);

    $agent = new Agent(['referidor_id' => 10]);
    $agent->setRelation('referidor', $referrer);
    $agent->setRelation('referidorAgent', null);

    $commission = new Commission;
    $commission->setRelation('agent', $agent);
    $commission->setRelation('agency', null);

    expect(CommissionReferidorPercentage::for($commission))->toBe(7.5)
        ->and($commission->referidorPercentage())->toBe(7.5);
});

it('usa el porcentaje del referidor de la agencia si el agente no tiene referidor', function (): void {
    $referrer = new Agent([
        'is_referidor' => true,
        'referidor_percentage' => '4.25',
    ]);

    $agency = new Agency(['referidor_agent_id' => 22]);
    $agency->setRelation('referidorAgent', $referrer);
    $agency->setRelation('referidor', null);

    $agent = new Agent;
    $agent->setRelation('referidor', null);
    $agent->setRelation('referidorAgent', null);

    $commission = new Commission;
    $commission->setRelation('agent', $agent);
    $commission->setRelation('agency', $agency);

    expect(CommissionReferidorPercentage::for($commission))->toBe(4.25);
});

it('prioriza el referidor del agente sobre el de la agencia', function (): void {
    $agentReferrer = new Agency([
        'is_referidor' => true,
        'referidor_percentage' => '12.00',
    ]);
    $agencyReferrer = new Agency([
        'is_referidor' => true,
        'referidor_percentage' => '3.00',
    ]);

    $agent = new Agent(['referidor_id' => 1]);
    $agent->setRelation('referidor', $agentReferrer);
    $agent->setRelation('referidorAgent', null);

    $agency = new Agency(['referidor_id' => 2]);
    $agency->setRelation('referidor', $agencyReferrer);
    $agency->setRelation('referidorAgent', null);

    $commission = new Commission;
    $commission->setRelation('agent', $agent);
    $commission->setRelation('agency', $agency);

    expect(CommissionReferidorPercentage::for($commission))->toBe(12.0);
});

it('devuelve cero si no hay referidor o el referidor no está activo', function (): void {
    $inactive = new Agency([
        'is_referidor' => false,
        'referidor_percentage' => '8.00',
    ]);

    $agency = new Agency(['referidor_id' => 5]);
    $agency->setRelation('referidor', $inactive);
    $agency->setRelation('referidorAgent', null);

    $commission = new Commission;
    $commission->setRelation('agent', null);
    $commission->setRelation('agency', $agency);

    expect(CommissionReferidorPercentage::for($commission))->toBe(0.0);

    $empty = new Commission;
    $empty->setRelation('agent', null);
    $empty->setRelation('agency', null);

    expect(CommissionReferidorPercentage::for($empty))->toBe(0.0);
});

it('muestra la columna % Referidor en el detallado de comisiones de administración', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Commissions/Tables/CommissionsTable.php');

    expect($table)
        ->toContain("ColumnGroup::make('COMISIONES REFERIDOR USD - VES')")
        ->toContain("TextColumn::make('porcent_referidor')")
        ->toContain("TextColumn::make('commission_referidor_usd')")
        ->toContain("TextColumn::make('commission_referidor_ves')")
        ->toContain("->label('% Referidor')")
        ->toContain('CommissionReferidorPercentage::eagerLoadRelations()');
});
