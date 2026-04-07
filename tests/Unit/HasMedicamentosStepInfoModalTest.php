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
