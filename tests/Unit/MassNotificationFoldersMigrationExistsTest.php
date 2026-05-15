<?php

declare(strict_types=1);

it('incluye migración que añade mass_notification_folder_id', function (): void {
    $path = dirname(__DIR__, 2).'/database/migrations/2026_05_09_100000_add_mass_notification_folders_support.php';
    $src = @file_get_contents($path);

    expect($src)->not->toBeFalse()
        ->and($src)->toContain('mass_notification_folder_id')
        ->and($src)->toContain('mass_notification_folders');
});
