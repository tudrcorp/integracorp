<?php

declare(strict_types=1);

use App\Enums\CollectionFollowUpStatus as Status;
use App\Filament\Administration\Resources\AnnualCollections\AnnualCollectionResource;
use App\Filament\Administration\Resources\AnnualCollections\Pages\ListAnnualCollections;
use App\Filament\Administration\Resources\AnnualCollections\Tables\AnnualCollectionsTable;
use App\Models\Collection;
use App\Models\CollectionFollowUpEvent;
use App\Models\User;
use App\Support\Collections\CollectionFollowUp;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Estados de gestión de «Cobranza Por Mes». Escribe dentro de una transacción que siempre se
 * revierte; usa una cuota POR PAGAR real que aparece en la tabla.
 */
beforeEach(function (): void {
    DB::beginTransaction();

    $this->actor = User::factory()->create([
        'email' => 'qa.estado.gestion.'.uniqid().'@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'ADMINISTRACION'],
    ]);
    $this->actingAs($this->actor);
    Filament::setCurrentPanel('administration');

    $this->collection = AnnualCollectionResource::getEloquentQuery()->first();

    if ($this->collection === null) {
        $this->markTestSkipped('No hay cuotas por cobrar en esta base.');
    }

    $this->id = (int) $this->collection->getKey();
    Collection::query()->whereKey($this->id)->update(['follow_up_status' => null]);
});

afterEach(function (): void {
    DB::rollBack();
});

function myFollowUpEvents(): array
{
    return CollectionFollowUpEvent::query()
        ->where('collection_id', test()->id)
        ->where('user_id', test()->actor->getKey())
        ->orderBy('id')
        ->get()
        ->map(fn (CollectionFollowUpEvent $event): string => ($event->previous_status ?? '—').' → '.($event->new_status ?? '—'))
        ->all();
}

it('ofrece tres estados: enviado y remitido pueden ir juntos, en revisión va solo', function (): void {
    expect(Status::options())->toBe([
        'AVISO ENVIADO' => 'Aviso enviado',
        'AVISO REMITIDO' => 'Aviso remitido',
        'AVISO EN REVISION' => 'Aviso en revisión',
    ])
        ->and(Status::encode(Status::toggle([], Status::AvisoRemitido)))->toBe('AVISO REMITIDO')
        ->and(Status::encode(Status::toggle([Status::AvisoRemitido], Status::AvisoEnviado)))->toBe('AVISO ENVIADO,AVISO REMITIDO')
        ->and(Status::encode(Status::toggle([Status::AvisoEnviado, Status::AvisoRemitido], Status::AvisoEnRevision)))->toBe('AVISO EN REVISION')
        ->and(Status::encode(Status::toggle([Status::AvisoEnRevision], Status::AvisoEnviado)))->toBe('AVISO ENVIADO')
        ->and(Status::encode(Status::toggle([Status::AvisoEnviado, Status::AvisoRemitido], Status::AvisoEnviado)))->toBe('AVISO REMITIDO')
        ->and(Status::isValidCombination('AVISO ENVIADO,AVISO EN REVISION'))->toBeFalse()
        ->and(Status::isValidCombination('AVISO REMITIDO,AVISO ENVIADO'))->toBeFalse()
        ->and(Status::isValidCombination('AVISO ENVIADO,AVISO REMITIDO'))->toBeTrue()
        ->and(Status::labelFor('AVISO ENVIADO,AVISO REMITIDO'))->toBe('Aviso enviado + Aviso remitido')
        ->and(Status::storedValuesContaining(Status::AvisoEnviado))->toBe(['AVISO ENVIADO', 'AVISO ENVIADO,AVISO REMITIDO'])
        ->and(Status::storedValuesContaining(Status::AvisoEnRevision))->toBe(['AVISO EN REVISION']);
});

it('marca enviado y remitido a la vez; en revisión apaga a los dos y al revés, con traza de cada clic', function (): void {
    expect(CollectionFollowUp::toggle($this->id, 'AVISO ENVIADO', null, $this->actor))->toBe(['status' => 'AVISO ENVIADO', 'changed' => true, 'conflict' => false])
        ->and(CollectionFollowUp::toggle($this->id, 'AVISO REMITIDO', 'AVISO ENVIADO', $this->actor)['status'])->toBe('AVISO ENVIADO,AVISO REMITIDO')
        ->and(CollectionFollowUp::toggle($this->id, 'AVISO EN REVISION', 'AVISO ENVIADO,AVISO REMITIDO', $this->actor)['status'])->toBe('AVISO EN REVISION')
        ->and(CollectionFollowUp::toggle($this->id, 'AVISO REMITIDO', 'AVISO EN REVISION', $this->actor)['status'])->toBe('AVISO REMITIDO')
        ->and(CollectionFollowUp::toggle($this->id, 'AVISO REMITIDO', 'AVISO REMITIDO', $this->actor)['status'])->toBeNull()
        ->and(Collection::query()->whereKey($this->id)->value('follow_up_status'))->toBeNull()
        ->and(myFollowUpEvents())->toBe([
            '— → AVISO ENVIADO',
            'AVISO ENVIADO → AVISO ENVIADO,AVISO REMITIDO',
            'AVISO ENVIADO,AVISO REMITIDO → AVISO EN REVISION',
            'AVISO EN REVISION → AVISO REMITIDO',
            'AVISO REMITIDO → —',
        ]);
});

