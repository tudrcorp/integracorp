<?php

declare(strict_types=1);

it('personaliza el título de edición individual sin la palabra Editar', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Pages/EditAffiliation.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain('public function getTitle(): string|Htmlable')
        ->toContain('full_name_ti')
        ->toContain('nro_identificacion_ti')
        ->toContain('RIF/CI:')
        ->toContain('Plan(es):')
        ->toContain('afiliados')
        ->toContain('plan?->description')
        ->toContain('affiliates()')
        ->not->toContain("'Editar")
        ->not->toContain('"Editar')
        ->not->toContain('Editar Afiliación Individual');
});
