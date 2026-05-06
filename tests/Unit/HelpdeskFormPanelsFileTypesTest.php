<?php

declare(strict_types=1);

it('permite adjuntar pdf y powerpoints en helpdesk para business administration marketing y operations', function (): void {
    $basePath = dirname(__DIR__, 2).'/app/Filament';

    $files = [
        $basePath.'/Business/Resources/Helpdesks/Schemas/HelpdeskForm.php',
        $basePath.'/Administration/Resources/Helpdesks/Schemas/HelpdeskForm.php',
        $basePath.'/Marketing/Resources/Helpdesks/Schemas/HelpdeskForm.php',
        $basePath.'/Operations/Resources/Helpdesks/Schemas/HelpdeskForm.php',
    ];

    foreach ($files as $file) {
        $src = file_get_contents($file);

        expect($src)
            ->toContain("'application/pdf'")
            ->toContain("'application/vnd.ms-powerpoint'")
            ->toContain("'application/vnd.openxmlformats-officedocument.presentationml.presentation'");
    }
});
