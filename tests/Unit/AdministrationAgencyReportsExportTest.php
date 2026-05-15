<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

it('redirige a invitado al exportar reportes de agencias administración', function (): void {
    $this->get(route('administration.agencies.reports.export', [
        'report' => 'commission_percentages',
        'format' => 'csv',
    ]))->assertRedirect();
});

it('rechaza reporte o formato inválido para usuario autenticado', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('administration.agencies.reports.export', [
            'report' => 'no_existe',
            'format' => 'csv',
        ]))
        ->assertSessionHasErrors(['report']);
});

it('rechaza formato distinto de csv o xlsx', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('administration.agencies.reports.export', [
            'report' => 'commission_percentages',
            'format' => 'pdf',
        ]))
        ->assertSessionHasErrors(['format']);
});

it('registra la ruta con nombre esperado', function (): void {
    expect(Route::has('administration.agencies.reports.export'))->toBeTrue();
});

it('devuelve csv de comisiones para usuario autenticado', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('administration.agencies.reports.export', [
            'report' => 'commission_percentages',
            'format' => 'csv',
        ]))
        ->assertOk()
        ->assertHeaderContains('content-type', 'text/csv');

    expect($response->streamedContent())->toContain('Estatus')
        ->and($response->streamedContent())->toContain('Nat. nombre beneficiario');
});

it('devuelve csv de estatus sin error de agregación (group by alineado con select)', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('administration.agencies.reports.export', [
            'report' => 'agency_status',
            'format' => 'csv',
        ]))
        ->assertOk()
        ->assertHeaderContains('content-type', 'text/csv');
});
