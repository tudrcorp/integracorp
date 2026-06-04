<?php

declare(strict_types=1);

it('tablas helpdesk de administration, operations y marketing usan configurador compartido', function (): void {
    $panels = [
        'Administration' => dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Helpdesks/Tables/HelpdesksTable.php',
        'Operations' => dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Helpdesks/Tables/HelpdesksTable.php',
        'Marketing' => dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/Helpdesks/Tables/HelpdesksTable.php',
        'Business' => dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Tables/HelpdesksTable.php',
    ];

    $configurator = file_get_contents(dirname(__DIR__, 2).'/app/Support/HelpdeskTableConfigurator.php');

    expect($configurator)
        ->toContain('recordPriorityRowClass')
        ->toContain('fi-helpdesk-ta-priority--alta')
        ->toContain('bg-red-50 dark:bg-red-950/30 border-l-4 border-red-500')
        ->toContain('bg-amber-50 dark:bg-amber-950/30 border-l-4 border-amber-500')
        ->toContain('bg-green-50 dark:bg-green-950/30 border-l-4 border-green-500');

    foreach ($panels as $panel => $path) {
        $contents = file_get_contents($path);

        expect($contents)
            ->toContain('HelpdeskTableConfigurator::configure', $panel)
            ->and($configurator)
            ->toContain('HelpdeskTableTicketTypeColumn::make(individualSearch: true)')
            ->toContain('filament.business.helpdesks.notes-modal')
            ->toContain('makeViewNotesAction')
            ->toContain('isIndividual: true');
    }
});

it('recursos helpdesk de todos los paneles validan creacion por grupo o SUPERADMIN', function (): void {
    foreach (['Business', 'Administration', 'Operations', 'Marketing'] as $panel) {
        $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/HelpdeskResource.php";

        expect(file_get_contents($path))->toContain('AuthorizesHelpdeskTicketCreation');
    }
});

it('acciones modales de todos los paneles incluyen nota obligatoria al cambiar estado', function (): void {
    $panels = ['Administration', 'Business', 'Marketing', 'Operations'];

    foreach ($panels as $panel) {
        $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Actions/HelpdeskTicketModalActions.php";
        $contents = file_get_contents($path);

        expect($contents)
            ->toContain('HelpdeskStatusChangeNote::assigneeExplanationEditor')
            ->toContain('HelpdeskStatusChangeNote::buildObservationHtml');
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

it('infolist compartido muestra tipo de ticket, descripción y notas enriquecidas', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Support/HelpdeskInfolistSchema.php');

    expect($contents)
        ->toContain("TextEntry::make('ticket_type')")
        ->toContain('HelpdeskTicketType::filamentColor')
        ->toContain('HelpdeskDescriptionInfolistRenderer::format')
        ->toContain('HelpdeskObservationHtmlRenderer::summaryBannerHtml')
        ->toContain('HelpdeskObservationHtmlRenderer::render')
        ->toContain('helpdesk-notes-infolist');
});

it('vista helpdesk de administration, operations y marketing usa titulo alineado con business', function (): void {
    $panels = ['Administration', 'Operations', 'Marketing'];

    foreach ($panels as $panel) {
        $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Pages/ViewHelpdesk.php";
        $contents = file_get_contents($path);

        expect($contents)->toContain('Detalles del ticket');
    }
});
