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
    Storage::fake('public');

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

it('con --order limpia solo las órdenes indicadas por número', function (string $format): void {
    Storage::fake('local');
    Storage::fake('public');

    $orders = OperationServiceOrder::query()->where('order_number', 'like', 'ORD-%')->latest('id')->take(2)->get();

    if ($orders->count() < 2) {
        test()->markTestSkipped('Hacen falta dos órdenes de servicio en la base.');
    }

    [$target, $other] = [$orders[0], $orders[1]];
    $target->forceFill(['observations' => FRASE_INTERNA_QA])->saveQuietly();
    $other->forceFill(['observations' => FRASE_INTERNA_QA."\nOtra QA."])->saveQuietly();

    $digits = preg_replace('/\D/', '', (string) $target->order_number);
    $value = match ($format) {
        'completo' => (string) $target->order_number,
        'minúsculas' => mb_strtolower((string) $target->order_number),
        'solo dígitos' => $digits,
        'sin ceros' => ltrim($digits, '0'),
    };

    test()->artisan('operations:clean-service-order-pricing-notes', ['--order' => [$value.',ORD-99999999'], '--apply' => true, '--actor' => 'QA'])
        ->expectsOutputToContain('ORD-99999999: no existe esa orden.')
        ->expectsOutputToContain('Listo: 1 de 1')
        ->assertSuccessful();

    expect($target->fresh()->observations)->toBeNull()
        ->and($other->fresh()->observations)->toBe(FRASE_INTERNA_QA."\nOtra QA.");

    test()->artisan('operations:clean-service-order-pricing-notes', ['--order' => [(string) $target->order_number]])
        ->expectsOutputToContain('no tiene costo, ganancia ni total de venta')
        ->expectsOutputToContain('Ninguna de las órdenes indicadas')
        ->assertSuccessful();
})->with(['completo', 'minúsculas', 'solo dígitos', 'sin ceros']);

it('quita el total de venta de la descripción automática y deja las demás igual', function (?string $input, ?string $expected): void {
    expect(Note::stripDescriptionTotal($input))->toBe($expected)
        ->and(Note::descriptionContainsTotal($input))->toBe($input !== $expected);
})->with([
    'en dólares' => ['Orden por cotización #57 · MEDICAMENTOS · 4 ítem(s) · Total US$ 62.54', 'Orden por cotización #57 · MEDICAMENTOS · 4 ítem(s)'],
    'con miles' => ['Orden por cotización #9 · IMAGENOLOGIA · 2 ítem(s) · Total US$ 1,250.00', 'Orden por cotización #9 · IMAGENOLOGIA · 2 ítem(s)'],
    'en bolívares' => ['Orden por cotización #9 · LABORATORIOS · 1 ítem(s) · Total Bs. 17,985.40', 'Orden por cotización #9 · LABORATORIOS · 1 ítem(s)'],
    'ya limpia' => ['Orden por cotización #57 · MEDICAMENTOS · 4 ítem(s)', 'Orden por cotización #57 · MEDICAMENTOS · 4 ítem(s)'],
    'escrita por el analista' => ['Total US$ 20 a cobrar en sitio', 'Total US$ 20 a cobrar en sitio'],
    'gestión de servicio' => ['Orden por gestión de servicio · LABORATORIOS · 2 ítem(s)', 'Orden por gestión de servicio · LABORATORIOS · 2 ítem(s)'],
    'nula' => [null, null],
]);

it('la descripción de una orden nueva desde cotización no lleva el total', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceQuoteManager.php');

    expect($source)->not->toContain("ítem(s) · Total %s'");
});

it('el PDF no muestra el total de venta de la descripción', function (): void {
    $order = OperationServiceOrder::query()->latest('id')->first();

    if ($order === null) {
        test()->markTestSkipped('No hay órdenes de servicio en la base para renderizar el PDF.');
    }

    $order->forceFill(['description' => 'Orden por cotización #57 · MEDICAMENTOS · 4 ítem(s) · Total US$ 62.54'])->saveQuietly();
    $html = OperationServiceOrderPdfService::html($order->fresh());

    expect($html)->toContain('Orden por cotización #57 · MEDICAMENTOS · 4 ítem(s)')
        ->and($html)->not->toContain('62.54');
});

it('el comando limpia la descripción, regenera el PDF, retira las copias viejas y restaura', function (): void {
    Storage::fake('local');
    Storage::fake('public');

    $order = OperationServiceOrder::query()->where('order_number', 'like', 'ORD-%')->latest('id')->first();

    if ($order === null) {
        test()->markTestSkipped('No hay órdenes de servicio en la base.');
    }

    $description = 'Orden por cotización #57 · MEDICAMENTOS · 4 ítem(s) · Total US$ 62.54';
    $order->forceFill(['description' => $description, 'observations' => FRASE_INTERNA_QA])->saveQuietly();

    $safe = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string) $order->order_number);
    $oldCopy = 'operation-service-orders/generated-pdf/service-order-'.$safe.'-20261101000000-vabc123abc123.pdf';
    $foreignCopy = 'operation-service-orders/generated-pdf/service-order-ORD-99999999-20261101000000-vabc123abc123.pdf';
    Storage::disk('public')->put($oldCopy, 'PDF viejo con total');
    Storage::disk('public')->put($foreignCopy, 'PDF de otra orden');

    test()->artisan('operations:clean-service-order-pricing-notes', ['--order' => [(string) $order->order_number], '--apply' => true, '--actor' => 'QA'])
        ->expectsOutputToContain('Listo: 1 de 1')
        ->expectsOutputToContain('Copias viejas retiradas del disco público: 1')
        ->assertSuccessful();

    $fresh = $order->fresh();
    expect($fresh->description)->toBe('Orden por cotización #57 · MEDICAMENTOS · 4 ítem(s)')
        ->and($fresh->observations)->toBeNull()
        ->and(Storage::disk('public')->exists($oldCopy))->toBeFalse()
        ->and(Storage::disk('public')->exists($foreignCopy))->toBeTrue()
        ->and(Storage::disk('public')->exists((string) $fresh->service_order_pdf_path))->toBeTrue()
        ->and(OperationServiceOrderPdfService::isCurrent((string) $fresh->service_order_pdf_path, OperationServiceOrderPdfService::fingerprint(OperationServiceOrderPdfService::html($fresh))))->toBeTrue();

    $archived = collect(Storage::disk('local')->allFiles('corrections/service-order-pricing-notes'))->first(fn (string $file): bool => str_ends_with($file, basename($oldCopy)));
    expect($archived)->not->toBeNull()
        ->and(Storage::disk('local')->get($archived))->toBe('PDF viejo con total');

    $backup = collect(Storage::disk('local')->files('corrections/service-order-pricing-notes'))->first(fn (string $file): bool => str_ends_with($file, '.json'));

    test()->artisan('operations:clean-service-order-pricing-notes', ['--restore' => $backup, '--apply' => true, '--actor' => 'QA'])
        ->expectsOutputToContain('Restauradas 1 de 1')
        ->assertSuccessful();

    expect($order->fresh()->description)->toBe($description)
        ->and($order->fresh()->observations)->toBe(FRASE_INTERNA_QA);
});
