<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\TelemedicineConsultationPatients\Schemas\TelemedicineConsultationPatientInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de consulta telemédica sin error', function (): void {
    $schema = Schema::make();
    $configured = TelemedicineConsultationPatientInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});
