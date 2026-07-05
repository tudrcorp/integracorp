<?php

declare(strict_types=1);

use App\Support\Filament\UserModulesFormUi;

it('expone estilos y resumen de modulos para la pestaña dedicada', function (): void {
    $styles = file_get_contents(__DIR__.'/../../resources/views/filament/business/users/partials/modules-form-styles.blade.php');

    expect(UserModulesFormUi::stylesView())->toBe('filament.business.users.partials.modules-form-styles')
        ->and(UserModulesFormUi::modulesIntroHtml()->toHtml())->toContain('¿Qué son los módulos?')
        ->and(UserModulesFormUi::permissionsHintHtml()->toHtml())->toContain('Permisos')
        ->and(UserModulesFormUi::selectionSummaryHtml([])->toHtml())->toContain('Ningún módulo seleccionado')
        ->and(UserModulesFormUi::selectionSummaryHtml(['NEGOCIOS'])->toHtml())->toContain('1 módulo seleccionado')
        ->and(UserModulesFormUi::selectionSummaryHtml(['NEGOCIOS', 'ADMINISTRACION'])->toHtml())->toContain('2 módulos seleccionados')
        ->and($styles)->toContain('.user-modules-checkbox-list .fi-fo-checkbox-list-option:has(.fi-checkbox-input:checked)');
});
