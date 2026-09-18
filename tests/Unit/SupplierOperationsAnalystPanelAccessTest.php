<?php

declare(strict_types=1);

use App\Models\Supplier;
use App\Models\User;
use Filament\Panel;

uses(Tests\TestCase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function makeSupplierUserForPanelAccess(array $attributes, bool $gestionIntegracorp = true): User
{
    $user = new User(array_merge([
        'name' => 'Analista Proveedor',
        'email' => 'analista@proveedorexterno.com',
        'status' => 'ACTIVO',
        'departament' => ['OPERACIONES'],
        'supplier_id' => 15,
        'is_proveedor_amd' => true,
    ], $attributes));

    $user->setRelation('supplier', new Supplier(['gestion_integracorp' => $gestionIntegracorp]));

    return $user;
}

function panelWithId(string $id): Panel
{
    return Panel::make()->id($id);
}

/** @return list<string> */
function internalPanelIdsExceptOperations(): array
{
    return [
        'admin', 'business', 'administration', 'marketing', 'telemedicina',
        'agents', 'master', 'general', 'projects', 'metrics',
    ];
}

it('permite al analista de proveedor entrar a operaciones sin correo @tudrencasa.com', function (): void {
    $analyst = makeSupplierUserForPanelAccess([]);

    expect($analyst->isSupplierOperationsAnalyst())->toBeTrue()
        ->and($analyst->canAccessPanel(panelWithId('operations')))->toBeTrue();
});

it('bloquea al analista de proveedor en cualquier otro panel', function (string $panelId): void {
    $analyst = makeSupplierUserForPanelAccess([
        'email' => 'analistauno@tudrencasa.com',
        'is_admin' => true,
        'is_agent' => true,
        'is_agency' => true,
        'agency_type' => 'MASTER',
        'departament' => ['OPERACIONES', 'NEGOCIOS', 'SUPERADMIN', 'METRICAS', 'TELEMEDICINA'],
    ]);

    expect($analyst->canAccessPanel(panelWithId($panelId)))->toBeFalse();
})->with(internalPanelIdsExceptOperations());

it('niega operaciones al analista inactivo', function (): void {
    $analyst = makeSupplierUserForPanelAccess(['status' => 'INACTIVO']);

    expect($analyst->canAccessPanel(panelWithId('operations')))->toBeFalse();
});

it('niega operaciones cuando el proveedor tiene la gestión Integracorp apagada', function (): void {
    $analyst = makeSupplierUserForPanelAccess([], gestionIntegracorp: false);

    expect($analyst->canAccessPanel(panelWithId('operations')))->toBeFalse();
});

it('no trata como analista al médico del proveedor y le conserva telemedicina', function (): void {
    $doctor = makeSupplierUserForPanelAccess([
        'email' => 'doctor1@pruebacom',
        'departament' => ['TELEMEDICINA'],
        'is_proveedor_amd' => false,
        'doctor_id' => 3,
    ]);

    expect($doctor->isSupplierOperationsAnalyst())->toBeFalse()
        ->and($doctor->canAccessPanel(panelWithId('telemedicina')))->toBeTrue()
        ->and($doctor->canAccessPanel(panelWithId('operations')))->toBeTrue();
});

it('no aplica el candado a los analistas internos sin proveedor', function (): void {
    $internal = new User([
        'name' => 'Analista TDG',
        'email' => 'analista@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['NEGOCIOS'],
    ]);

    expect($internal->isSupplierOperationsAnalyst())->toBeFalse()
        ->and($internal->canAccessPanel(panelWithId('business')))->toBeTrue();
});
