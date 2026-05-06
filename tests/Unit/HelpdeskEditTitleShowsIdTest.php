<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

it('muestra el id del ticket en el título al editar helpdesk', function (): void {
    $files = [
        base_path('app/Filament/Operations/Resources/Helpdesks/Pages/EditHelpdesk.php'),
        base_path('app/Filament/Business/Resources/Helpdesks/Pages/EditHelpdesk.php'),
        base_path('app/Filament/Administration/Resources/Helpdesks/Pages/EditHelpdesk.php'),
        base_path('app/Filament/Marketing/Resources/Helpdesks/Pages/EditHelpdesk.php'),
    ];

    foreach ($files as $file) {
        $contents = file_get_contents($file);

        expect($contents)->not->toBeFalse();

        expect($contents)
            ->toContain('public function getTitle(): string|Htmlable')
            ->toContain('Editar Help Desk #');
    }
});
