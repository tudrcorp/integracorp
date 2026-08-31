<?php

declare(strict_types=1);

use App\Models\TelemedicineConsultationPatient;
use App\Support\Telemedicine\TelemedicineConsultationUploadedDocuments;

uses(Tests\TestCase::class);

it('la migracion convierte uploaded_documents a json', function (): void {
    $migration = file_get_contents(
        dirname(__DIR__, 2).'/database/migrations/2026_07_22_191129_change_uploaded_documents_to_json_on_telemedicine_consultation_patients_table.php'
    );

    expect($migration)
        ->toContain('telemedicine_consultation_patients')
        ->toContain('uploaded_documents')
        ->toContain('JSON NULL');
});

it('reemplaza documentos del mismo tipo al sincronizar metadata', function (): void {
    $consultation = Mockery::mock(TelemedicineConsultationPatient::class)->makePartial();
    $consultation->uploaded_documents = [
        [
            'document_name' => 'viejo-informe-largo.pdf',
            'file_path' => 'telemedicina-doc/viejo-informe-largo.pdf',
            'document_type_ids' => [9],
            'document_types' => ['INFORME MEDICO CONSULTA INICIAL (LARGO)'],
            'uploaded_at' => '2026-07-01 10:00:00',
        ],
        [
            'document_name' => 'recipe.pdf',
            'file_path' => 'telemedicina-doc/recipe.pdf',
            'document_type_ids' => [10],
            'document_types' => ['RECIPE'],
            'uploaded_at' => '2026-07-01 10:00:00',
        ],
    ];

    $consultation->shouldReceive('update')
        ->once()
        ->with(Mockery::on(function (array $attributes): bool {
            $documents = $attributes['uploaded_documents'] ?? [];
            $names = collect($documents)->pluck('document_name')->all();

            return count($documents) === 2
                && in_array('nuevo-informe-largo.pdf', $names, true)
                && in_array('recipe.pdf', $names, true)
                && ! in_array('viejo-informe-largo.pdf', $names, true);
        }))
        ->andReturnTrue();

    TelemedicineConsultationUploadedDocuments::sync($consultation, [
        'document_name' => 'nuevo-informe-largo.pdf',
        'file_path' => 'telemedicina-doc/nuevo-informe-largo.pdf',
        'document_type_ids' => [9],
        'document_types' => ['INFORME MEDICO CONSULTA INICIAL (LARGO)'],
        'uploaded_at' => '2026-07-22 19:10:43',
    ], 9);
});

it('reemplaza la familia cubiertos y no cubiertos del mismo tipo de documento', function (): void {
    $consultation = Mockery::mock(TelemedicineConsultationPatient::class)->makePartial();
    $consultation->uploaded_documents = [
        [
            'document_name' => '16007868-REF-1-medicamentos.pdf',
            'file_path' => 'telemedicina-doc/16007868-REF-1-medicamentos.pdf',
            'document_type_ids' => [10],
            'document_types' => ['RECIPE DE MEDICAMENTOS'],
            'uploaded_at' => '2026-07-01 10:00:00',
        ],
        [
            'document_name' => 'informe-corto.pdf',
            'file_path' => 'telemedicina-doc/informe-corto.pdf',
            'document_type_ids' => [14],
            'document_types' => ['INFORME MEDICO CONSULTA INICIAL (CORTO)'],
            'uploaded_at' => '2026-07-01 10:00:00',
        ],
    ];

    $consultation->shouldReceive('update')
        ->once()
        ->with(Mockery::on(function (array $attributes): bool {
            $documents = $attributes['uploaded_documents'] ?? [];
            $names = collect($documents)->pluck('document_name')->all();

            return count($documents) === 3
                && in_array('informe-corto.pdf', $names, true)
                && in_array('16007868-REF-1-medicamentos-cubiertos.pdf', $names, true)
                && in_array('16007868-REF-1-medicamentos-no-cubiertos.pdf', $names, true)
                && ! in_array('16007868-REF-1-medicamentos.pdf', $names, true);
        }))
        ->andReturnTrue();

    TelemedicineConsultationUploadedDocuments::replaceFamily(
        $consultation,
        10,
        [
            [
                'document_name' => '16007868-REF-1-medicamentos-cubiertos.pdf',
                'file_path' => 'telemedicina-doc/16007868-REF-1-medicamentos-cubiertos.pdf',
                'document_type_ids' => [10],
                'document_types' => ['RECIPE DE MEDICAMENTOS (Cubiertos)'],
                'uploaded_at' => '2026-08-30 23:00:00',
            ],
            [
                'document_name' => '16007868-REF-1-medicamentos-no-cubiertos.pdf',
                'file_path' => 'telemedicina-doc/16007868-REF-1-medicamentos-no-cubiertos.pdf',
                'document_type_ids' => [10],
                'document_types' => ['RECIPE DE MEDICAMENTOS (No cubiertos)'],
                'uploaded_at' => '2026-08-30 23:00:00',
            ],
        ],
        [
            '16007868-REF-1-medicamentos.pdf',
            '16007868-REF-1-medicamentos-cubiertos.pdf',
            '16007868-REF-1-medicamentos-no-cubiertos.pdf',
        ],
    );
});
