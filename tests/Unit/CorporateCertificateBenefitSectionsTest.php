<?php

declare(strict_types=1);

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Support\AffiliationCorporates\CorporateCertificateBenefitSections;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/** Los planes de prueba se crean dentro de una transacción que siempre se revierte. */
beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

function crearPlanCertificado(string $descripcion, array $beneficios, bool $requiereNota = false): int
{
    $planId = DB::table('plans')->insertGetId([
        'code' => 'TEST-'.Illuminate\Support\Str::random(8),
        'description' => $descripcion,
        'status' => 'ACTIVO',
        'created_by' => 'tests',
        'requires_preexistence_note' => $requiereNota,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach ($beneficios as $beneficio) {
        $benefitId = DB::table('benefits')->insertGetId([
            'code' => 'TESTB-'.Illuminate\Support\Str::random(8),
            'description' => $beneficio,
            'status' => 'ACTIVO',
            'created_by' => 'tests',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('benefit_plans')->insert([
            'benefit_id' => $benefitId,
            'plan_id' => $planId,
            'description' => $beneficio,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return $planId;
}

function corporativaConAfiliados(array $planIdsPorAfiliado): AffiliationCorporate
{
    $record = new AffiliationCorporate(['code' => 'TEST-COR-0001']);

    $afiliados = collect($planIdsPorAfiliado)->map(function (?int $planId, int $i): AffiliateCorporate {
        $afiliado = new AffiliateCorporate;
        $afiliado->forceFill([
            'id' => 900 + $i,
            'first_name' => 'Afiliado',
            'last_name' => (string) $i,
            'plan_id' => $planId,
        ]);

        return $afiliado;
    })->values();

    $record->setRelation('corporateAffiliates', $afiliados);
    $record->setRelation('affiliationCorporatePlans', collect());

    return $record;
}

it('emite una sección de beneficios por cada plan presente en la población', function (): void {
    $planA = crearPlanCertificado('PLAN PRUEBA A', ['BENEFICIO A1', 'BENEFICIO A2']);
    $planB = crearPlanCertificado('PLAN PRUEBA B', ['BENEFICIO B1']);

    $record = corporativaConAfiliados([$planA, $planB, $planA]);

    $sections = CorporateCertificateBenefitSections::forAffiliation($record, false);

    expect($sections)->toHaveCount(2)
        ->and($sections[0]['plan_label'])->toBe('PLAN PRUEBA A')
        ->and($sections[0]['rows'])->toHaveCount(2)
        ->and($sections[1]['plan_label'])->toBe('PLAN PRUEBA B')
        ->and($sections[1]['rows'])->toHaveCount(1);
});

it('adjunta la nota de preexistencias solo al plan que la declara en base de datos', function (): void {
    $conNota = crearPlanCertificado('PLAN CON NOTA', ['BENEFICIO X'], requiereNota: true);
    $sinNota = crearPlanCertificado('PLAN SIN NOTA', ['BENEFICIO Y']);

    $sections = CorporateCertificateBenefitSections::forAffiliation(
        corporativaConAfiliados([$conNota, $sinNota]),
        false,
    );

    expect($sections[0]['note'])->toBe(CorporateCertificateBenefitSections::PREEXISTENCE_NOTE)
        ->and($sections[1]['note'])->toBeNull();
});

it('muestra el monto de cobertura solo cuando hay cobertura contratada', function (): void {
    $planId = crearPlanCertificado('PLAN COBERTURA', [
        'EMERGENCIAS MÉDICAS POR PATOLOGIAS LISTADAS',
        'LABORATORIOS A DOMICILIO',
    ]);

    $sinCobertura = CorporateCertificateBenefitSections::forAffiliation(corporativaConAfiliados([$planId]), false);
    $conCobertura = CorporateCertificateBenefitSections::forAffiliation(corporativaConAfiliados([$planId]), true);

    expect(array_column($sinCobertura[0]['rows'], 'show_cobertura'))->toBe([false, false])
        ->and(array_column($conCobertura[0]['rows'], 'show_cobertura'))->toBe([true, false]);
});

it('etiqueta al afiliado sin plan propio con el plan contratado', function (): void {
    $planId = crearPlanCertificado('PLAN UNICO', ['BENEFICIO Z']);
    $nombres = [$planId => 'PLAN UNICO'];

    expect(CorporateCertificateBenefitSections::planLabelForAffiliate($planId, $nombres, 'PLAN CONTRATADO'))
        ->toBe('PLAN UNICO')
        ->and(CorporateCertificateBenefitSections::planLabelForAffiliate(null, $nombres, 'PLAN CONTRATADO'))
        ->toBe('PLAN CONTRATADO')
        ->and(CorporateCertificateBenefitSections::planLabelForAffiliate(999999, $nombres, 'PLAN CONTRATADO'))
        ->toBe('PLAN CONTRATADO');
});

it('ignora planes sin beneficios en vez de imprimir una sección vacía', function (): void {
    $conBeneficios = crearPlanCertificado('PLAN CON BENEFICIOS', ['BENEFICIO 1']);
    $vacio = crearPlanCertificado('PLAN VACIO', []);

    $sections = CorporateCertificateBenefitSections::forAffiliation(
        corporativaConAfiliados([$conBeneficios, $vacio]),
        false,
    );

    expect($sections)->toHaveCount(1)
        ->and($sections[0]['plan_label'])->toBe('PLAN CON BENEFICIOS');
});
