<?php

declare(strict_types=1);

use App\Filament\Business\Resources\AffiliationCorporates\Pages\ViewAffiliationCorporate;
use App\Models\AffiliationCorporate;
use App\Models\AffiliationCorporateDocument;
use App\Models\AffiliationCorporateObservation;
use App\Models\User;
use App\Support\AffiliationCorporates\ExpedienteDocumentDeletion;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Escriben en la base: transacción que siempre se revierte. Los discos `public`
 * y `local` son falsos: no se toca ningún archivo real.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Storage::fake('public');
    Storage::fake('local');

    $this->actingAs(User::factory()->create([
        'status' => 'ACTIVO',
        'email' => 'qa.expediente.'.uniqid().'@tudrencasa.com',
        'departament' => ['SUPERADMIN', 'NEGOCIOS'],
    ]));
    Filament::setCurrentPanel('business');

    $this->affiliation = AffiliationCorporate::query()->firstOrFail();
});

afterEach(fn () => DB::rollBack());

function expedienteTestDocument(AffiliationCorporate $affiliation, string $name = 'factura.pdf'): AffiliationCorporateDocument
{
    $path = 'affiliation-corporate-documents/qa-'.uniqid().'.pdf';
    Storage::disk('public')->put($path, '%PDF-1.4 prueba');

    return AffiliationCorporateDocument::query()->create([
        'affiliation_corporate_id' => $affiliation->getKey(),
        'file_path' => $path,
        'original_name' => $name,
        'mime_type' => 'application/pdf',
        'file_size' => 15,
    ]);
}

function expedienteTestPage(AffiliationCorporate $affiliation): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::test(ViewAffiliationCorporate::class, ['record' => $affiliation->getKey()])
        ->set('affiliationCorporateInfolistTab', 'expediente');
}

it('el botón de eliminar abre la confirmación y, con motivo, elimina el documento dejando traza', function (): void {
    $document = expedienteTestDocument($this->affiliation, 'FACTURA REPSOL.pdf');
    $observationsBefore = AffiliationCorporateObservation::query()->where('affiliation_corporate_id', $this->affiliation->getKey())->count();

    $page = expedienteTestPage($this->affiliation)
        ->call('mountAction', 'deleteExpedienteDocument', ['document' => $document->getKey()]);

    expect($page->instance()->mountedActions)->toHaveCount(1);

    $page->set('mountedActions.0.data.reason', 'El cliente envió una versión corregida')
        ->callMountedAction()
        ->assertHasNoErrors()
        ->assertNotified('Documento eliminado');

    expect(AffiliationCorporateDocument::query()->whereKey($document->getKey())->exists())->toBeFalse();

    $observation = AffiliationCorporateObservation::query()
        ->where('affiliation_corporate_id', $this->affiliation->getKey())
        ->latest('id')
        ->first();

    expect(AffiliationCorporateObservation::query()->where('affiliation_corporate_id', $this->affiliation->getKey())->count())->toBe($observationsBefore + 1)
        ->and($observation->description)->toContain('FACTURA REPSOL.pdf')->toContain('El cliente envió una versión corregida')
        ->and($observation->created_by)->toBe((string) auth()->id());

    $archived = Storage::disk('local')->allFiles(ExpedienteDocumentDeletion::ARCHIVE_DIRECTORY.'/'.$this->affiliation->getKey());

    expect($archived)->toHaveCount(1)
        ->and(Storage::disk('local')->get($archived[0]))->toBe('%PDF-1.4 prueba');
});

it('sin motivo no elimina nada', function (): void {
    $document = expedienteTestDocument($this->affiliation);

    expedienteTestPage($this->affiliation)
        ->call('mountAction', 'deleteExpedienteDocument', ['document' => $document->getKey()])
        ->set('mountedActions.0.data.reason', '')
        ->callMountedAction()
        ->assertHasErrors();

    expect(AffiliationCorporateDocument::query()->whereKey($document->getKey())->exists())->toBeTrue();
});

it('no elimina un documento de otra afiliación', function (): void {
    $other = AffiliationCorporate::query()->whereKeyNot($this->affiliation->getKey())->firstOrFail();
    $foreign = expedienteTestDocument($other);

    expedienteTestPage($this->affiliation)
        ->call('mountAction', 'deleteExpedienteDocument', ['document' => $foreign->getKey()])
        ->set('mountedActions.0.data.reason', 'Intento cruzado de prueba')
        ->callMountedAction()
        ->assertNotified('El documento ya no existe');

    expect(AffiliationCorporateDocument::query()->whereKey($foreign->getKey())->exists())->toBeTrue();
});

it('el servicio exige motivo y aborta si no puede resguardar el archivo', function (): void {
    $document = expedienteTestDocument($this->affiliation);

    expect(fn () => ExpedienteDocumentDeletion::delete($this->affiliation, $document->getKey(), 'no', auth()->user()))
        ->toThrow(InvalidArgumentException::class, 'motivo');

    expect(AffiliationCorporateDocument::query()->whereKey($document->getKey())->exists())->toBeTrue();
});

it('si el archivo ya no estaba en el servidor, igual quita el registro y lo deja anotado', function (): void {
    $document = expedienteTestDocument($this->affiliation);
    Storage::disk('public')->delete($document->file_path);

    $result = ExpedienteDocumentDeletion::delete($this->affiliation, $document->getKey(), 'Archivo dañado en el servidor', auth()->user());

    expect($result['archived_path'])->toBeNull()
        ->and(AffiliationCorporateDocument::query()->whereKey($document->getKey())->exists())->toBeFalse()
        ->and(AffiliationCorporateObservation::query()->where('affiliation_corporate_id', $this->affiliation->getKey())->latest('id')->value('description'))
        ->toContain('ya no estaba en el servidor');
});

it('el botón de descargar entrega el archivo', function (): void {
    $document = expedienteTestDocument($this->affiliation, 'tracking.pdf');

    expedienteTestPage($this->affiliation)
        ->call('mountAction', 'downloadExpedienteDocument', ['document' => $document->getKey()])
        ->assertFileDownloaded('tracking.pdf');
});

it('la fila del expediente usa botones que abren las acciones de la página', function (): void {
    $document = expedienteTestDocument($this->affiliation);
    $html = App\Filament\Business\Resources\AffiliationCorporates\Schemas\AffiliationCorporateInfolist::expedienteRowActions($document)->toHtml();

    expect($html)->toContain("wire:click=\"mountAction('deleteExpedienteDocument', { document: ".$document->getKey().' })"')
        ->toContain("wire:click=\"mountAction('downloadExpedienteDocument', { document: ".$document->getKey().' })"')
        ->toContain('Eliminar documento');
});
