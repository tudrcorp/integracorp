<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\DoctorNurses\Schemas\DoctorNurseInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de proveedores naturales sin error', function (): void {
    $schema = Schema::make();
    $configured = DoctorNurseInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});
