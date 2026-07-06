<?php

declare(strict_types=1);

use App\Support\Companies\CompanyAssociatesGroupPalette;

it('asigna colores de grupo de forma estable por responsable', function (): void {
    $first = CompanyAssociatesGroupPalette::forResponsibleId(12);
    $same = CompanyAssociatesGroupPalette::forResponsibleId(12);
    $different = CompanyAssociatesGroupPalette::forResponsibleId(13);

    expect($same)->toBe($first)
        ->and($different['tone'])->not->toBe($first['tone']);
});

it('tabla de asociados usa paleta de colores por grupo', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CompanyAssociates/Tables/CompanyAssociatesTable.php');
    $palette = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyAssociatesGroupPalette.php');

    expect($table)
        ->toContain('CompanyAssociatesGroupPalette::groupTitleLabel')
        ->toContain('CompanyAssociatesGroupPalette::groupDescriptionLabel')
        ->toContain('CompanyAssociatesGroupPalette::recordRowClasses');

    expect($palette)
        ->toContain('#03045E')
        ->toContain('#ADE8F4')
        ->toContain('associate-group-row--')
        ->toContain('forResponsibleId');
});
