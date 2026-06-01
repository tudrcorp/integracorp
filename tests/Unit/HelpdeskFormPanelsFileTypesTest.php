<?php

declare(strict_types=1);

it('permite adjuntar pdf y powerpoints en helpdesk para business administration marketing y operations', function (): void {
    $basePath = dirname(__DIR__, 2).'/app/Filament';

    $panelFiles = [
        $basePath.'/Business/Resources/Helpdesks/Schemas/HelpdeskForm.php',
        $basePath.'/Administration/Resources/Helpdesks/Schemas/HelpdeskForm.php',
        $basePath.'/Marketing/Resources/Helpdesks/Schemas/HelpdeskForm.php',
        $basePath.'/Operations/Resources/Helpdesks/Schemas/HelpdeskForm.php',
    ];

    foreach ($panelFiles as $file) {
        expect(file_get_contents($file))->toContain('HelpdeskFormSchema::configure');
    }

    $shared = file_get_contents(dirname(__DIR__, 2).'/app/Support/HelpdeskFormSchema.php');

    expect($shared)
        ->toContain("Tab::make('Compromiso de atención')")
        ->toContain('HelpdeskTechnologyTermsNotice::ACCEPTANCE_FIELD');

    expect($shared)
        ->toContain("'application/pdf'")
        ->toContain("'application/vnd.ms-powerpoint'")
        ->toContain("'application/vnd.openxmlformats-officedocument.presentationml.presentation'");
});
