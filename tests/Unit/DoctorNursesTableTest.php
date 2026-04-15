<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\DoctorNurses\Tables\DoctorNursesTable;

it('expone configure para la tabla de proveedores naturales', function (): void {
    expect(method_exists(DoctorNursesTable::class, 'configure'))->toBeTrue();
});
