<?php

declare(strict_types=1);

it('ListMassNotifications oculta crear notificación y crear carpeta dentro de una carpeta', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/MassNotifications/Pages/ListMassNotifications.php';
    $src = file_get_contents($path);

    expect($src)->toContain('->visible(fn (): bool => $this->folderId === null)');
});
