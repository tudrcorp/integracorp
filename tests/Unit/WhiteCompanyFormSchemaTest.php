<?php

declare(strict_types=1);

use App\Filament\Business\Resources\WhiteCompanies\Schemas\WhiteCompanyForm;

it('expone el configurador de schema del formulario de empresas aliadas', function () {
    expect(method_exists(WhiteCompanyForm::class, 'configure'))->toBeTrue();
});
