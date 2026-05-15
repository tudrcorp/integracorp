<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\TelemedicineConsultationPatients\Schemas\TelemedicineConsultationPatientInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de consulta telemédica sin error', function (): void {
    $schema = Schema::make();
    $configured = TelemedicineConsultationPatientInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('agrupa las secciones del infolist en pestañas persistentes', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('Tabs::make(\'telemedicineConsultationPatientInfolistTabs\')')
        ->toContain('->persistTab()')
        ->toContain('Tab::make(\'Paciente en esta consulta\')')
        ->toContain('Tab::make(\'Consulta telemédica\')');
});
