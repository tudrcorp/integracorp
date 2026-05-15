<?php

declare(strict_types=1);

use App\Support\Filament\GlobalSearchAffiliateStatusLabel;
use Illuminate\Contracts\Support\Htmlable;

it('resalta ACTIVO en verde', function (): void {
    $html = GlobalSearchAffiliateStatusLabel::html('ACTIVO');
    $markup = $html instanceof Htmlable ? $html->toHtml() : (string) $html;

    expect($markup)->toContain('emerald')->and($markup)->toContain('ACTIVO');
});

it('resalta INACTIVO en rojo', function (): void {
    $html = GlobalSearchAffiliateStatusLabel::html('INACTIVO');
    $markup = $html instanceof Htmlable ? $html->toHtml() : (string) $html;

    expect($markup)->toContain('rose')->and($markup)->toContain('INACTIVO');
});

it('devuelve guión sin estatus', function (): void {
    expect(GlobalSearchAffiliateStatusLabel::html(null))->toBe('—')
        ->and(GlobalSearchAffiliateStatusLabel::html(''))->toBe('—');
});
