<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicineCoverageDocumentSplit;

uses(Tests\TestCase::class);

it('parte medicamentos cubiertos y no cubiertos en dos grupos', function (): void {
    $groups = TelemedicineCoverageDocumentSplit::medicationGroups([
        'ci_patiente' => 'V-1',
        'code_reference' => 'REF-1',
        'medicationsArr' => [
            [
                'medicines' => 'Paracetamol 500 mg',
                'indications' => 'Cada 8 horas',
                'coverage' => 'Cubierto',
            ],
            [
                'medicines' => 'Loratadina 10 mg',
                'indications' => 'Cada 24 horas',
                'coverage' => 'Cubierto',
            ],
            [
                'medicines' => 'Omeprazol 20 mg',
                'indications' => 'En ayunas',
                'coverage' => 'No cubierto',
            ],
        ],
    ]);

    expect($groups)->toHaveCount(2)
        ->and($groups[0]['group'])->toBe(TelemedicineCoverageDocumentSplit::GROUP_COVERED)
        ->and($groups[0]['payload']['coverage_group'])->toBe('Cubiertos')
        ->and($groups[0]['payload']['medicationsArr'])->toHaveCount(2)
        ->and($groups[0]['payload']['medicationsArr'][0]['medicines'])->toBe('Paracetamol 500 mg')
        ->and($groups[1]['group'])->toBe(TelemedicineCoverageDocumentSplit::GROUP_UNCOVERED)
        ->and($groups[1]['payload']['coverage_group'])->toBe('No cubiertos')
        ->and($groups[1]['payload']['medicationsArr'])->toHaveCount(1)
        ->and($groups[1]['payload']['medicationsArr'][0]['medicines'])->toBe('Omeprazol 20 mg');
});

it('si todos los medicamentos son cubiertos solo genera un grupo', function (): void {
    $groups = TelemedicineCoverageDocumentSplit::medicationGroups([
        'medicationsArr' => [
            [
                'medicines' => 'Paracetamol 500 mg',
                'coverage' => 'Cubierto',
            ],
        ],
    ]);

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['group'])->toBe(TelemedicineCoverageDocumentSplit::GROUP_COVERED);
});

it('parte laboratorios cubiertos y no cubiertos en dos documentos', function (): void {
    $groups = TelemedicineCoverageDocumentSplit::orderGroups('laboratorios', [
        'labs' => ['Hematología completa', 'Glicemia en ayunas'],
        'other_labs' => ['Perfil hepático'],
    ]);

    expect($groups)->toHaveCount(2)
        ->and($groups[0]['payload']['labs'])->toBe(['Hematología completa', 'Glicemia en ayunas'])
        ->and($groups[0]['payload']['other_labs'])->toBe([])
        ->and($groups[0]['payload']['coverage_group'])->toBe('Cubiertos')
        ->and($groups[1]['payload']['labs'])->toBe([])
        ->and($groups[1]['payload']['other_labs'])->toBe(['Perfil hepático'])
        ->and($groups[1]['payload']['coverage_group'])->toBe('No cubiertos');
});

it('parte imagenologia y especialistas por cobertura', function (): void {
    $studies = TelemedicineCoverageDocumentSplit::orderGroups('imagenologia', [
        'studies' => ['Radiografía de tórax PA'],
        'other_studies' => ['Ecografía abdominal'],
    ]);
    $specialists = TelemedicineCoverageDocumentSplit::orderGroups('especialista', [
        'consultSpecialistArr' => ['Otorrinolaringología'],
        'other_specialist' => ['Medicina interna'],
    ]);

    expect($studies)->toHaveCount(2)
        ->and($studies[0]['payload']['studies'])->toBe(['Radiografía de tórax PA'])
        ->and($studies[1]['payload']['other_studies'])->toBe(['Ecografía abdominal'])
        ->and($specialists)->toHaveCount(2)
        ->and($specialists[0]['payload']['consultSpecialistArr'])->toBe(['Otorrinolaringología'])
        ->and($specialists[1]['payload']['other_specialist'])->toBe(['Medicina interna']);
});

it('nombra los archivos por familia cubiertos y no cubiertos', function (): void {
    $data = [
        'ci_patiente' => '16007868',
        'code_reference' => 'REF-71352',
    ];

    expect(TelemedicineCoverageDocumentSplit::filename($data, 'medicamentos-cubiertos'))
        ->toBe('16007868-REF-71352-medicamentos-cubiertos.pdf')
        ->and(TelemedicineCoverageDocumentSplit::familyFilenames($data, 'laboratorios'))
        ->toBe([
            '16007868-REF-71352-laboratorios.pdf',
            '16007868-REF-71352-laboratorios-cubiertos.pdf',
            '16007868-REF-71352-laboratorios-no-cubiertos.pdf',
        ]);
});
