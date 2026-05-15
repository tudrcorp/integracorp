<?php

declare(strict_types=1);

it('ListMassNotifications prepara miniaturas para la vista de carpetas', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/MassNotifications/Pages/ListMassNotifications.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("'preview_images'")
        ->toContain('resolveNotificationFileUrl')
        ->toContain('withCount(\'massNotifications\')')
        ->toContain('->with([');
});
