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