it('no pisa el cambio de otra persona: devuelve lo vigente sin escribir', function (): void {
    CollectionFollowUp::toggle($this->id, 'AVISO REMITIDO', null, $this->actor);

    expect(CollectionFollowUp::toggle($this->id, 'AVISO ENVIADO', null, $this->actor))->toBe(['status' => 'AVISO REMITIDO', 'changed' => false, 'conflict' => true])
        ->and(myFollowUpEvents())->toHaveCount(1);
});

it('una cuota que quedó en SEGUIMIENTO se puede reemplazar o limpiar, y SEGUIMIENTO no se vuelve a marcar', function (): void {
    Collection::query()->whereKey($this->id)->update(['follow_up_status' => 'SEGUIMIENTO']);

    expect(CollectionFollowUp::label('SEGUIMIENTO'))->toBe('Seguimiento (ya no se usa)')
        ->and(fn () => CollectionFollowUp::toggle($this->id, 'SEGUIMIENTO', 'SEGUIMIENTO', $this->actor))->toThrow(InvalidArgumentException::class, 'Estado de gestión no válido')
        ->and(CollectionFollowUp::toggle($this->id, 'AVISO ENVIADO', 'SEGUIMIENTO', $this->actor)['status'])->toBe('AVISO ENVIADO');

    Collection::query()->whereKey($this->id)->update(['follow_up_status' => 'SEGUIMIENTO']);

    expect(CollectionFollowUp::clear($this->id, 'SEGUIMIENTO', $this->actor))->toBe(['status' => null, 'changed' => true, 'conflict' => false])
        ->and(myFollowUpEvents())->toBe(['SEGUIMIENTO → AVISO ENVIADO', 'SEGUIMIENTO → —']);
});

it('rechaza cuotas ya pagadas y analistas sin el permiso', function (): void {
    Collection::query()->whereKey($this->id)->update(['status' => 'PAGADO']);

    expect(fn () => CollectionFollowUp::toggle($this->id, 'AVISO ENVIADO', null, $this->actor))->toThrow(InvalidArgumentException::class, 'ya no está por pagar');

    $analyst = User::factory()->create(['email' => 'qa.estado.sinpermiso.'.uniqid().'@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['ADMINISTRACION']]);
    $this->actingAs($analyst);

    expect(fn () => CollectionFollowUp::toggle($this->id, 'AVISO ENVIADO', null, $analyst))->toThrow(InvalidArgumentException::class, 'No tiene permiso');
});

it('la tabla muestra los tres estados, guarda desde la página sin redibujar y el filtro encuentra las combinaciones', function (): void {
    $page = Livewire::test(ListAnnualCollections::class)
        ->assertSuccessful()
        ->assertSee('Estado de gestión')
        ->assertSee('Aviso en revisión')
        ->assertSee('setCollectionFollowUp', false)
        ->assertDontSee('>Seguimiento', false);

    expect($page->instance()->setCollectionFollowUp($this->id, 'AVISO ENVIADO', null))->toBe(['ok' => true, 'status' => 'AVISO ENVIADO', 'message' => null])
        ->and($page->instance()->setCollectionFollowUp($this->id, 'AVISO REMITIDO', 'AVISO ENVIADO')['status'])->toBe('AVISO ENVIADO,AVISO REMITIDO')
        ->and($page->instance()->setCollectionFollowUp($this->id, 'AVISO EN REVISION', null))->toMatchArray(['ok' => false, 'status' => 'AVISO ENVIADO,AVISO REMITIDO'])
        ->and($page->instance()->setCollectionFollowUp($this->id, 'INVENTADO', 'AVISO ENVIADO,AVISO REMITIDO')['message'])->toBe('Estado de gestión no válido.');

    $visible = fn (string $value): bool => Livewire::test(ListAnnualCollections::class)
        ->filterTable('follow_up_status', $value)
        ->instance()
        ->getFilteredTableQuery()
        ->whereKey($this->id)
        ->exists();

    expect($visible('AVISO ENVIADO'))->toBeTrue()
        ->and($visible('AVISO REMITIDO'))->toBeTrue()
        ->and($visible('AVISO EN REVISION'))->toBeFalse()
        ->and($visible(AnnualCollectionsTable::WITHOUT_FOLLOW_UP))->toBeFalse();
});

it('el panel de observaciones muestra el historial de los estados de la cuota', function (): void {
    CollectionFollowUp::toggle($this->id, 'AVISO ENVIADO', null, $this->actor);

    expect((string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AnnualCollections/Actions/CollectionObservationsAction.php'))
        ->toContain("Placeholder::make('follow_up_log')")
        ->and(CollectionFollowUp::history($this->id)->first()?->new_status)->toBe('AVISO ENVIADO');
});
