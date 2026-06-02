<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\DoctorNurses\Tables\DoctorNursesTable;

it('expone configure para la tabla de proveedores naturales', function (): void {
    expect(method_exists(DoctorNursesTable::class, 'configure'))->toBeTrue();
});

it('configura filtro de coordinador encargado por created_by', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/DoctorNurses/Tables/DoctorNursesTable.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain("SelectFilter::make('created_by')")
        ->toContain("->label('Coordinador encargado:')")
        ->toContain("in_array('OPERACIONES', \$departaments, true)");
});
it('configura filtro de fecha desde y hasta', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/DoctorNurses/Tables/DoctorNursesTable.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain("Filter::make('created_at_range')")
        ->toContain("->label('Fecha de creación')")
        ->toContain("DatePicker::make('from')")
        ->toContain("->label('Desde')")
        ->toContain("DatePicker::make('until')")
        ->toContain("->label('Hasta')")
        ->toContain("whereDate('created_at', '>=', \$data['from'])")
        ->toContain("whereDate('created_at', '<=', \$data['until'])");
});
