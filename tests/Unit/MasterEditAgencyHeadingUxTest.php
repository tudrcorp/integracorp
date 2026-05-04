<?php

declare(strict_types=1);

it('pone la razón social (o el código) en el título del perfil de agencia master', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Master/Resources/Agencies/Pages/EditAgency.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('public function getTitle(): string|Htmlable')
        ->toContain("sprintf('Perfil de la agencia · %s', e(\$displayName))")
        ->toContain('name_corporative')
        ->toContain('Perfil de la agencia');
});

it('usa subtítulo con jerarquía visual y guía para guardar cambios', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Master/Resources/Agencies/Pages/EditAgency.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('Illuminate\Support\HtmlString')
        ->toContain('max-w-2xl')
        ->toContain('dark:text-zinc-400')
        ->toContain('Guarda al final del formulario para aplicar los cambios.')
        ->toContain('Revisa y actualiza contacto, facturación y preferencias.');
});
