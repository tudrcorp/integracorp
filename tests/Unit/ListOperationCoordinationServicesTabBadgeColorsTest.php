<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ListOperationCoordinationServices;
use Filament\Support\Colors\Color;

it('genera paletas con tono 50 para badgeColor de tabs', function () {
    foreach (['#ffc107', '#ffcc00', '#28cd41', '#ff3b30'] as $hex) {
        $palette = Color::hex($hex);
        expect($palette)->toHaveKeys([50, 400, 500, 600, 900, 950]);
    }
});

it('usa tabs de estado con scroll horizontal en mobile y sin etiqueta de convenio', function () {
    $page = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Pages/ListOperationCoordinationServices.php'
    );
    $theme = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');

    expect(method_exists(ListOperationCoordinationServices::class, 'getTabsContentComponent'))->toBeTrue();

    expect($page)
        ->toContain('fi-status-filter-tabs-ios fi-supplier-status-tabs-ios')
        ->not->toContain('fi-supplier-convenio-tabs-ios');

    expect($theme)
        ->toContain('.fi-status-filter-tabs-ios.fi-sc-tabs::before')
        ->toContain("content: 'Estado'")
        ->toContain('@media (max-width: 767px)')
        ->toContain('flex-wrap: nowrap')
        ->toContain('overflow-x: auto');
});
