<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Filament\CommercialNetworkAccess;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

it('usa menú superior por defecto si la agencia del usuario no existe', function (): void {
    $user = User::factory()->create([
        'is_agency' => true,
        'agency_type' => 'GENERAL',
        'code_agency' => 'TDG-NO-EXISTE-'.uniqid('', true),
        'status' => 'ACTIVO',
    ]);

    expect(CommercialNetworkAccess::prefersTopNavigation($user))->toBeTrue();
});

it('usa menú superior por defecto si el agente del usuario no existe', function (): void {
    $user = User::factory()->create([
        'is_agent' => true,
        'agent_id' => 9_999_999,
        'status' => 'ACTIVO',
    ]);

    expect(CommercialNetworkAccess::prefersTopNavigation($user))->toBeTrue();
});

it('usa barras como tipo de gráfico si la agencia del usuario no existe', function (): void {
    $user = User::factory()->make([
        'is_agency' => true,
        'agency_type' => 'GENERAL',
        'code_agency' => 'TDG-SIN-REGISTRO',
    ]);

    expect(CommercialNetworkAccess::chartTypeForUser($user))->toBe('bar');
});

it('resuelve agencyIdForUser como null si la agencia no existe', function (): void {
    $user = User::factory()->make([
        'is_agency' => true,
        'agency_type' => 'GENERAL',
        'code_agency' => 'TDG-SIN-REGISTRO',
    ]);

    expect(CommercialNetworkAccess::agencyIdForUser($user))->toBeNull();
});

it('los widgets comerciales delegan getType en CommercialNetworkAccess', function (): void {
    $root = dirname(__DIR__, 2);
    $paths = array_merge(
        glob($root.'/app/Filament/General/Widgets/*Chart.php') ?: [],
        glob($root.'/app/Filament/Master/Widgets/*Chart.php') ?: [],
        glob($root.'/app/Filament/Agents/Widgets/*Chart.php') ?: [],
    );

    expect($paths)->not->toBeEmpty();

    foreach ($paths as $path) {
        $source = file_get_contents($path);
        expect($source)
            ->toContain('CommercialNetworkAccess::chartTypeForUser(Auth::user())')
            ->not->toContain('->first()->type_chart');
    }
});

it('los panel providers comerciales no acceden a id sobre agencia inexistente en el menú perfil', function (): void {
    $paths = [
        'app/Providers/Filament/GeneralPanelProvider.php',
        'app/Providers/Filament/MasterPanelProvider.php',
    ];

    foreach ($paths as $path) {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        expect($source)
            ->toContain('CommercialNetworkAccess::agencyIdForUser(Auth::user())')
            ->not->toContain("->first('id')->id");
    }
});

it('los panel providers comerciales delegan topNavigation en CommercialNetworkAccess', function (): void {
    $paths = [
        'app/Providers/Filament/GeneralPanelProvider.php',
        'app/Providers/Filament/MasterPanelProvider.php',
        'app/Providers/Filament/AgentsPanelProvider.php',
    ];

    foreach ($paths as $path) {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        expect($source)
            ->toContain('CommercialNetworkAccess::prefersTopNavigation(Auth::user())')
            ->not->toContain('->first()->conf_position_menu');
    }
});
