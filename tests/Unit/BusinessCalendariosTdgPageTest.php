<?php

declare(strict_types=1);

it('registra calendarios tdg en el panel business con el diseño de agenda corporativa', function (): void {
    $pagePath = dirname(__DIR__, 2).'/app/Filament/Business/Pages/CalendariosTdg.php';
    $viewPath = dirname(__DIR__, 2).'/resources/views/filament/business/pages/calendarios-tdg.blade.php';
    $shellPath = dirname(__DIR__, 2).'/resources/views/filament/business/pages/partials/corporate-calendar-shell.blade.php';
    expect(file_exists($pagePath))->toBeTrue()
        ->and(file_exists($viewPath))->toBeTrue()
        ->and(file_exists($shellPath))->toBeTrue();

    $pageContents = file_get_contents($pagePath);
    $viewContents = file_get_contents($viewPath);
    $shellContents = file_get_contents($shellPath);

    expect($pageContents)
        ->toContain('namespace App\\Filament\\Business\\Pages;')
        ->toContain("protected static ?string \$navigationLabel = 'Calendarios TDG';")
        ->toContain("protected string \$view = 'filament.business.pages.calendarios-tdg';")
        ->toContain('InteractsWithTdgHybridCalendar');

    expect($viewContents)
        ->toContain('corporate-calendar-shell')
        ->toContain('calendarios-tdg-day-modal');

    expect($shellContents)
        ->toContain('corporateCalendarHeading()')
        ->toContain('calendarDayInteractionsEnabled()')
        ->toContain('wire:click="previousMonth"')
        ->toContain('wire:click="setWeekView"')
        ->toContain('dark:bg-slate-900/70')
        ->toContain('department_badges')
        ->toContain('office_count')
        ->toContain('tdg-calendar-day-avatars');
});
