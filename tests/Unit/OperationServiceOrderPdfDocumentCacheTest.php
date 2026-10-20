<?php

declare(strict_types=1);

use App\Models\OperationCoordinationService;
use App\Models\OperationServiceOrder;
use App\Services\OperationServiceOrderPdfService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    Storage::fake('public');
});

afterEach(fn () => DB::rollBack());

function orderForPdfCache(): OperationServiceOrder
{
    $coordinationId = OperationCoordinationService::query()->value('id');

    if ($coordinationId === null) {
        test()->markTestSkipped('No hay coordinaciones para colgar la orden de prueba.');
    }

    return OperationServiceOrder::query()->create([
        'operation_coordination_service_id' => $coordinationId,
        'order_number' => 'ORD-PDF-CACHE-TEST',
        'service_type' => 'LABORATORIOS',
        'description' => 'Orden de prueba de caché del PDF',
        'status' => 'PENDIENTE',
        'created_by' => 'TEST',
    ]);
}

it('genera una vez, guarda la ruta con la huella y reutiliza la copia mientras la orden no cambie', function (): void {
    $order = orderForPdfCache();
    $updatedAt = $order->fresh()->updated_at;

    $first = OperationServiceOrderPdfService::document($order);

    expect($first['path'])->toEndWith('-v'.$first['fingerprint'].'.pdf')
        ->and($first['contents'])->toStartWith('%PDF')
        ->and(Storage::disk('public')->exists($first['path']))->toBeTrue();

    $reloaded = $order->fresh();

    expect($reloaded->service_order_pdf_path)->toBe($first['path'])
        ->and($reloaded->updated_at->equalTo($updatedAt))->toBeTrue();

    $second = OperationServiceOrderPdfService::document($reloaded);

    expect($second['path'])->toBe($first['path'])
        ->and($second['contents'])->toBe($first['contents'])
        ->and(Storage::disk('public')->allFiles())->toHaveCount(1);
});

it('regenera cuando cambia algo que el documento muestra', function (): void {
    $order = orderForPdfCache();
    $first = OperationServiceOrderPdfService::document($order);

    $order->refresh();
    $order->description = 'Descripción corregida por el analista';
    $order->saveQuietly();

    $second = OperationServiceOrderPdfService::document($order->fresh());

    expect($second['fingerprint'])->not->toBe($first['fingerprint'])
        ->and($second['path'])->not->toBe($first['path'])
        ->and($order->fresh()->service_order_pdf_path)->toBe($second['path']);
});

it('regenera copias antiguas sin huella, archivos perdidos o vacíos y cuando se fuerza', function (): void {
    $order = orderForPdfCache();
    $disk = Storage::disk('public');

    $disk->put('operation-service-orders/generated-pdf/service-order-viejo-20261001000000.pdf', str_repeat('x', 500));
    $order->forceFill(['service_order_pdf_path' => 'operation-service-orders/generated-pdf/service-order-viejo-20261001000000.pdf'])->saveQuietly();

    $fromLegacy = OperationServiceOrderPdfService::document($order->fresh());
    expect($fromLegacy['path'])->toEndWith('-v'.$fromLegacy['fingerprint'].'.pdf');

    $disk->put($fromLegacy['path'], '');
    $fromEmpty = OperationServiceOrderPdfService::document($order->fresh());
    expect($fromEmpty['contents'])->toStartWith('%PDF');

    $disk->delete($fromEmpty['path']);
    $fromMissing = OperationServiceOrderPdfService::document($order->fresh());
    expect($disk->exists($fromMissing['path']))->toBeTrue();

    $this->travel(1)->seconds();
    $forced = OperationServiceOrderPdfService::document($order->fresh(), force: true);
    expect($forced['path'])->not->toBe($fromMissing['path'])
        ->and($forced['fingerprint'])->toBe($fromMissing['fingerprint']);
});

it('la huella es estable para el mismo HTML y depende de la versión de render', function (): void {
    $fingerprint = OperationServiceOrderPdfService::fingerprint('<p>orden</p>');

    expect(OperationServiceOrderPdfService::fingerprint('<p>orden</p>'))->toBe($fingerprint)
        ->and(OperationServiceOrderPdfService::fingerprint('<p>orden 2</p>'))->not->toBe($fingerprint)
        ->and($fingerprint)->toBe(substr(sha1(OperationServiceOrderPdfService::RENDER_VERSION.'|<p>orden</p>'), 0, 12))
        ->and(OperationServiceOrderPdfService::isCurrent('a/service-order-1-20261020-v'.$fingerprint.'.pdf', $fingerprint))->toBeTrue()
        ->and(OperationServiceOrderPdfService::isCurrent('a/service-order-1-20261020.pdf', $fingerprint))->toBeFalse()
        ->and(OperationServiceOrderPdfService::isCurrent('', $fingerprint))->toBeFalse();
});

it('incrusta solo los glifos usados y el logo reducido', function (): void {
    $order = orderForPdfCache();

    $document = OperationServiceOrderPdfService::document($order);

    expect(strlen($document['contents']))->toBeLessThan(400 * 1024)
        ->and(is_file(public_path('image/logoNewPdf-800.png')))->toBeTrue();
});

it('vista previa y descarga entregan la copia vigente con un solo render y ETag', function (): void {
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/OperationServiceOrderPdfController.php');
    $mail = file_get_contents(dirname(__DIR__, 2).'/app/Mail/OperationServiceOrderPdfMail.php');

    expect($controller)
        ->toContain('OperationServiceOrderPdfService::document($order)')
        ->toContain('HTTP_NOT_MODIFIED')
        ->toContain("'ETag' => \$etag")
        ->not->toContain('ensurePersisted')
        ->not->toContain('OperationServiceOrderPdfService::make(');

    expect(substr_count($controller, '$this->authorizeOrder('))->toBe(2);

    expect($mail)->toContain("OperationServiceOrderPdfService::document(\$this->order)['contents']");
});

it('la modal de vista previa muestra un indicador mientras se genera el PDF', function (): void {
    $html = view('filament.operations.operation-service-orders.pdf-preview', [
        'pdfPreviewUrl' => 'https://example.test/orden.pdf',
        'pdfDownloadUrl' => 'https://example.test/orden.pdf?d=1',
        'documentLabel' => 'Orden de servicio',
        'documentTitle' => 'ORD-0001',
    ])->render();

    expect($html)
        ->toContain('data-pdf-preview-loading')
        ->toContain('Generando orden de servicio…')
        ->toContain('x-on:load="loading = false"')
        ->toContain('Está tardando más de lo normal')
        ->toContain('animate-spin')
        ->not->toContain('loading="lazy"');
});
