<?php

declare(strict_types=1);

use App\Support\Operations\AffiliateStatusPresentation;

it('AffiliateStatusPresentation mapea cada estatus real a su color y etiqueta', function (?string $status, string $label, string $color, string $bg): void {
    expect(AffiliateStatusPresentation::label($status))->toBe($label)
        ->and(AffiliateStatusPresentation::filamentColor($status))->toBe($color)
        ->and(AffiliateStatusPresentation::headerBadgeStyle($status)['bg'])->toBe($bg);
})->with([
    'activo' => ['ACTIVO', 'ACTIVO', 'success', '#28cd41'],
    'activa' => ['ACTIVA', 'ACTIVA', 'success', '#28cd41'],
    'pendiente' => ['PENDIENTE', 'PENDIENTE', 'warning', '#ffcc00'],
    'inactivo' => ['INACTIVO', 'INACTIVO', 'danger', '#ff3b30'],
    'excluido' => ['EXCLUIDO', 'EXCLUIDO', 'danger', '#ff3b30'],
    'pre-aprobada' => ['PRE-APROBADA', 'PRE-APROBADA', 'gray', '#8e8e93'],
    'minusculas' => ['activo', 'ACTIVO', 'success', '#28cd41'],
    'con espacios' => ['  inactivo  ', 'INACTIVO', 'danger', '#ff3b30'],
    'vacio' => ['', 'SIN ESTATUS', 'gray', '#8e8e93'],
    'nulo' => [null, 'SIN ESTATUS', 'gray', '#8e8e93'],
]);

it('la sombra del badge del encabezado acompaña al color de fondo', function (): void {
    expect(AffiliateStatusPresentation::headerBadgeStyle('ACTIVO')['shadow'])->toContain('40, 205, 65')
        ->and(AffiliateStatusPresentation::headerBadgeStyle('INACTIVO')['shadow'])->toContain('255, 59, 48')
        ->and(AffiliateStatusPresentation::headerBadgeStyle('PENDIENTE')['shadow'])->toContain('255, 204, 0')
        ->and(AffiliateStatusPresentation::headerBadgeStyle(null)['shadow'])->toContain('142, 142, 147');
});

it('el encabezado de la ficha del afiliado corporativo toma el estatus del registro y no un literal', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/AffiliateCorporates/Pages/ViewAffiliateCorporate.php');
    expect($page)->not->toBeFalse();

    expect($page)
        ->toContain('AffiliateStatusPresentation::label($affiliate->status)')
        ->toContain('AffiliateStatusPresentation::headerBadgeStyle($affiliate->status)')
        ->toContain("'background-color: '.\$badgeStyle['bg'].'; '")
        ->toContain("'box-shadow: '.\$badgeStyle['shadow'].'; '")
        ->toContain('e($statusLabel)')
        ->not->toContain('●</span> ACTIVO')
        ->not->toContain('background-color: #28cd41');
});

it('el infolist del afiliado corporativo comparte la misma fuente de estatus que el encabezado', function (): void {
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/AffiliateCorporates/Schemas/AffiliateCorporateInfolist.php');
    expect($infolist)->not->toBeFalse();

    expect($infolist)
        ->toContain('return AffiliateStatusPresentation::filamentColor($state);')
        ->toContain("->label('Estatus afiliado')")
        ->toContain('->state(fn (AffiliateCorporate $record): string => AffiliateStatusPresentation::label($record->status))')
        ->toContain('->color(fn (AffiliateCorporate $record): string => self::statusColor($record->status)),');
});
