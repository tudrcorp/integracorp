<?php

declare(strict_types=1);

use App\Filament\Business\Resources\WhiteCompanies\Schemas\WhiteCompanyForm;

it('expone el configurador de schema del formulario de empresas aliadas', function () {
    expect(method_exists(WhiteCompanyForm::class, 'configure'))->toBeTrue();
});

it('incluye el campo de credito asignado en el formulario de empresas aliadas', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/WhiteCompanies/Schemas/WhiteCompanyForm.php');

    expect($form)
        ->toContain("Section::make('Crédito asignado')")
        ->toContain("TextInput::make('assigned_credit')")
        ->toContain("label('Crédito asignado')")
        ->toContain('cancelar cuotas')
        ->toContain("Tab::make('Documentos de marca')");
});
