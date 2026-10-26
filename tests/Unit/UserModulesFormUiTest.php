<?php

declare(strict_types=1);

use App\Filament\Forms\Components\ModuleSelector;
use App\Support\Filament\UserModulesFormUi;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

it('cada módulo de la tabla rols tiene su ficha: paneles primero y accesos especiales después', function (): void {
    $catalog = collect(UserModulesFormUi::moduleCatalog());
    $kinds = $catalog->pluck('kind')->values()->all();

    expect(UserModulesFormUi::stylesView())->toBe('filament.business.users.partials.modules-form-styles')
        ->and($catalog->pluck('key')->sort()->values()->all())->toBe(DB::table('rols')->orderBy('name')->pluck('name')->filter()->sort()->values()->all())
        ->and($kinds)->toBe([...array_filter($kinds, fn (string $kind): bool => $kind === 'panel'), ...array_filter($kinds, fn (string $kind): bool => $kind !== 'panel')])
        ->and($catalog->every(fn (array $module): bool => $module['description'] !== '' && str_starts_with($module['accent'], '#')))->toBeTrue();

    $superadmin = $catalog->firstWhere('key', 'SUPERADMIN');
    $metricas = $catalog->firstWhere('key', 'METRICAS');
    $negocios = $catalog->firstWhere('key', 'NEGOCIOS');

    if ($superadmin !== null) {
        expect($superadmin['kind'])->toBe('special')->and($superadmin['confirm'])->toContain('acceso total');
    }

    if ($metricas !== null) {
        expect($metricas['requires'])->toBe('SUPERADMIN')->and($metricas['internal_email'])->toBeTrue();
    }

    if ($negocios !== null) {
        expect($negocios['path'])->toBe('/business')->and($negocios['internal_email'])->toBeTrue();
    }
});

it('un módulo desconocido se muestra como acceso especial sin romper la pestaña', function (): void {
    DB::table('rols')->insert(['name' => 'QA-MODULO-NUEVO', 'description' => 'QA', 'created_by' => 'QA', 'created_at' => now(), 'updated_at' => now()]);

    expect(collect(UserModulesFormUi::moduleCatalog())->firstWhere('key', 'QA-MODULO-NUEVO'))
        ->toMatchArray(['kind' => 'special', 'description' => 'Módulo sin descripción registrada.']);
});

it('el selector normaliza el estado y valida contra los módulos existentes', function (): void {
    $field = ModuleSelector::make('departament');

    expect(ModuleSelector::normalize(['NEGOCIOS', '', null, 'NEGOCIOS', 5, 'OPERACIONES']))->toBe(['NEGOCIOS', 'OPERACIONES'])
        ->and(ModuleSelector::normalize('NEGOCIOS'))->toBe([])
        ->and($field->getNestedRecursiveValidationRules())->toHaveCount(1);

    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/forms/components/module-selector.blade.php');

    expect($view)->toContain('wire:ignore')
        ->toContain('$wire.$entangle(@js($statePath))')
        ->toContain('Sí, dar acceso')
        ->toContain('no podrá entrar a este panel')
        ->toContain('Al guardar se quitarán sus ')
        ->not->toContain('wire:model.live');
});
