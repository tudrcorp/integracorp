<?php

declare(strict_types=1);

it('tablas helpdesk de administration, operations y marketing incluyen tipo de ticket', function (): void {
    $panels = [
        'Administration' => dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Helpdesks/Tables/HelpdesksTable.php',
        'Operations' => dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Helpdesks/Tables/HelpdesksTable.php',
        'Marketing' => dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/Helpdesks/Tables/HelpdesksTable.php',
    ];

    foreach ($panels as $panel => $path) {
        $contents = file_get_contents($path);

        expect($contents)->toContain('HelpdeskTableTicketTypeColumn::make()');
    }
});

it('formularios helpdesk de todos los paneles usan schema compartido con pestañas nuevas', function (): void {
    $forms = [
        'Administration' => dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Helpdesks/Schemas/HelpdeskForm.php',
        'Operations' => dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Helpdesks/Schemas/HelpdeskForm.php',
        'Marketing' => dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/Helpdesks/Schemas/HelpdeskForm.php',
        'Business' => dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Schemas/HelpdeskForm.php',
    ];

    $schema = file_get_contents(dirname(__DIR__, 2).'/app/Support/HelpdeskFormSchema.php');

    foreach ($forms as $panel => $path) {
        $contents = file_get_contents($path);

        expect($contents)
            ->toContain('HelpdeskFormSchema::configure', $panel)
            ->and($schema)->toContain("Tab::make('Tipo de ticket')")
            ->and($schema)->toContain("Tab::make('Compromiso de atención')");
    }
});

it('infolist compartido muestra tipo de ticket', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Support/HelpdeskInfolistSchema.php');

    expect($contents)
        ->toContain("TextEntry::make('ticket_type')")
        ->toContain('HelpdeskTicketType::filamentColor')
        ->toContain('HelpdeskDescriptionInfolistRenderer::format');
});
