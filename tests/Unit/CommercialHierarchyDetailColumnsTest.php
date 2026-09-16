<?php

declare(strict_types=1);

use App\Models\Agency;
use App\Models\Agent;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use App\Services\CommercialHierarchyExportService;
use App\Support\CommercialStructure\CommercialHierarchyDetailColumns;

uses(Tests\TestCase::class);

/**
 * Las relaciones se inyectan a mano: el objetivo es validar el diagnóstico de completitud,
 * no tocar la base de datos.
 */
function conUbicacion(Agency|Agent $record): Agency|Agent
{
    $record->setRelation('country', new Country(['name' => 'VENEZUELA']));
    $record->setRelation('state', new State(['definition' => 'MIRANDA']));
    $record->setRelation('city', new City(['definition' => 'CARACAS']));
    $record->setRelation('region', new \App\Models\Region(['definition' => 'CAPITAL']));

    return $record;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function agenteDePrueba(array $overrides = []): Agent
{
    return conUbicacion(new Agent(array_merge([
        'name' => 'ANA HIDALGO',
        'ci' => '12345678',
        'rif' => 'V-12345678-9',
        'email' => 'ana@ejemplo.com',
        'phone' => '+584141234567',
        'address' => 'AV. PRINCIPAL',
        'local_beneficiary_name' => 'ANA HIDALGO',
        'local_beneficiary_rif' => 'V-12345678-9',
        'local_beneficiary_account_number' => '01340173071732046689',
        'local_beneficiary_account_bank' => 'BANESCO',
        'local_beneficiary_account_type' => 'AHORRO',
    ], $overrides)));
}

it('marca como completo al registro con identidad, contacto, ubicación y banco', function () {
    $audit = CommercialHierarchyDetailColumns::completeness(agenteDePrueba());

    expect($audit['complete'])->toBeTrue()
        ->and($audit['percentage'])->toBe(100)
        ->and($audit['missing'])->toBe([])
        ->and($audit['missing_groups'])->toBe([]);
});

it('nombra en español cada campo obligatorio que falta y su grupo', function () {
    $agent = agenteDePrueba([
        'rif' => null,
        'phone' => '',
        'local_beneficiary_account_number' => null,
    ]);

    $audit = CommercialHierarchyDetailColumns::completeness($agent);

    expect($audit['complete'])->toBeFalse()
        ->and($audit['missing'])->toBe(['RIF', 'Teléfono', 'Nº de cuenta'])
        ->and($audit['missing_groups'])->toBe(['Identificación', 'Contacto', 'Datos bancarios'])
        // 11 de los 14 campos obligatorios están llenos.
        ->and($audit['percentage'])->toBe(79);
});

it('cuenta la ubicación por el nombre de la relación, no por el id suelto', function () {
    $sinUbicacion = agenteDePrueba();
    $sinUbicacion->setRelation('city', null);

    $audit = CommercialHierarchyDetailColumns::completeness($sinUbicacion);

    expect($audit['missing'])->toBe(['Ciudad'])
        ->and($audit['missing_groups'])->toBe(['Ubicación']);
});

it('valida la agencia por su cédula de responsable y su razón social', function () {
    $agency = conUbicacion(new Agency([
        'name_corporative' => 'YV SOLUTIONS',
        'ci_responsable' => '6302675',
        'rif' => 'J-63026751',
        'email' => 'info@ejemplo.com',
        'phone' => '+584141234567',
        'address' => 'AV. PRINCIPAL',
        'local_beneficiary_name' => 'VIVIANNE CASTILLO',
        'local_beneficiary_rif' => '6302675',
        'local_beneficiary_account_number' => '01340173071732046689',
        'local_beneficiary_account_bank' => 'BANESCO',
        'local_beneficiary_account_type' => 'AHORRO',
    ]));

    expect(CommercialHierarchyDetailColumns::completeness($agency)['complete'])->toBeTrue();

    $agency->ci_responsable = null;

    expect(CommercialHierarchyDetailColumns::completeness($agency)['missing'])->toBe(['Cédula']);
});

it('emite una fila del mismo ancho y marcada como No aplica cuando el nodo no tiene ficha', function () {
    $values = CommercialHierarchyDetailColumns::values(null);

    expect($values)->toHaveCount(count(CommercialHierarchyDetailColumns::headers()))
        ->and($values[0])->toBe('No aplica');
});

it('exporta los datos propios de cada tipo sin desalinear las columnas', function () {
    $agentValues = CommercialHierarchyDetailColumns::values(agenteDePrueba(['sex' => 'FEMENINO']));
    $agencyValues = CommercialHierarchyDetailColumns::values(conUbicacion(new Agency([
        'name_corporative' => 'YV SOLUTIONS',
        'name_representative' => 'VIVIANNE CASTILLO',
    ])));

    $headers = CommercialHierarchyDetailColumns::headers();
    $index = fn (string $header): int => (int) array_search($header, $headers, true);

    expect($agentValues)->toHaveCount(count($headers))
        ->and($agencyValues)->toHaveCount(count($headers))
        // El representante legal solo existe en la agencia; el sexo solo en el agente.
        ->and($agencyValues[$index('Representante legal')])->toBe('VIVIANNE CASTILLO')
        ->and($agentValues[$index('Representante legal')])->toBe('')
        ->and($agentValues[$index('Sexo')])->toBe('FEMENINO')
        ->and($agencyValues[$index('Sexo')])->toBe('');
});

it('reporta los adjuntos como presencia y no como ruta interna', function () {
    $headers = CommercialHierarchyDetailColumns::headers();
    $index = (int) array_search('Doc. CI/RIF', $headers, true);

    $conDocumento = CommercialHierarchyDetailColumns::values(agenteDePrueba(['file_ci_rif' => 'agents/ci/123.pdf']));
    $sinDocumento = CommercialHierarchyDetailColumns::values(agenteDePrueba());

    expect($conDocumento[$index])->toBe('Cargado')
        ->and($sinDocumento[$index])->toBe('Faltante');
});

it('la hoja de jerarquía arranca con la estructura y sigue con el diagnóstico y la ficha', function () {
    $headers = CommercialHierarchyExportService::headers();

    expect(array_slice($headers, 0, 9))->toBe([
        'Nivel', 'Jerarquía', 'Tipo', 'Código', 'Nombre', 'Estatus', 'Depende de', 'Ruta jerárquica', 'Estructura',
    ])
        ->and($headers[9])->toBe('Información completa')
        ->and($headers)->toContain('Campos faltantes')
        ->and($headers)->toContain('Nat. nº cuenta')
        ->and($headers)->toContain('Comisión TDEC')
        ->and($headers)->toContain('Doc. W8/W9')
        ->and(count($headers))->toBe(9 + count(CommercialHierarchyDetailColumns::headers()));
});
