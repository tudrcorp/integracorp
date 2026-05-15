<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

it('redirige a invitado al exportar reportes de agentes administración', function (): void {
    $this->get(route('administration.agents.reports.export', [
        'report' => 'commission_percentages',
        'format' => 'csv',
    ]))->assertRedirect();
});

it('rechaza reporte inválido para usuario autenticado (agentes)', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/')
        ->get(route('administration.agents.reports.export', [
            'report' => 'no_existe',
            'format' => 'csv',
        ]))
        ->assertRedirect('/')
        ->assertSessionHasErrors(['report']);
});

it('registra la ruta de exportación de agentes', function (): void {
    expect(Route::has('administration.agents.reports.export'))->toBeTrue();
});

it('devuelve csv de comisiones de agentes con columna Estatus', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('administration.agents.reports.export', [
            'report' => 'commission_percentages',
            'format' => 'csv',
        ]))
        ->assertOk()
        ->assertHeaderContains('content-type', 'text/csv');

    expect($response->streamedContent())->toContain('Estatus')
        ->and($response->streamedContent())->toContain('Nat. nombre beneficiario');
});

it('devuelve csv de estatus de agentes sin error de agregación', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('administration.agents.reports.export', [
            'report' => 'agent_status',
            'format' => 'csv',
        ]))
        ->assertOk()
        ->assertHeaderContains('content-type', 'text/csv');
});
