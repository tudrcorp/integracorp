<?php

declare(strict_types=1);

use App\Services\PublicAiAgent\ChatAgentIdentityDocument;

it('parsea cedula v- eliminando el prefijo', function (): void {
    $parsed = ChatAgentIdentityDocument::parse('v-16007868');

    expect($parsed)->not->toBeNull()
        ->and($parsed['kind'])->toBe(ChatAgentIdentityDocument::KIND_CI)
        ->and($parsed['number'])->toBe('16007868')
        ->and($parsed['display'])->toBe('V-16007868');
});

it('parsea cedula e- eliminando el prefijo', function (): void {
    $parsed = ChatAgentIdentityDocument::parse('e-12321345');

    expect($parsed)->not->toBeNull()
        ->and($parsed['kind'])->toBe(ChatAgentIdentityDocument::KIND_CI)
        ->and($parsed['number'])->toBe('12321345');
});

it('parsea rif j- eliminando el prefijo', function (): void {
    $parsed = ChatAgentIdentityDocument::parse('j-23456789');

    expect($parsed)->not->toBeNull()
        ->and($parsed['kind'])->toBe(ChatAgentIdentityDocument::KIND_RIF)
        ->and($parsed['number'])->toBe('23456789');
});

it('rechaza documentos sin prefijo valido', function (): void {
    expect(ChatAgentIdentityDocument::parse('16007868'))->toBeNull()
        ->and(ChatAgentIdentityDocument::parse('g-12345678'))->toBeNull();
});
