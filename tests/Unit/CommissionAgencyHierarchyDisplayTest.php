<?php

declare(strict_types=1);

use App\Models\Agency;
use App\Models\Commission;

it('muestra master y general cuando el pago pertenece a una agencia general', function (): void {
    $master = new Agency([
        'code' => 'MST-001',
        'name_corporative' => 'Best Insurance Advisors International',
        'agency_type_id' => 1,
    ]);

    $general = new Agency([
        'code' => 'GEN-001',
        'name_corporative' => 'Preventa Global Asset',
        'agency_type_id' => 3,
        'owner_code' => 'MST-001',
    ]);
    $general->setRelation('masterAgency', $master);

    $commission = new Commission(['code_agency' => 'GEN-001']);
    $commission->setRelation('agency', $general);

    expect($commission->masterAgencyDisplayName())->toBe('Best Insurance Advisors International')
        ->and($commission->generalAgencyDisplayName())->toBe('Preventa Global Asset');
});

it('muestra master y guion en general cuando el pago pertenece a una agencia master', function (): void {
    $master = new Agency([
        'code' => 'MST-002',
        'name_corporative' => 'Dayanis Moreno',
        'agency_type_id' => 1,
        'owner_code' => 'TDG-100',
    ]);

    $commission = new Commission(['code_agency' => 'MST-002']);
    $commission->setRelation('agency', $master);

    expect($commission->masterAgencyDisplayName())->toBe('Dayanis Moreno')
        ->and($commission->generalAgencyDisplayName())->toBe('-');
});

it('muestra guion en master cuando la general no tiene agencia master valida', function (): void {
    $general = new Agency([
        'code' => 'GEN-002',
        'name_corporative' => 'Agencia Sin Master',
        'agency_type_id' => 3,
        'owner_code' => 'TDG-100',
    ]);
    $general->setRelation('masterAgency', new Agency([
        'code' => 'TDG-100',
        'name_corporative' => 'TUDRENCASA',
        'agency_type_id' => 99,
    ]));

    $commission = new Commission(['code_agency' => 'GEN-002']);
    $commission->setRelation('agency', $general);

    expect($commission->masterAgencyDisplayName())->toBe('-')
        ->and($commission->generalAgencyDisplayName())->toBe('Agencia Sin Master');
});

it('muestra tudrencasa como master cuando code_agency es TDG-100 sin agencia cargada', function (): void {
    $commission = new Commission(['code_agency' => 'TDG-100']);
    $commission->setRelation('agency', null);

    expect($commission->masterAgencyDisplayName())->toBe('TUDRENCASA')
        ->and($commission->generalAgencyDisplayName())->toBe('-');
});
