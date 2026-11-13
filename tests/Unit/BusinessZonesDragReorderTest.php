<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

it('permite reordenar carpetas arrastrando por position en negocio y marketing', function (): void {
    $files = [
        base_path('app/Filament/Business/Resources/Zones/Tables/ZonesTable.php'),
        base_path('app/Filament/Marketing/Resources/Zones/Tables/ZonesTable.php'),
    ];

    foreach ($files as $file) {
        $table = file_get_contents($file);
        expect($table)->not->toBeFalse();

        expect($table)
            ->toContain("->reorderable('position')")
            ->toContain('reorderRecordsTriggerAction')
            ->toContain("'Reordenar carpetas'")
            ->toContain("'Terminar de ordenar'")
            ->toContain('Arrastra las carpetas')
            ->not->toContain("TextColumn::make('position')");
    }
});

it('ya no pide posición numérica en el formulario de carpetas de negocio y marketing', function (): void {
    $files = [
        base_path('app/Filament/Business/Resources/Zones/Schemas/ZoneForm.php'),
        base_path('app/Filament/Marketing/Resources/Zones/Schemas/ZoneForm.php'),
    ];

    foreach ($files as $file) {
        $form = file_get_contents($file);
        expect($form)->not->toBeFalse();

        expect($form)
            ->not->toContain("TextInput::make('position')")
            ->not->toContain("unique(table: Zone::class, column: 'position')")
            ->toContain('arrastrando las carpetas');
    }
});

it('asigna la siguiente posición al crear una carpeta en negocio y marketing', function (): void {
    $files = [
        base_path('app/Filament/Business/Resources/Zones/Pages/CreateZone.php'),
        base_path('app/Filament/Marketing/Resources/Zones/Pages/CreateZone.php'),
    ];

    foreach ($files as $file) {
        $create = file_get_contents($file);
        expect($create)->not->toBeFalse();

        expect($create)
            ->toContain('mutateFormDataBeforeCreate')
            ->toContain("Zone::query()->max('position')")
            ->toContain("'position'");
    }
});
