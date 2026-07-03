<?php

declare(strict_types=1);

it('pagina ver usuario usa titulo personalizado en español', function (): void {
    $php = file_get_contents(__DIR__.'/../../app/Filament/Business/Resources/Users/Pages/ViewUser.php');

    expect($php)->toContain('UserPageHeader::make($user)')
        ->not->toContain('Ver User');
});
