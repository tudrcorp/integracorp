<?php

declare(strict_types=1);

use App\Filament\Business\Pages\ManageQuoteDocumentLayout;
use App\Models\User;
use App\Support\TuDrQuote\QuoteDocumentAssembler;
use App\Support\TuDrQuote\QuoteDocumentLayout;
use Filament\Facades\Filament;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    bootTuDrQuoteSqliteSchema();
    config(['cache.default' => 'array']);
    Filament::setCurrentPanel('business');
    QuoteDocumentLayout::flush();
});

afterEach(fn () => QuoteDocumentLayout::flush());

/**
 * @return array{superadmin: User, analista: User}
 */
function usuariosDelPanelDeNegocios(): array
{
    return [
        'superadmin' => makeTuDrQuoteUser('Super administrador', ['NEGOCIOS', 'SUPERADMIN']),
        'analista' => makeTuDrQuoteUser('Analista de negocios', ['NEGOCIOS']),
    ];
}

it('vive en el grupo CONFIGURACIÓN del panel de negocios', function (): void {
    expect(ManageQuoteDocumentLayout::getNavigationGroup())->toBe('CONFIGURACIÓN')
        ->and(ManageQuoteDocumentLayout::getNavigationLabel())->toBe('Formato de la cotización');
});

it('solo la abre un SUPERADMIN', function (): void {
    ['superadmin' => $superAdmin, 'analista' => $analista] = usuariosDelPanelDeNegocios();

    $this->actingAs($superAdmin);
    expect(ManageQuoteDocumentLayout::canAccess())->toBeTrue()
        ->and(ManageQuoteDocumentLayout::shouldRegisterNavigation())->toBeTrue();

    $this->actingAs($analista);
    expect(ManageQuoteDocumentLayout::canAccess())->toBeFalse()
        ->and(ManageQuoteDocumentLayout::shouldRegisterNavigation())->toBeFalse();
});

it('guarda el número de hojas y la página de cálculos de cada ámbito', function (): void {
    ['superadmin' => $superAdmin] = usuariosDelPanelDeNegocios();

    $this->actingAs($superAdmin);

    Livewire::test(ManageQuoteDocumentLayout::class)
        ->assertSuccessful()
        ->set('data.individual.total_pages', 6)
        ->set('data.individual.calculations_page', 4)
        ->set('data.corporate.total_pages', 5)
        ->set('data.corporate.calculations_page', 2)
        ->call('save')
        ->assertHasNoErrors();

    expect(QuoteDocumentLayout::for('individual'))->toBe(['total_pages' => 6, 'calculations_page' => 4])
        ->and(QuoteDocumentLayout::for('corporate'))->toBe(['total_pages' => 5, 'calculations_page' => 2]);
});

it('no deja la página de cálculos fuera del documento', function (): void {
    ['superadmin' => $superAdmin] = usuariosDelPanelDeNegocios();

    $this->actingAs($superAdmin);

    QuoteDocumentLayout::save('individual', 3, 9);

    expect(QuoteDocumentLayout::for('individual'))->toBe(['total_pages' => 3, 'calculations_page' => 3]);
});

it('deja intacto el documento del servicio con la configuración por defecto', function (): void {
    /** Documento tal como lo entrega el servicio para el Especial. */
    $paginas = ['portada', 'acerca', 'calculos', 'patologias', 'contraportada'];

    expect(QuoteDocumentAssembler::order($paginas, 1, QuoteDocumentLayout::for('individual')))
        ->toBe($paginas);
});

it('mueve los cálculos a la hoja configurada y recorta al total pedido', function (): void {
    $paginas = ['portada', 'acerca', 'calculos', 'patologias', 'contraportada'];

    expect(QuoteDocumentAssembler::order($paginas, 1, ['total_pages' => 7, 'calculations_page' => 1]))
        ->toBe(['calculos', 'portada', 'acerca', 'patologias', 'contraportada'])
        ->and(QuoteDocumentAssembler::order($paginas, 1, ['total_pages' => 3, 'calculations_page' => 3]))
        ->toBe(['portada', 'acerca', 'calculos']);
});

it('nunca recorta las páginas de cálculo aunque se pidan menos hojas', function (): void {
    $paginas = ['portada', 'acerca', 'inicial', 'ideal', 'especial', 'contraportada'];

    /**
     * Pedir dos hojas con los cálculos en la tercera es contradictorio: la
     * posición se acota al total, y las tres páginas de tarifas se conservan
     * aunque el documento acabe con más hojas de las pedidas.
     */
    expect(QuoteDocumentAssembler::order($paginas, 3, ['total_pages' => 2, 'calculations_page' => 3]))
        ->toBe(['portada', 'inicial', 'ideal', 'especial']);
});

it('devuelve el documento sin tocar si no trae páginas de cálculo', function (): void {
    $paginas = ['portada', 'acerca'];

    expect(QuoteDocumentAssembler::order($paginas, 0, ['total_pages' => 7, 'calculations_page' => 3]))
        ->toBe($paginas)
        ->and(QuoteDocumentAssembler::order($paginas, 2, ['total_pages' => 7, 'calculations_page' => 3]))
        ->toBe($paginas);
});
