<?php

declare(strict_types=1);

function storefrontDownloadZonePath(string $path): string
{
    return dirname(__DIR__, 2).'/'.ltrim($path, '/');
}

it('el menu de la pwa ofrece zona de descarga', function (): void {
    $nav = file_get_contents(storefrontDownloadZonePath('app/Support/Storefront/StorefrontNav.php'));
    $icon = file_get_contents(storefrontDownloadZonePath('resources/views/storefront/partials/nav-icon.blade.php'));

    expect($nav)
        ->toContain("'downloads', 'Zona de Descarga'")
        ->toContain("'storefront.download-zone'")
        ->toContain("'storefront.download-zone.feed'")
        ->toContain("'label' => 'Volver a carpetas'")
        ->and($icon)->toContain("'downloads'");
});

it('la portada muestra carpetas en dos columnas y oculta las vacias', function (): void {
    $page = file_get_contents(storefrontDownloadZonePath('resources/views/livewire/volt/app/download-zone.blade.php'));
    $catalog = file_get_contents(storefrontDownloadZonePath('app/Support/Storefront/StorefrontDownloadZoneCatalog.php'));
    $css = file_get_contents(storefrontDownloadZonePath('resources/css/storefront.css'));

    expect($page)
        ->toContain('Zona de Descarga')
        ->toContain('sf-albums')
        ->toContain('sf-album')
        ->toContain('$container[\'url\']')
        ->toContain('Aún no hay carpetas')
        ->not->toContain('setZone')
        ->not->toContain('sf-download-card')
        ->and($catalog)->toContain('function containers')
        ->and($catalog)->toContain("route('storefront.download-zone.feed'")
        ->and($catalog)->toContain('if ($items === null || $items->isEmpty())')
        ->and($catalog)->toContain('continue;')
        ->and($catalog)->toContain('function resolveImageAbsolutePath')
        ->and($catalog)->toContain('function imageUrl')
        ->and($catalog)->toContain("route('storefront.documents.download-zone-image'")
        ->and($catalog)->not->toContain('function publicUrl')
        ->and($page)->toContain('onerror="this.hidden = true"')
        ->and($page)->toContain('sf-album__placeholder')
        ->and($css)->toContain('.sf-albums')
        ->toContain('grid-template-columns: 1fr 1fr')
        ->toContain('.sf-album');
});

it('el feed instagram permite swipe, doble toque y descargar', function (): void {
    $page = file_get_contents(storefrontDownloadZonePath('resources/views/livewire/volt/app/download-zone-feed.blade.php'));
    $catalog = file_get_contents(storefrontDownloadZonePath('app/Support/Storefront/StorefrontDownloadZoneCatalog.php'));
    $controller = file_get_contents(storefrontDownloadZonePath('app/Http/Controllers/Storefront/StorefrontDownloadZoneController.php'));
    $routes = file_get_contents(storefrontDownloadZonePath('routes/storefront.php'));
    $css = file_get_contents(storefrontDownloadZonePath('resources/css/storefront.css'));
    $migration = file_get_contents(storefrontDownloadZonePath('database/migrations/2026_09_10_220000_create_download_zone_likes_table.php'));
    $model = file_get_contents(storefrontDownloadZonePath('app/Models/DownloadZone.php'));

    expect($page)
        ->toContain('sf-reel')
        ->toContain('onTap()')
        ->toContain('this.like(true)')
        ->toContain('fromDouble && this.liked')
        ->toContain('toggleLike')
        ->toContain('skipRender')
        ->toContain('downloadFile')
        ->toContain('Descargar')
        ->toContain('Me gusta')
        ->and($catalog)->toContain('function posts')
        ->and($catalog)->toContain('function toggleLike')
        ->and($catalog)->toContain('lockForUpdate')
        ->and($catalog)->toContain('DownloadZoneLike')
        ->and($catalog)->toContain('storefront.documents.download-zone')
        ->and($catalog)->not->toContain('Notification::make')
        ->and($controller)->toContain('findDownloadable')
        ->and($controller)->toContain('function image')
        ->and($routes)->toContain("Volt::route('/zona-de-descarga', 'volt.app.download-zone')")
        ->and($routes)->toContain('storefront.documents.download-zone-image')
        ->and($routes)->toContain("Volt::route('/zona-de-descarga/{zone}', 'volt.app.download-zone-feed')")
        ->and($routes)->toContain('storefront.download-zone.feed')
        ->and($css)->toContain('.sf-reel')
        ->toContain('scroll-snap-type: y mandatory')
        ->toContain('.sf-reel__like.is-on')
        ->toContain('sf-heart-burst')
        ->and($migration)->toContain("Schema::hasTable('download_zone_likes')")
        ->and($migration)->toContain('download_zone_likes_user_document_unique')
        ->and($model)->toContain('function likes');
});
