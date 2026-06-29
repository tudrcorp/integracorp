<?php

declare(strict_types=1);

use App\Models\Agency;
use App\Services\PublicAiAgent\ChatAgencyRepresentativeDocument;
use App\Services\PublicAiAgent\ChatAgentIdentityDocument;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

it('parsea rif j- eliminando el prefijo para agencias', function (): void {
    $parsed = ChatAgencyRepresentativeDocument::parse('j-123456789');

    expect($parsed)->not->toBeNull()
        ->and($parsed['kind'])->toBe(ChatAgentIdentityDocument::KIND_RIF)
        ->and($parsed['number'])->toBe('123456789')
        ->and($parsed['display'])->toBe('J-123456789');
});

it('parsea cedula v- eliminando el prefijo para agencias', function (): void {
    $parsed = ChatAgencyRepresentativeDocument::parse('v-12345678');

    expect($parsed)->not->toBeNull()
        ->and($parsed['kind'])->toBe(ChatAgentIdentityDocument::KIND_CI)
        ->and($parsed['number'])->toBe('12345678');
});

it('parsea cedula e- eliminando el prefijo para agencias', function (): void {
    $parsed = ChatAgencyRepresentativeDocument::parse('e-12345654');

    expect($parsed)->not->toBeNull()
        ->and($parsed['kind'])->toBe(ChatAgentIdentityDocument::KIND_CI)
        ->and($parsed['number'])->toBe('12345654');
});

it('aplica rif j- solo con el numero en agencies', function (): void {
    if (! Schema::hasTable('agencies')) {
        $this->markTestSkipped('Tabla agencies no disponible.');
    }

    $agency = new Agency;
    ChatAgencyRepresentativeDocument::applyRawInputToAgency($agency, 'j-23456789');

    expect($agency->rif)->toBe('23456789')
        ->and($agency->ci_responsable)->toBeNull();
});

it('aplica cedula v- solo con el numero en ci_responsable', function (): void {
    if (! Schema::hasTable('agencies')) {
        $this->markTestSkipped('Tabla agencies no disponible.');
    }

    $agency = new Agency;
    ChatAgencyRepresentativeDocument::applyRawInputToAgency($agency, 'v-16007868');

    expect($agency->ci_responsable)->toBe('16007868')
        ->and($agency->rif)->toBeNull();
});

it('aplica cedula e- solo con el numero en ci_responsable', function (): void {
    if (! Schema::hasTable('agencies')) {
        $this->markTestSkipped('Tabla agencies no disponible.');
    }

    $agency = new Agency;
    ChatAgencyRepresentativeDocument::applyRawInputToAgency($agency, 'E-12345654');

    expect($agency->ci_responsable)->toBe('12345654')
        ->and($agency->rif)->toBeNull();
});
