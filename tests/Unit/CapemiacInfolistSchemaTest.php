<?php

declare(strict_types=1);

use App\Filament\Marketing\Resources\Capemiacs\Schemas\CapemiacInfolist;
use Filament\Schemas\Schema;

it('configura el infolist Capemiac con estilos marketing iOS sin error', function (): void {
    $schema = Schema::make();
    $configured = CapemiacInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('aplica clases Liquid Glass experimentales en Capemiac y el tema', function (): void {
    $php = file_get_contents(__DIR__.'/../../app/Filament/Marketing/Resources/Capemiacs/Schemas/CapemiacInfolist.php');
    $css = file_get_contents(__DIR__.'/../../resources/css/filament/admin/theme.css');

    expect($php)->not->toBeFalse()
        ->toContain('fi-capemiac-liquid-glass-section')
        ->toContain('fi-capemiac-liquid-glass-inset');

    expect($css)->not->toBeFalse()
        ->toContain('.fi-capemiac-liquid-glass-section .fi-section')
        ->toContain('backdrop-filter');
});
