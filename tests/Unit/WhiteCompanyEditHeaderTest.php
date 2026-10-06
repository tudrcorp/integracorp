<?php

declare(strict_types=1);

use App\Filament\Business\Resources\WhiteCompanies\Pages\EditWhiteCompany;
use App\Models\User;
use App\Models\WhiteCompany;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Solo monta la página; la transacción revertida cubre lo que pudiera registrar.
 */
beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

it('la edición de la empresa aliada usa el encabezado del sistema con su nombre, logo o iniciales y sus datos', function (): void {
    $company = WhiteCompany::query()->first();

    if ($company === null) {
        $this->markTestSkipped('No hay empresas aliadas en esta base.');
    }

    Filament::setCurrentPanel('business');
    $this->actingAs(User::query()->where('email', 'like', '%@tudrencasa.com')->where('is_superAdmin', 1)->firstOrFail());

    $page = Livewire::test(EditWhiteCompany::class, ['record' => $company->getKey()])
        ->assertSuccessful()
        ->assertSee('Empresa aliada · Editar información')
        ->assertSee(trim((string) $company->name))
        ->assertSee('planes asignados', false);

    expect($page->instance()->getTitle())->toBe('Editar '.trim((string) $company->name));
});

it('sin logo cargado avisa dónde subirlo', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/WhiteCompanies/Pages/EditWhiteCompany.php');

    expect($source)
        ->toContain('RecordPageHeader::render(')
        ->toContain("Storage::disk('public')->url((string) \$company->logo)")
        ->toContain('Sin logo: súbalo en la marca de documentos');
});
