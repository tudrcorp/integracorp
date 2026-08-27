<?php

declare(strict_types=1);

use App\Models\FamilyHistory;
use App\Models\GynecologicalHistory;
use App\Models\NoPathologicalHistory;
use App\Models\PathologicalHistory;
use App\Models\SurgicalHistory;
use App\Support\Telemedicine\TelemedicineHistoryRelatedRecordsSync;

it('no copia observaciones no patológicas a los antecedentes quirúrgicos ni ginecológicos', function (): void {
    $payloads = TelemedicineHistoryRelatedRecordsSync::payloadsFromAttributes([
        'history_surgical' => 'Apendicectomía 2019',
        'observations_ginecologica' => 'G3P2',
        'observations_not_pathological' => null,
    ]);

    expect($payloads)->toHaveCount(2)
        ->and($payloads[0]['model'])->toBe(SurgicalHistory::class)
        ->and($payloads[0]['source_attribute'])->toBe('history_surgical')
        ->and($payloads[0]['observations'])->toBe('Apendicectomía 2019')
        ->and($payloads[1]['model'])->toBe(GynecologicalHistory::class)
        ->and($payloads[1]['source_attribute'])->toBe('observations_ginecologica')
        ->and($payloads[1]['observations'])->toBe('G3P2');
});

it('omite filas históricas cuando la observación está vacía, nula o solo espacios', function (): void {
    $payloads = TelemedicineHistoryRelatedRecordsSync::payloadsFromAttributes([
        'observations_personal' => '   ',
        'observations_pathological' => null,
        'observations_not_pathological' => '',
        'history_surgical' => '  Cesárea 2021  ',
        'observations_ginecologica' => false,
    ]);

    expect($payloads)->toHaveCount(1)
        ->and($payloads[0]['model'])->toBe(SurgicalHistory::class)
        ->and($payloads[0]['observations'])->toBe('Cesárea 2021');
});

it('incluye los cinco tipos de antecedente cuando todos tienen texto', function (): void {
    $payloads = TelemedicineHistoryRelatedRecordsSync::payloadsFromAttributes([
        'observations_personal' => 'Padre hipertenso',
        'observations_pathological' => 'Asma',
        'observations_not_pathological' => 'Sedentarismo',
        'history_surgical' => 'Colecistectomía',
        'observations_ginecologica' => 'Menarquia 12 años',
    ]);

    expect(array_column($payloads, 'model'))->toBe([
        FamilyHistory::class,
        PathologicalHistory::class,
        NoPathologicalHistory::class,
        SurgicalHistory::class,
        GynecologicalHistory::class,
    ]);
});

it('normaliza el autor vacío a Sistema y las observaciones en blanco a null', function (): void {
    expect(TelemedicineHistoryRelatedRecordsSync::normalizeCreatedBy('  '))
        ->toBe('Sistema')
        ->and(TelemedicineHistoryRelatedRecordsSync::normalizeCreatedBy('Dra. Pérez'))
        ->toBe('Dra. Pérez')
        ->and(TelemedicineHistoryRelatedRecordsSync::normalizeObservations(null))
        ->toBeNull()
        ->and(TelemedicineHistoryRelatedRecordsSync::normalizeObservations('  texto  '))
        ->toBe('texto');
});
