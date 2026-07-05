<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Users\Schemas\UserInfolist;
use Filament\Schemas\Schema;

it('configura el infolist User sin error', function (): void {
    $schema = Schema::make();
    $configured = UserInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('infolist de usuario usa pestañas del sistema', function (): void {
    $php = file_get_contents(__DIR__.'/../../app/Filament/Business/Resources/Users/Schemas/UserInfolist.php');

    expect($php)->not->toBeFalse()
        ->toContain('Tabs::make')
        ->toContain('persistTab')
        ->toContain('TABS_CONTAINER')
        ->toContain('IOS_SECTION_CLASS')
        ->toContain('IOS_INNER_CLASS')
        ->toContain("Tabs::make('userInfolistTabs')")
        ->toContain("Tab::make('Información del usuario')")
        ->toContain("Tab::make('Rol del usuario')")
        ->toContain('identity_card')
        ->toContain('Documento de identidad');
});
