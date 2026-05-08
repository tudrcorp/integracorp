<?php

declare(strict_types=1);

it('muestra la descripcion del ticket con retardo al pasar el cursor sobre la descripcion en todos los paneles', function (): void {
    foreach (['Business', 'Administration', 'Marketing', 'Operations'] as $panel) {
        $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Tables/HelpdesksTable.php";
        $contents = file_get_contents($path);

        expect($contents)
            ->not->toBeFalse()
            ->toContain("TextColumn::make('description')")
            ->toContain("'x-tooltip' =>")
            ->toContain('delay: [1000, 0]')
            ->toContain('Js::from($description)');
    }
});
