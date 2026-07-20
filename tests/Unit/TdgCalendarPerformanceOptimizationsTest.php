<?php

declare(strict_types=1);

use App\Filament\Business\Pages\Concerns\InteractsWithTdgHybridCalendar;

it('optimiza el payload del calendario tdg sin comprobar disco por avatar', function (): void {
    $traitPath = dirname(__DIR__, 2).'/app/Filament/Business/Pages/Concerns/InteractsWithTdgHybridCalendar.php';
    $source = file_get_contents($traitPath);

    expect($source)
        ->toContain('trait InteractsWithTdgHybridCalendar')
        ->toContain('forgetTdgMonthDayPayloadCache')
        ->toContain('tdg_calendar_payload_version')
        ->toContain('buildTdgMonthDayPayloadArrays')
        ->toContain("whereBetween('calendar_date'")
        ->toContain('tdgPublicStorageUrl')
        ->toContain('Cache::remember(')
        ->toContain('serializeOfficeAssignment')
        ->toContain('serializeGuardAssignment')
        ->toContain('serializeDepartmentColaboradorAssignment')
        ->toContain('collaboratorOptionsById')
        ->toContain('avatarPresentation[\'visible\']')
        ->toContain('DB::transaction')
        ->not->toContain("Storage::disk('public')->exists")
        ->not->toContain("whereDate('calendar_date'");
});

it('carga imagenes de avatares del calendario tdg de forma diferida', function (): void {
    $stackPath = dirname(__DIR__, 2).'/resources/views/components/collaborator-avatar-stack.blade.php';
    $avatarPath = dirname(__DIR__, 2).'/resources/views/filament/business/pages/partials/tdg-colaborador-avatar.blade.php';

    expect(file_get_contents($stackPath))
        ->toContain('loading="lazy"')
        ->toContain('decoding="async"');

    expect(file_get_contents($avatarPath))
        ->toContain('loading="lazy"')
        ->toContain('decoding="async"');
});

it('optimiza abrir y cerrar el modal de dia del calendario tdg', function (): void {
    $traitPath = dirname(__DIR__, 2).'/app/Filament/Business/Pages/Concerns/InteractsWithTdgHybridCalendar.php';
    $modalPath = dirname(__DIR__, 2).'/resources/views/filament/business/pages/partials/calendarios-tdg-day-modal.blade.php';
    $shellPath = dirname(__DIR__, 2).'/resources/views/filament/business/pages/partials/corporate-calendar-shell.blade.php';

    $trait = file_get_contents($traitPath);

    expect($trait)
        ->toContain('public function closeDayModal(): void')
        ->toContain('$this->skipRender();')
        ->toContain('resolveSelectedDayPayloadForHydration')
        ->toContain('applyDayPayloadToForms')
        ->not->toMatch('/function closeDayModal\(\): void\s*\{[^}]*forgetTdgMonthDayPayloadCache/s');

    expect(file_get_contents($modalPath))
        ->toContain('@if ($isDayModalOpen)')
        ->toContain("@js(\$modalWorkspace ?: 'offices')");

    expect(file_get_contents($shellPath))
        ->toContain('wire:target="openDayModal"');
});

it('expone el trait de agenda hibrida tdg', function (): void {
    expect(trait_exists(InteractsWithTdgHybridCalendar::class))->toBeTrue();
});
