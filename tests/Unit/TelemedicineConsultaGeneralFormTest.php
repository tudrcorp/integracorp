<?php

declare(strict_types=1);

use App\Models\TelemedicineServiceList;

it('define la constante de consulta general', function (): void {
    expect(TelemedicineServiceList::CONSULTA_GENERAL_ID)->toBe(17);
});

it('muestra el select de servicio general solo cuando el tipo es consulta general', function (): void {
    $formPath = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php';
    $contents = file_get_contents($formPath);

    expect($contents)
        ->toContain('telemedicine_general_service_id')
        ->toContain('generalServiceSelect')
        ->toContain('TelemedicineServiceList::CONSULTA_GENERAL_ID')
        ->toContain('TelemedicineGeneralService::query()')
        ->toContain('syncServiceListSideEffects')
        ->toContain("->label('Servicio General')");
});

it('el servicio general es opcional y no bloquea el registro de la consulta', function (): void {
    $formPath = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php';
    $contents = file_get_contents($formPath);

    expect($contents)
        ->toContain('private static function generalServiceSelect()')
        ->toContain('->nullable()')
        ->toContain("->placeholder('Opcional — seleccione si aplica')")
        ->not->toContain('->required(fn (Get $get): bool => self::isConsultaGeneralSelected($get))');
});

it('persiste el id de servicio general en el modelo de consulta', function (): void {
    $modelPath = dirname(__DIR__, 2).'/app/Models/TelemedicineConsultationPatient.php';
    $contents = file_get_contents($modelPath);

    expect($contents)
        ->toContain("'telemedicine_general_service_id'")
        ->toContain('telemedicineGeneralService');
});

it('agrega la columna de servicio general en la migración de consultas', function (): void {
    $migrationPath = dirname(__DIR__, 2).'/database/migrations/2026_08_06_003540_add_telemedicine_general_service_id_to_telemedicine_consultation_patients_table.php';
    $contents = file_get_contents($migrationPath);

    expect($contents)
        ->toContain('telemedicine_general_service_id')
        ->toContain('nullOnDelete');
});
