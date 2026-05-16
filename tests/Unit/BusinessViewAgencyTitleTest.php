<?php

declare(strict_types=1);

it('muestra información principal en el título de vista de agencia business', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Pages/ViewAgency.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('public function getTitle(): string|Htmlable')
        ->toContain('Agencia: ')
        ->toContain('name_corporative')
        ->toContain('badgeStyleForStatus')
        ->toContain('email')
        ->toContain('phone');
});
