<?php

declare(strict_types=1);

use App\Models\OperationServiceOrder;
use App\Services\OperationServiceOrderPdfService;
use App\Support\Operations\OperationServiceOrderInternalPricingNote as Note;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

/**
 * Escribe en base de datos: todo dentro de una transacción que siempre se revierte.
 */
beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

const FRASE_INTERNA_QA = 'Generada automáticamente desde cotización #56 (costo base US$ 15.00, ganancia 20.00%).';

it('quita la frase interna y conserva lo que escribió el analista', function (?string $input, ?string $expected): void {
    expect(Note::strip($input))->toBe($expected);
})->with([
    'solo la frase' => [FRASE_INTERNA_QA, null],
    'frase y texto del analista' => [FRASE_INTERNA_QA."\nEntregar en recepción.", 'Entregar en recepción.'],
    'texto antes de la frase' => ['Paciente en silla de ruedas. '.FRASE_INTERNA_QA, 'Paciente en silla de ruedas.'],
    'sin acentos' => ['Generada automaticamente desde cotizacion #7 (costo base US$ 1,00, ganancia 5%)', null],
    'sin la frase no cambia' => ['Llamar antes de llegar · piso 3', 'Llamar antes de llegar · piso 3'],
    'acentos al final se respetan' => ['Revisión médica', 'Revisión médica'],
    'nulo' => [null, null],
]);

it('detecta solo la frase interna', function (): void {
    expect(Note::contains(FRASE_INTERNA_QA))->toBeTrue()
        ->and(Note::contains('Costo base del traslado a cargo del paciente'))->toBeFalse()
        ->and(Note::contains(null))->toBeFalse();
});

it('la orden creada desde una cotización ya no recibe costo base ni ganancia en sus observaciones', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceQuoteManager.php');

    expect($source)->not->toContain('costo base %s')
        ->and($source)->not->toContain('ganancia %s')
        ->and($source)->not->toContain("'service_order_observations'] = sprintf")
        ->and($source)->not->toContain("\$set('service_order_observations', sprintf");
});

it('el PDF de la orden nunca muestra costo base ni ganancia, pero sí la nota del analista', function (): void {
    $order = OperationServiceOrder::query()->latest('id')->first();

    if ($order === null) {
        test()->markTestSkipped('No hay órdenes de servicio en la base para renderizar el PDF.');
    }

    $order->forceFill(['observations' => FRASE_INTERNA_QA."\nEntregar en recepción QA."])->saveQuietly();
    $html = OperationServiceOrderPdfService::html($order->fresh());

    expect($html)->toContain('Entregar en recepción QA.')
        ->and($html)->not->toContain('costo base')
        ->and($html)->not->toContain('ganancia 20.00%');

    $order->forceFill(['observations' => FRASE_INTERNA_QA])->saveQuietly();
    $onlyNote = OperationServiceOrderPdfService::html($order->fresh());

    expect($onlyNote)->not->toContain('costo base')
        ->and($onlyNote)->not->toContain('Observaciones');
});

it('el comando simula, limpia con respaldo y traza, y restaura', function (): void {
    Storage::fake('local');

    $order = OperationServiceOrder::query()->latest('id')->first();

    if ($order === null) {
        test()->markTestSkipped('No hay órdenes de servicio en la base.');
    }

    $order->forceFill(['observations' => FRASE_INTERNA_QA."\nNota QA."])->saveQuietly();

    test()->artisan('operations:clean-service-order-pricing-notes')
        ->expectsOutputToContain('Simulación')
        ->assertSuccessful();
    expect($order->fresh()->observations)->toBe(FRASE_INTERNA_QA."\nNota QA.");

    test()->artisan('operations:clean-service-order-pricing-notes', ['--apply' => true])
        ->expectsOutputToContain('--actor')
        ->assertFailed();

    test()->artisan('operations:clean-service-order-pricing-notes', ['--apply' => true, '--actor' => 'QA'])
        ->assertSuccessful();

    expect($order->fresh()->observations)->toBe('Nota QA.')
        ->and(DB::table('logs')->where('action', 'AUDIT_OPERATIONS_SERVICE_ORDER_PRICING_NOTE_REMOVED')->where('response', 'like', '%"service_order_id":'.$order->id.',%')->exists())->toBeTrue();

    $files = Storage::disk('local')->files('corrections/service-order-pricing-notes');
    expect($files)->toHaveCount(1);

    test()->artisan('operations:clean-service-order-pricing-notes', ['--restore' => $files[0], '--apply' => true, '--actor' => 'QA'])
        ->assertSuccessful();

    expect($order->fresh()->observations)->toBe(FRASE_INTERNA_QA."\nNota QA.");
});
