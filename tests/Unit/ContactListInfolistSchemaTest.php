<?php

declare(strict_types=1);

use App\Filament\Marketing\Resources\ContactLists\Schemas\ContactListInfolist;
use Filament\Schemas\Schema;

it('configura el infolist ContactList sin error', function (): void {
    $schema = Schema::make();
    $configured = ContactListInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('aplica clases Liquid Glass en ContactList y el tema', function (): void {
    $php = file_get_contents(__DIR__.'/../../app/Filament/Marketing/Resources/ContactLists/Schemas/ContactListInfolist.php');
    $css = file_get_contents(__DIR__.'/../../resources/css/filament/admin/theme.css');

    expect($php)->not->toBeFalse()
        ->toContain('fi-capemiac-liquid-glass-section')
        ->toContain('fi-capemiac-liquid-glass-inset');

    expect($css)->not->toBeFalse()
        ->toContain('.fi-capemiac-liquid-glass-section .fi-section')
        ->toContain('backdrop-filter');
});
