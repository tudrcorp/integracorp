<?php

declare(strict_types=1);

use App\Filament\Marketing\Resources\InfoFrees\Schemas\InfoFreeInfolist;
use Filament\Schemas\Schema;

it('configura el infolist InfoFree sin error', function (): void {
    $schema = Schema::make();
    $configured = InfoFreeInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('aplica clases Liquid Glass en InfoFree y el tema', function (): void {
    $php = file_get_contents(__DIR__.'/../../app/Filament/Marketing/Resources/InfoFrees/Schemas/InfoFreeInfolist.php');
    $css = file_get_contents(__DIR__.'/../../resources/css/filament/admin/theme.css');

    expect($php)->not->toBeFalse()
        ->toContain('fi-capemiac-liquid-glass-section')
        ->toContain('fi-capemiac-liquid-glass-inset');

    expect($css)->not->toBeFalse()
        ->toContain('.fi-capemiac-liquid-glass-section .fi-section')
        ->toContain('backdrop-filter');
});
