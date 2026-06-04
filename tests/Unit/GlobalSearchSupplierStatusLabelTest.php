<?php

declare(strict_types=1);

use App\Support\Filament\GlobalSearchSupplierStatusLabel;

it('renderiza badge para estatus en sistema afiliado', function (): void {
    $html = GlobalSearchSupplierStatusLabel::sistemaHtml('AFILIADO');

    expect((string) $html)->toContain('AFILIADO')
        ->toContain('fi-global-search-supplier-badge--sistema-afiliado');
});

it('renderiza badge para convenio preferencial', function (): void {
    $html = GlobalSearchSupplierStatusLabel::convenioHtml('PREFERENCIAL');

    expect((string) $html)->toContain('PREFERENCIAL')
        ->toContain('fi-global-search-supplier-badge--convenio-preferencial');
});
