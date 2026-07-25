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
