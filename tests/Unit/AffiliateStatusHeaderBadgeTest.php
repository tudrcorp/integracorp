<?php

declare(strict_types=1);

use App\Support\Filament\AffiliateStatusHeaderBadge;

it('pinta ACTIVO en verde', function (): void {
    $html = AffiliateStatusHeaderBadge::html('ACTIVO');

    expect($html)->toContain('#28cd41')->and($html)->toContain('ACTIVO');
});

it('pinta INACTIVO en rojo', function (): void {
    $html = AffiliateStatusHeaderBadge::html('INACTIVO');

    expect($html)->toContain('#ff3b30')->and($html)->toContain('INACTIVO');
});

it('pinta PENDIENTE en naranja', function (): void {
    $html = AffiliateStatusHeaderBadge::html('PENDIENTE');

    expect($html)->toContain('#ff9f0a')->and($html)->toContain('PENDIENTE');
});

it('muestra Sin estado cuando no hay estatus', function (): void {
    $html = AffiliateStatusHeaderBadge::html(null);

    expect($html)->toContain('#8e8e93')->and($html)->toContain('Sin estado');
});
