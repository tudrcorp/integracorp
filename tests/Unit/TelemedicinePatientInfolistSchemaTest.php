<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\TelemedicinePatients\Schemas\TelemedicinePatientInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de paciente de telemedicina sin error', function (): void {
    $schema = Schema::make();
    $configured = TelemedicinePatientInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});
