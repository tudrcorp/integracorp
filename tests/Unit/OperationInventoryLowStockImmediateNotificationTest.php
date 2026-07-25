<?php

declare(strict_types=1);

use App\Jobs\NotifyOperationInventoryProductLowStockJob;
use App\Mail\OperationInventoryLowStockMail;
use App\Services\OperationInventoryLowStockReporter;
use App\Services\OperationInventoryLowStockWatcher;
use App\Services\OperationInventoryProductStockAdjuster;
use App\Services\TelemedicineMedicationInventoryDeductor;
use Illuminate\Support\Facades\Queue;

uses(Tests\TestCase::class);

it('despacha el job inmediato solo al cruzar el umbral hacia abajo', function (): void {
    Queue::fake();

    $watcher = new class extends OperationInventoryLowStockWatcher
    {
        public int $threshold = 3;

        public int $currentTotal = 3;

        public bool $activeProduct = true;

        public function dispatchIfCrossedThreshold(int $productId, ?int $previousTotalExistence = null): bool
        {
            if (! $this->activeProduct || $productId < 1) {
                return false;
            }

            if ($this->currentTotal > $this->threshold) {
                return false;
            }

            if ($previousTotalExistence !== null && $previousTotalExistence <= $this->threshold) {
                return false;
            }

            NotifyOperationInventoryProductLowStockJob::dispatch($productId);

            return true;
        }
    };

    expect($watcher->dispatchIfCrossedThreshold(10, 5))->toBeTrue();
    Queue::assertPushed(NotifyOperationInventoryProductLowStockJob::class, fn ($job) => $job->productId === 10);

    Queue::fake();
    expect($watcher->dispatchIfCrossedThreshold(10, 2))->toBeFalse();
    Queue::assertNothingPushed();

    $watcher->currentTotal = 4;
    expect($watcher->dispatchIfCrossedThreshold(10, 8))->toBeFalse();
});

it('arma cuerpo whatsapp inmediato para un producto', function (): void {
    $report = [
        'threshold' => 3,
        'generated_at' => '22/07/2026 12:00',
        'products' => [
            [
                'id' => 1,
                'code' => 'PROD-100',
                'name' => 'SUERO',
                'category' => 'Medicamentos',
                'unit' => 'UNIDAD',
                'total_existence' => 2,
                'warehouses' => [
                    ['name' => 'DIAGNOMOVIL', 'existence' => 2],
                ],
            ],
        ],
    ];

    $body = (new OperationInventoryLowStockReporter)->whatsappBodyImmediate($report);

    expect($body)
        ->toContain('Alerta inmediata de stock bajo')
        ->toContain('*PROD-100* · SUERO')
        ->toContain('Total: 2')
        ->toContain('· DIAGNOMOVIL: 2');
});

it('el mailable distingue alerta inmediata', function (): void {
    $mail = new OperationInventoryLowStockMail([
        'threshold' => 3,
        'generated_at' => '22/07/2026 12:00',
        'products' => [],
    ], 'inventario@example.com', immediate: true);

    expect($mail->envelope()->subject)->toContain('Alerta inmediata de stock bajo');
});

it('engancha la alerta inmediata en adjuster, carga de existencia y telemedicina', function (): void {
    $adjuster = file_get_contents(dirname(__DIR__, 2).'/app/Services/OperationInventoryProductStockAdjuster.php');
    $load = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryProducts/Actions/LoadProductExistenceAction.php');
    $deductor = file_get_contents(dirname(__DIR__, 2).'/app/Services/TelemedicineMedicationInventoryDeductor.php');
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/NotifyOperationInventoryProductLowStockJob.php');

    expect($adjuster)
        ->toContain('OperationInventoryLowStockWatcher')
        ->toContain('dispatchIfCrossedThreshold')
        ->toContain('$previousTotal = $increase ? null : $product->totalExistence()');

    expect($load)
        ->toContain('OperationInventoryLowStockWatcher')
        ->toContain('$previousTotal = $record->totalExistence()')
        ->toContain('dispatchIfCrossedThreshold');

    expect($deductor)
        ->toContain('OperationInventoryLowStockWatcher')
        ->toContain('dispatchIfCrossedThreshold');

    expect($job)
        ->toContain('SystemNotificationKey::OperationInventoryLowStock')
        ->toContain('SendNotificacionWhatsApp::dispatch')
        ->toContain('OperationInventoryLowStockMail')
        ->toContain('immediate: true')
        ->toContain('ShouldQueue');

    expect(class_exists(NotifyOperationInventoryProductLowStockJob::class))->toBeTrue()
        ->and(class_exists(OperationInventoryLowStockWatcher::class))->toBeTrue()
        ->and(class_exists(OperationInventoryProductStockAdjuster::class))->toBeTrue()
        ->and(class_exists(TelemedicineMedicationInventoryDeductor::class))->toBeTrue();
});
