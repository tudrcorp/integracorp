<?php

declare(strict_types=1);

use App\Models\Agency;
use App\Support\CommercialStructure\AgencyHierarchyCommissionResolver;

uses(Tests\TestCase::class);

it('resuelve al menos la agencia referencia cuando no hay owner_code', function (): void {
    $agency = new Agency([
        'name_corporative' => 'Agencia Demo',
        'agency_type_id' => 2,
        'owner_code' => '',
        'status' => 'ACTIVO',
        'commission_tdec' => 15,
    ]);
    $agency->code = 'TDG-999';

    $resolution = AgencyHierarchyCommissionResolver::resolve($agency);

    expect($resolution['nodes'])->toHaveCount(1)
        ->and($resolution['nodes'][0]['role'])->toBe('Agencia general')
        ->and($resolution['warnings'])->not->toBeEmpty();
});
