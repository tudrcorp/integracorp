<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Filament\InternalPanelsQuickNavigation;
use Tests\TestCase;

uses(TestCase::class);

it('returns empty navigation items for guests', function (): void {
    expect(InternalPanelsQuickNavigation::navigationItems('business'))->toBeEmpty();
});

it('includes crear ticket first with helpdesk url scoped to host panel', function (): void {
    $user = User::factory()->create([
        'email' => 'superadmin-nav-'.uniqid('', true).'@tudrencasa.com',
        'departament' => ['SUPERADMIN'],
        'status' => 'ACTIVO',
        'is_admin' => true,
    ]);

    $this->actingAs($user);

    $itemsBusiness = InternalPanelsQuickNavigation::navigationItems('business');
    expect($itemsBusiness)->not->toBeEmpty()
        ->and($itemsBusiness[0]['kind'])->toBe('ticket')
        ->and($itemsBusiness[0]['label'])->toBe('Crear ticket')
        ->and($itemsBusiness[0]['url'])->toContain('business')
        ->and($itemsBusiness[0]['url'])->toContain('helpdesk');

    $itemsMarketing = InternalPanelsQuickNavigation::navigationItems('marketing');
    expect($itemsMarketing[0]['url'])->toContain('marketing')
        ->and($itemsMarketing[0]['url'])->toContain('helpdesk');
});

it('shows operations panel but not marketing for operations-only internal user', function (): void {
    $user = User::factory()->create([
        'email' => 'ops-nav-'.uniqid('', true).'@tudrencasa.com',
        'departament' => ['OPERACIONES'],
        'status' => 'ACTIVO',
    ]);

    $this->actingAs($user);

    $panelIds = collect(InternalPanelsQuickNavigation::navigationItems('operations'))
        ->where('kind', 'panel')
        ->pluck('panel_id')
        ->all();

    expect($panelIds)->toContain('operations')
        ->and($panelIds)->not->toContain('marketing');
});

it('shows negocios and operaciones when departament includes NEGOCIOS and OPERACIONES', function (): void {
    $user = User::factory()->create([
        'email' => 'multi-dept-'.uniqid('', true).'@tudrencasa.com',
        'departament' => ['NEGOCIOS', 'OPERACIONES'],
        'status' => 'ACTIVO',
    ]);

    $this->actingAs($user);

    $panelIds = collect(InternalPanelsQuickNavigation::navigationItems('business'))
        ->where('kind', 'panel')
        ->pluck('panel_id')
        ->all();

    expect($panelIds)->toContain('business')
        ->and($panelIds)->toContain('operations')
        ->and($panelIds)->not->toContain('marketing');
});

it('marks superadmin panel links inaccessible when module department is not assigned', function (): void {
    $user = User::factory()->create([
        'email' => 'superadmin-no-module-'.uniqid('', true).'@tudrencasa.com',
        'departament' => ['SUPERADMIN'],
        'status' => 'ACTIVO',
        'is_admin' => true,
    ]);

    $this->actingAs($user);

    $panels = collect(InternalPanelsQuickNavigation::navigationItems('business'))
        ->where('kind', 'panel')
        ->keyBy('panel_id');

    expect($panels->keys()->all())
        ->toContain('marketing')
        ->and($panels->get('marketing')['accessible'])->toBeFalse()
        ->and($panels->get('marketing')['denied_message'])
        ->toContain('No tiene acceso al módulo Marketing');
});

it('marks superadmin panel links accessible when module department is assigned', function (): void {
    $user = User::factory()->create([
        'email' => 'superadmin-with-negocios-'.uniqid('', true).'@tudrencasa.com',
        'departament' => ['SUPERADMIN', 'NEGOCIOS'],
        'status' => 'ACTIVO',
        'is_admin' => true,
    ]);

    $this->actingAs($user);

    $panels = collect(InternalPanelsQuickNavigation::navigationItems('business'))
        ->where('kind', 'panel')
        ->keyBy('panel_id');

    expect($panels->get('business')['accessible'])->toBeTrue()
        ->and($panels->get('marketing')['accessible'])->toBeFalse();
});

it('shows projects quick access for SUPERADMIN inside projects panel', function (): void {
    $user = User::factory()->create([
        'email' => 'superadmin-projects-nav-'.uniqid('', true).'@tudrencasa.com',
        'departament' => ['SUPERADMIN'],
        'status' => 'ACTIVO',
        'is_admin' => true,
    ]);

    $this->actingAs($user);

    $items = InternalPanelsQuickNavigation::navigationItems('projects');

    expect($items)->not->toBeEmpty()
        ->and($items[0]['kind'])->toBe('ticket')
        ->and($items[0]['label'])->toBe('Crear ticket')
        ->and($items[0]['url'])->toContain('business')
        ->and($items[0]['url'])->toContain('helpdesk');

    $panelIds = collect($items)
        ->where('kind', 'panel')
        ->pluck('panel_id')
        ->all();

    expect($panelIds)
        ->toContain('projects')
        ->toContain('business')
        ->toContain('administration')
        ->toContain('operations')
        ->toContain('marketing');
});

it('includes chat casos in operations quick navigation menu', function (): void {
    $user = User::factory()->create([
        'email' => 'ops-chat-nav-'.uniqid('', true).'@tudrencasa.com',
        'departament' => ['OPERACIONES'],
        'status' => 'ACTIVO',
    ]);

    $this->actingAs($user);

    $chatItem = collect(InternalPanelsQuickNavigation::navigationItems('operations'))
        ->firstWhere('kind', 'operations-chat');

    expect($chatItem)->not->toBeNull()
        ->and($chatItem['label'])->toBe('Chat casos')
        ->and($chatItem['subtitle'])->toBe('Seguimiento activo');
});

it('does not include chat casos outside operations panel', function (): void {
    $user = User::factory()->create([
        'email' => 'business-chat-nav-'.uniqid('', true).'@tudrencasa.com',
        'departament' => ['NEGOCIOS'],
        'status' => 'ACTIVO',
    ]);

    $this->actingAs($user);

    $chatItem = collect(InternalPanelsQuickNavigation::navigationItems('business'))
        ->firstWhere('kind', 'operations-chat');

    expect($chatItem)->toBeNull();
});

it('uses filament notification when superadmin clicks a module without access', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/panels/internal-quick-nav.blade.php');

    expect($source)
        ->toContain('FilamentNotification')
        ->toContain('fi-business-panel-stepper-segment--restricted')
        ->toContain('denied_message');
});
