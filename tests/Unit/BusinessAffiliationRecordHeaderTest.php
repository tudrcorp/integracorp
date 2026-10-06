<?php

declare(strict_types=1);

use App\Filament\Business\Resources\AffiliationCorporates\Pages\ViewAffiliationCorporate;
use App\Filament\Business\Resources\Affiliations\Pages\ViewAffiliation;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Solo lectura; la transacción revertida cubre lo que la página pudiera registrar al montarse.
 */
beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

it('las fichas de Negocios usan el mismo encabezado que Administración', function (string $business, string $administration): void {
    $businessSource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/'.$business);
    $administrationSource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/'.$administration);

    $extract = fn (string $source): string => trim((string) preg_replace('/\s+/', ' ', substr($source, strpos($source, 'return RecordPageHeader::render('), strpos($source, ');', strpos($source, 'return RecordPageHeader::render(')) - strpos($source, 'return RecordPageHeader::render('))));

    expect($businessSource)->toContain('RecordPageHeader::render(')
        ->and($extract($businessSource))->toBe($extract($administrationSource));
})->with([
    'corporativa' => ['AffiliationCorporates/Pages/ViewAffiliationCorporate.php', 'AffiliationCorporates/Pages/ViewAffiliationCorporate.php'],
    'individual' => ['Affiliations/Pages/ViewAffiliation.php', 'Affiliations/Pages/ViewAffiliation.php'],
]);

it('las dos fichas de Negocios montan con el encabezado', function (string $page, string $model): void {
    Filament::setCurrentPanel('business');
    $this->actingAs(User::query()->where('email', 'like', '%@tudrencasa.com')->where('is_superAdmin', 1)->firstOrFail());

    $record = $model::query()->latest('id')->firstOrFail();

    Livewire::test($page, ['record' => $record->getKey()])
        ->assertSuccessful()
        ->assertSee($record->code);
})->with([
    'corporativa' => [ViewAffiliationCorporate::class, AffiliationCorporate::class],
    'individual' => [ViewAffiliation::class, Affiliation::class],
]);
