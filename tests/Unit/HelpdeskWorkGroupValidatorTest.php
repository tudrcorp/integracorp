<?php

declare(strict_types=1);

use App\Support\HelpdeskWorkGroupValidator;

it('rechaza grupo sin nombre', function (): void {
    $result = HelpdeskWorkGroupValidator::validateForUpdate([
        'name' => '   ',
        'status' => 'ACTIVO',
        'team_colaborador_ids' => [1, 2],
    ]);

    expect($result->valid)->toBeFalse()
        ->and($result->errorTitle)->toBe('Nombre requerido');
});

it('rechaza grupo con menos de dos integrantes', function (): void {
    $result = HelpdeskWorkGroupValidator::validateForUpdate([
        'name' => 'Mesa TI',
        'status' => 'ACTIVO',
        'team_colaborador_ids' => [1],
    ]);

    expect($result->valid)->toBeFalse()
        ->and($result->errorTitle)->toBe('Integrantes insuficientes');
});

it('registra accion editar grupo en el trait HelpdeskWorkGroupValidatorTest listado', function (): void {
    $traitPath = dirname(__DIR__, 2).'/app/Filament/Concerns/ManagesHelpdeskWorkGroupsOnList.php';
    $formPath = dirname(__DIR__, 2).'/app/Support/HelpdeskWorkGroupFormSchema.php';
    $modalPath = dirname(__DIR__, 2).'/resources/views/filament/helpdesks/work-groups-modal.blade.php';

    expect(file_get_contents($traitPath))
        ->toContain('editHelpdeskWorkGroupAction')
        ->toContain('mountEditHelpdeskWorkGroup')
        ->toContain('HelpdeskWorkGroupValidator::validateForUpdate');

    expect(file_get_contents($formPath))
        ->toContain('editFormComponents')
        ->toContain("Select::make('team_colaborador_ids')");

    expect(file_get_contents($modalPath))
        ->toContain('mountEditHelpdeskWorkGroup')
        ->toContain('Editar grupo');
});
