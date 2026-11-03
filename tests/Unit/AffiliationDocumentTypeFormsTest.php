<?php

declare(strict_types=1);

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\User;
use App\Support\Affiliations\AffiliationDocumentTypeColumns;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    AffiliationDocumentTypeColumns::flush();
});

afterEach(fn () => DB::rollBack());

/**
 * Usuario real del panel (solo lectura): las páginas de alta dependen de su agente o agencia.
 */
function documentTypePanelUser(string $panel): ?User
{
    return match ($panel) {
        'agents' => User::query()->where('is_agent', true)->whereNotNull('agent_id')->where('status', 'ACTIVO')->first(),
        'master' => User::query()->where('agency_type', 'MASTER')->where('status', 'ACTIVO')->first(),
        'general' => User::query()->where('agency_type', 'GENERAL')->where('status', 'ACTIVO')->first(),
        default => User::query()->where('departament', 'like', '%SUPERADMIN%')->where('status', 'ACTIVO')->first(),
    };
}

it('las altas de cada panel muestran el tipo de documento del titular y del pagador', function (string $panel, string $page): void {
    $user = documentTypePanelUser($panel);

    if ($user === null || ! AffiliationDocumentTypeColumns::available()) {
        $this->markTestSkipped('Falta un usuario del panel o la migración de tipos de documento.');
    }

    Filament::setCurrentPanel($panel);
    $this->actingAs($user);

    Livewire::test($page)
        ->assertSuccessful()
        ->assertFormFieldExists('document_type_ti')
        ->assertFormFieldExists('document_type_payer')
        ->assertSet('data.document_type_ti', 'V')
        ->assertSet('data.document_type_payer', 'V');
})->with([
    'agentes' => ['agents', App\Filament\Agents\Resources\Affiliations\Pages\CreateAffiliation::class],
    'master' => ['master', App\Filament\Master\Resources\Affiliations\Pages\CreateAffiliation::class],
    'general' => ['general', App\Filament\General\Resources\Affiliations\Pages\CreateAffiliation::class],
    'negocios' => ['business', App\Filament\Business\Resources\Affiliations\Pages\CreateAffiliation::class],
]);

it('las altas corporativas sugieren J para el RIF', function (string $panel, string $page): void {
    $user = documentTypePanelUser($panel);

    if ($user === null || ! AffiliationDocumentTypeColumns::available()) {
        $this->markTestSkipped('Falta un usuario del panel o la migración de tipos de documento.');
    }

    Filament::setCurrentPanel($panel);
    $this->actingAs($user);

    Livewire::test($page)
        ->assertSuccessful()
        ->assertFormFieldExists('document_type_rif')
        ->assertSet('data.document_type_rif', 'J');
})->with([
    'master' => ['master', App\Filament\Master\Resources\AffiliationCorporates\Pages\CreateAffiliationCorporate::class],
    'general' => ['general', App\Filament\General\Resources\AffiliationCorporates\Pages\CreateAffiliationCorporate::class],
    'negocios' => ['business', App\Filament\Business\Resources\AffiliationCorporates\Pages\CreateAffiliationCorporate::class],
]);

it('la edición en Negocios propone el tipo deducido cuando la afiliación no lo tiene', function (): void {
    $user = documentTypePanelUser('business');
    $affiliation = Affiliation::query()->whereNotNull('nro_identificacion_ti')->latest('id')->first();

    if ($user === null || $affiliation === null || ! AffiliationDocumentTypeColumns::available()) {
        $this->markTestSkipped('Falta usuario, afiliación o migración.');
    }

    $affiliation->forceFill(['document_type_ti' => null, 'nro_identificacion_ti' => '84322352'])->saveQuietly();

    Filament::setCurrentPanel('business');
    $this->actingAs($user);

    Livewire::test(App\Filament\Business\Resources\Affiliations\Pages\EditAffiliation::class, ['record' => $affiliation->getKey()])
        ->assertSuccessful()
        ->assertSet('data.document_type_ti', 'E');
});

it('la edición corporativa en Negocios propone J y respeta la G elegida', function (): void {
    $user = documentTypePanelUser('business');
    $corporate = AffiliationCorporate::query()->latest('id')->first();

    if ($user === null || $corporate === null || ! AffiliationDocumentTypeColumns::available()) {
        $this->markTestSkipped('Falta usuario, afiliación corporativa o migración.');
    }

    Filament::setCurrentPanel('business');
    $this->actingAs($user);

    $corporate->forceFill(['document_type_rif' => null])->saveQuietly();
    Livewire::test(App\Filament\Business\Resources\AffiliationCorporates\Pages\EditAffiliationCorporate::class, ['record' => $corporate->getKey()])
        ->assertSuccessful()
        ->assertSet('data.document_type_rif', 'J');

    $corporate->forceFill(['document_type_rif' => 'G'])->saveQuietly();
    Livewire::test(App\Filament\Business\Resources\AffiliationCorporates\Pages\EditAffiliationCorporate::class, ['record' => $corporate->getKey()])
        ->assertSet('data.document_type_rif', 'G');
});

it('la edición en agentes, master y general no muestra el select', function (string $panel, string $page): void {
    $user = documentTypePanelUser($panel);
    $affiliation = $user !== null ? Affiliation::query()
        ->when($panel === 'agents', fn ($query) => $query->where('agent_id', $user->agent_id))
        ->when($panel !== 'agents', fn ($query) => $query->where('code_agency', $user->code_agency))
        ->latest('id')
        ->first() : null;

    if ($affiliation === null || ! AffiliationDocumentTypeColumns::available()) {
        $this->markTestSkipped('Falta un usuario del panel con afiliaciones propias.');
    }

    Filament::setCurrentPanel($panel);
    $this->actingAs($user);

    Livewire::test($page, ['record' => $affiliation->getKey()])
        ->assertSuccessful()
        ->assertFormFieldIsHidden('document_type_ti')
        ->assertFormFieldIsHidden('document_type_payer');
})->with([
    'agentes' => ['agents', App\Filament\Agents\Resources\Affiliations\Pages\EditAffiliation::class],
    'master' => ['master', App\Filament\Master\Resources\Affiliations\Pages\EditAffiliation::class],
    'general' => ['general', App\Filament\General\Resources\Affiliations\Pages\EditAffiliation::class],
]);
