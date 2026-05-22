<?php

declare(strict_types=1);

it('registra agenda corporativa en administration, operations y marketing', function (): void {
    $adminPage = dirname(__DIR__, 2).'/app/Filament/Administration/Pages/AgendaCorporativa.php';
    $operationsPage = dirname(__DIR__, 2).'/app/Filament/Operations/Pages/AgendaCorporativa.php';
    $marketingPage = dirname(__DIR__, 2).'/app/Filament/Marketing/Pages/AgendaCorporativa.php';

    expect(file_exists($adminPage))->toBeTrue()
        ->and(file_exists($operationsPage))->toBeTrue()
        ->and(file_exists($marketingPage))->toBeTrue();

    expect(file_get_contents($adminPage))
        ->toContain('extends BusinessAgendaCorporativa')
        ->toContain("protected static string|UnitEnum|null \$navigationGroup = 'ADMINISTRACIÓN';");

    expect(file_get_contents($operationsPage))
        ->toContain('extends BusinessAgendaCorporativa')
        ->toContain("protected static string|UnitEnum|null \$navigationGroup = 'COORDINACIÓN DE SERVICIOS';");

    expect(file_get_contents($marketingPage))
        ->toContain('extends BusinessAgendaCorporativa')
        ->toContain("protected static string|UnitEnum|null \$navigationGroup = 'MARKETING';");
});

it('restringe visibilidad para no superadmin y mantiene edicion por creador o superadmin', function (): void {
    $businessPage = dirname(__DIR__, 2).'/app/Filament/Business/Pages/AgendaCorporativa.php';
    $viewPath = dirname(__DIR__, 2).'/resources/views/filament/business/pages/agenda-corporativa.blade.php';

    $businessPageContents = file_get_contents($businessPage);
    $viewContents = file_get_contents($viewPath);

    expect($businessPageContents)
        ->toContain('private function userIsSuperAdmin(): bool')
        ->toContain('public function canCurrentUserEdit(?CorporateAgendaActivity $activity): bool')
        ->toContain('if ($this->userIsSuperAdmin()) {')
        ->toContain('return (int) $activity->creator_user_id === (int) Auth::id();')
        ->toContain('public function canCreateActivity(): bool')
        ->toContain('return Auth::check();')
        ->toContain('private function visibleActivitiesBetween(Carbon $start, Carbon $end): Builder')
        ->toContain("->orWhereHas('participants'")
        ->toContain("->where('creator_user_id', \$currentUserId)")
        ->toContain('private function currentCollaboratorIds(): array');

    expect($viewContents)
        ->toContain('@if ($isCreatingActivity || $this->canCurrentUserEdit($selectedActivity))')
        ->toContain('Solo el creador o un usuario SUPERADMIN puede editarla');
});

it('alinea la vista semanal con el fondo claro de la vista mensual', function (): void {
    $shellPath = dirname(__DIR__, 2).'/resources/views/filament/business/pages/partials/corporate-calendar-shell.blade.php';
    $viewContents = file_get_contents($shellPath);

    expect($viewContents)
        ->toContain('min-w-[980px] rounded-[1.65rem] border border-slate-200/80 bg-slate-50/70 p-3')
        ->not->toContain('from-cyan-500/90 via-sky-600/85 to-cyan-700/90')
        ->and($viewContents)->toContain('border-slate-200/70 bg-slate-50/90 px-2 py-1 dark:border-white/10 dark:bg-slate-950/50');
});
