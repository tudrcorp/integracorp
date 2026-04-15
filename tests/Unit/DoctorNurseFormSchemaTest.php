<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\DoctorNurses\Schemas\DoctorNurseForm;
use Filament\Schemas\Schema;

it('configura el schema del formulario de proveedores naturales sin error', function (): void {
    $schema = Schema::make();
    $configured = DoctorNurseForm::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});
