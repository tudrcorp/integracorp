<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Users\Schemas\UserInfolist;
use Filament\Schemas\Schema;

it('configura el infolist User sin error', function (): void {
    $schema = Schema::make();
    $configured = UserInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('aplica clases Liquid Glass en User (Business) y el tema', function (): void {
    $php = file_get_contents(__DIR__.'/../../app/Filament/Business/Resources/Users/Schemas/UserInfolist.php');
    $css = file_get_contents(__DIR__.'/../../resources/css/filament/admin/theme.css');

    expect($php)->not->toBeFalse()
        ->toContain('fi-capemiac-liquid-glass-section')
        ->toContain('fi-capemiac-liquid-glass-inset');

    expect($css)->not->toBeFalse()
        ->toContain('.fi-capemiac-liquid-glass-section .fi-section')
        ->toContain('backdrop-filter');
});
