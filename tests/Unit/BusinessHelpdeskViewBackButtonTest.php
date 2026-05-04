<?php

declare(strict_types=1);

it('ViewHelpdesk incluye botón volver junto a actualizar estado', function (): void {
    $paths = [
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Pages/ViewHelpdesk.php',
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Helpdesks/Pages/ViewHelpdesk.php',
        dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/Helpdesks/Pages/ViewHelpdesk.php',
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Helpdesks/Pages/ViewHelpdesk.php',
    ];

    foreach ($paths as $path) {
        $contents = file_get_contents($path);

        expect($contents)
            ->not->toBeFalse()
            ->toContain('HelpdeskTicketModalActions::makeAddNoteAction()')
            ->toContain('HelpdeskTicketModalActions::makeUpdateStatusAction()')
            ->toContain("Action::make('back')")
            ->toContain("->label('Volver')")
            ->toContain("->icon('heroicon-o-arrow-left')")
            ->toContain('->url(HelpdeskResource::getUrl())')
            ->toContain("'class' => 'ticket-btn-ios-shell'");

        expect(strpos($contents, "Action::make('back')"))
            ->toBeLessThan(strpos($contents, 'HelpdeskTicketModalActions::makeAddNoteAction()'))
            ->toBeLessThan(strpos($contents, 'HelpdeskTicketModalActions::makeUpdateStatusAction()'));

        expect($contents)->toMatch(
            "/HelpdeskTicketModalActions::makeAddNoteAction\\(\\)[\\s\\S]*?->extraAttributes\\(\\[[\\s\\S]*?'class' => 'ticket-btn-ios-shell'/"
        );
    }
});
