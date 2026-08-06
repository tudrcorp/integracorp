<?php

declare(strict_types=1);

use App\Services\PatientSiniestralidadReportService;
use Tests\TestCase;

uses(TestCase::class);

it('normaliza el top n entre 1 y 500 con default 50', function (): void {
    expect(PatientSiniestralidadReportService::normalizeParams([])['top_n'])->toBe(50)
        ->and(PatientSiniestralidadReportService::normalizeParams(['top_n' => 0])['top_n'])->toBe(1)
        ->and(PatientSiniestralidadReportService::normalizeParams(['top_n' => 999])['top_n'])->toBe(500)
        ->and(PatientSiniestralidadReportService::normalizeParams(['top_n' => 25])['top_n'])->toBe(25);
});

it('rankea por cantidad de siniestros e incluye monto total del paciente', function (): void {
    $rows = PatientSiniestralidadReportService::mapAggregatesToRankedRows(
        [
            [
                'telemedicine_patient_id' => 1,
                'claims_count' => 2,
                'total_bill_price' => 3000.0,
            ],
            [
                'telemedicine_patient_id' => 2,
                'claims_count' => 1,
                'total_bill_price' => 5000.0,
            ],
            [
                'telemedicine_patient_id' => 3,
                'claims_count' => 3,
                'total_bill_price' => 60.0,
            ],
        ],
        [
            1 => [
                'full_name' => 'PACIENTE ALTO',
                'nro_identificacion' => 'V111',
                'code' => 'P-HIGH',
                'type_affiliation' => 'TITULAR',
                'business_unit' => 'Unidad Demo',
            ],
            2 => [
                'full_name' => 'PACIENTE MEDIO',
                'nro_identificacion' => 'V222',
                'code' => 'P-MID',
                'type_affiliation' => 'BENEFICIARIO',
                'business_unit' => 'Unidad Demo',
            ],
            3 => [
                'full_name' => 'PACIENTE FRECUENTE BARATO',
                'nro_identificacion' => 'V333',
                'code' => 'P-FREQ',
                'type_affiliation' => 'EXTERNO',
                'business_unit' => 'Unidad Demo',
            ],
        ],
    );

    $topRows = array_slice($rows, 0, 2);

    expect($rows)->toHaveCount(3)
        ->and($rows[0]['telemedicine_patient_id'])->toBe(3)
        ->and($rows[0]['claims_count'])->toBe(3)
        ->and($rows[0]['total_bill_price'])->toBe(60.0)
        ->and($rows[0]['patient'])->toBe('PACIENTE FRECUENTE BARATO')
        ->and($rows[1]['telemedicine_patient_id'])->toBe(1)
        ->and($rows[1]['claims_count'])->toBe(2)
        ->and($rows[1]['total_bill_price'])->toBe(3000.0)
        ->and($rows[2]['telemedicine_patient_id'])->toBe(2)
        ->and($topRows)->toHaveCount(2)
        ->and($topRows[0]['rank'])->toBe(1);
});

it('guarda y recupera parametros del token de reporte', function (): void {
    $token = PatientSiniestralidadReportService::storeParamsAndGetToken([
        'top_n' => 12,
        'date_from' => '2026-01-01',
        'date_to' => '2026-08-01',
    ]);

    $params = PatientSiniestralidadReportService::pullParamsFromToken($token);

    expect($params)->toBe([
        'top_n' => 12,
        'date_from' => '2026-01-01',
        'date_to' => '2026-08-01',
    ]);
});
