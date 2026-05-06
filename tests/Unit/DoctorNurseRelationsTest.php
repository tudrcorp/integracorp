<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\DoctorNurses\DoctorNurseResource;
use App\Filament\Operations\Resources\DoctorNurses\RelationManagers\DoctorNurseObservacionsRelationManager;

it('incluye bitácora de observaciones en proveedores naturales', function () {
    expect(DoctorNurseResource::getRelations())
        ->toContain(DoctorNurseObservacionsRelationManager::class);
});
