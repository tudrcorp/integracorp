<?php

declare(strict_types=1);

use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Concerns\HasMedicamentosStepInfoModal;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages\CreateTelemedicineConsultationPatient;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages\EditTelemedicineConsultationPatient;

test('Create and Edit consultation patient pages use medicamentos step info modal trait', function () {
    expect(class_uses_recursive(CreateTelemedicineConsultationPatient::class))
        ->toHaveKey(HasMedicamentosStepInfoModal::class);
    expect(class_uses_recursive(EditTelemedicineConsultationPatient::class))
        ->toHaveKey(HasMedicamentosStepInfoModal::class);
});

test('el modal del paso medicamentos explica inventario, cubierto sin stock y no cubierto', function (): void {
    $modal = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/modals/medicamentos-step-info.blade.php');

    expect($modal)
        ->toContain('Cubierto (Operaciones)')
        ->toContain('no está en inventario')
        ->toContain('No cubierto')
        ->toContain('una sola fuente');
});
