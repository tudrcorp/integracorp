<?php

declare(strict_types=1);

it('registra la agenda corporativa en el panel business con UI compatible light y dark', function (): void {
    $pagePath = dirname(__DIR__, 2).'/app/Filament/Business/Pages/AgendaCorporativa.php';
    $viewPath = dirname(__DIR__, 2).'/resources/views/filament/business/pages/agenda-corporativa.blade.php';

    expect(file_exists($pagePath))->toBeTrue()
        ->and(file_exists($viewPath))->toBeTrue();

    $pageContents = file_get_contents($pagePath);
    $viewContents = file_get_contents($viewPath);

    expect($pageContents)
        ->toContain('namespace Tests\Unit;')
        ->toContain("protected static ?string \$navigationLabel = 'Agenda Corporativa';")
        ->toContain("protected static string|UnitEnum|null \$navigationGroup = 'SOLICITUDES';")
        ->toContain("protected string \$view = 'filament.business.pages.agenda-corporativa';")
        ->toContain('public function previousMonth(): void')
        ->toContain('public function nextMonth(): void')
        ->toContain('public function goToday(): void');

    expect($viewContents)
        ->toContain('Agenda Corporativa')
        ->toContain('wire:click="previousMonth"')
        ->toContain('wire:click="goToday"')
        ->toContain('wire:click="nextMonth"')
        ->toContain("\$day['is_past_date']")
        ->toContain('dark:border-white/10')
        ->toContain('dark:bg-slate-900/70');
});
