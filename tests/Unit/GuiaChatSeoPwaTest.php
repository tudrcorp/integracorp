<?php

declare(strict_types=1);

function guiaChatBasePath(string $path): string
{
    return dirname(__DIR__, 2).'/'.ltrim($path, '/');
}

it('el chat publico usa layout con seo y pwa de guia-chat', function (): void {
    $volt = file_get_contents(guiaChatBasePath('resources/views/livewire/volt/public/ai_chat.blade.php'));
    $layout = file_get_contents(guiaChatBasePath('resources/views/components/layouts/guia-chat.blade.php'));
    $head = file_get_contents(guiaChatBasePath('resources/views/partials/guia-chat-head.blade.php'));

    expect($volt)->toContain("Layout('components.layouts.guia-chat')")
        ->and($layout)->toContain("@include('partials.guia-chat-head')")
        ->and($layout)->toContain('install-guia-chat')
        ->and($layout)->toContain('guia-chat-app')
        ->and($head)->toContain('<title>GUIA-CHAT | Integracorp')
        ->and($head)->toContain('meta name="robots" content="index, follow"')
        ->and($head)->toContain('link rel="canonical"')
        ->and($head)->toContain('guia-chat.webmanifest')
        ->and($head)->toContain('WebApplication')
        ->and($head)->toContain('-webkit-fill-available')
        ->and($volt)->toContain('guia-chat-shell')
        ->and($volt)->toContain('env(safe-area-inset-bottom');
});

it('el json ld del head escapa la directiva blade context', function (): void {
    $head = file_get_contents(guiaChatBasePath('resources/views/partials/guia-chat-head.blade.php'));

    expect($head)
        ->toContain("'@'.'context' => 'https://schema.org'")
        ->not->toContain("'@context' =>");
});

it('existe manifest pwa y service worker de guia-chat', function (): void {
    $manifest = file_get_contents(guiaChatBasePath('public/pwa/guia-chat.webmanifest'));
    $sw = file_get_contents(guiaChatBasePath('public/chat/publico/sw.js'));
    $install = file_get_contents(guiaChatBasePath('resources/views/pwa/install-guia-chat.blade.php'));

    expect($manifest)
        ->toContain('"short_name": "GUIA-CHAT"')
        ->toContain('"start_url": "/chat/publico"')
        ->toContain('/pwa/guia-chat/icon-192.png');

    expect($sw)->toContain("self.addEventListener('install'")
        ->and($sw)->toContain('/chat/publico')
        ->and($sw)->toContain("request.mode === 'navigate'")
        ->and($sw)->not->toContain('(cached) => cached || fetch(event.request)');

    expect($install)
        ->toContain("register('/chat/publico/sw.js")
        ->toContain("scope: '/chat/publico/'")
        ->toContain('installPlatform === \'ios\'')
        ->toContain('Agregar a pantalla de inicio');

    expect(file_exists(guiaChatBasePath('public/pwa/guia-chat/icon-192.png')))->toBeTrue()
        ->and(file_exists(guiaChatBasePath('public/pwa/guia-chat/icon-512.png')))->toBeTrue()
        ->and(file_exists(guiaChatBasePath('public/pwa/guia-chat/apple-touch-icon.png')))->toBeTrue()
        ->and(file_exists(guiaChatBasePath('public/chat/publico/sw.js')))->toBeTrue();
});

it('sitemap incluye la url del chat publico', function (): void {
    $sitemap = file_get_contents(guiaChatBasePath('public/sitemap.xml'));

    expect($sitemap)
        ->toContain('https://integracorp.tudrgroup.com/chat/publico')
        ->not->toContain('{{ now()');
});

it('ruta guia-chat redirige al chat publico', function (): void {
    $routes = file_get_contents(guiaChatBasePath('routes/web.php'));

    expect($routes)
        ->toContain("Route::redirect('/guia-chat', '/chat/publico', 301)")
        ->toContain("->name('guia-chat')");
});

it('htaccess envia chat publico a laravel aunque exista el directorio del service worker', function (): void {
    $htaccess = file_get_contents(guiaChatBasePath('public/.htaccess'));

    expect($htaccess)
        ->toContain('RewriteRule ^chat/publico/?$ index.php [L]')
        ->and(is_dir(guiaChatBasePath('public/chat/publico')))->toBeTrue()
        ->and(file_exists(guiaChatBasePath('public/chat/publico/sw.js')))->toBeTrue();
});

it('incluye indicador de escribiendo con animacion en el head del chat', function (): void {
    $head = file_get_contents(guiaChatBasePath('resources/views/partials/guia-chat-head.blade.php'));
    $typing = file_get_contents(guiaChatBasePath('resources/views/pwa/partials/guia-chat-typing-indicator.blade.php'));

    expect($head)->toContain('guia-chat-typing-dot')
        ->and($typing)->toContain('Escribiendo')
        ->and($typing)->toContain('isTypingVisible()');
});
