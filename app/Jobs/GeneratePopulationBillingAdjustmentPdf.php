<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Collection;
use App\Models\CorporateBillingAdjustment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * PDF del aviso de cobro por ajuste de población, en cola. Idempotente: siempre escribe el mismo
 * archivo del ajuste y lo reemplaza si se reintenta.
 */
class GeneratePopulationBillingAdjustmentPdf implements ShouldQueue
{
    use Queueable;

    public const DISK = 'local';

    /**
     * Sube cuando cambia la plantilla: el nombre del archivo la incluye, así un PDF guardado con
     * la plantilla vieja no se vuelve a servir y se regenera solo al pedirlo.
     */
    public const TEMPLATE_VERSION = 2;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public int $adjustmentId) {}

    public static function dispatchFor(CorporateBillingAdjustment $adjustment): void
    {
        self::dispatch((int) $adjustment->getKey())->afterCommit();
    }

    public static function pathFor(CorporateBillingAdjustment $adjustment): string
    {
        return 'billing-adjustments/'.$adjustment->public_id.'-v'.self::TEMPLATE_VERSION.'.pdf';
    }

    public function handle(): void
    {
        $adjustment = CorporateBillingAdjustment::query()->find($this->adjustmentId);

        if ($adjustment === null) {
            return;
        }

        $path = self::pathFor($adjustment);
        $binary = self::render($adjustment);

        if (! Storage::disk(self::DISK)->put($path, $binary)) {
            throw new RuntimeException('No se pudo guardar el PDF del ajuste '.$adjustment->public_id.'.');
        }

        CorporateBillingAdjustment::query()->whereKey($adjustment->getKey())->update(['pdf_path' => $path]);
    }

    public static function render(CorporateBillingAdjustment $adjustment): string
    {
        $lines = $adjustment->lines()->orderBy('kind')->orderBy('full_name')->get();
        $charge = $adjustment->charge_collection_id !== null ? Collection::query()->find($adjustment->charge_collection_id) : null;

        return Pdf::loadView('documents.aviso-ajuste-poblacion', [
            'adjustment' => $adjustment,
            'summary' => (array) $adjustment->summary,
            'charge' => $charge,
            'lines' => $lines,
        ])->setPaper('a4')->output();
    }
}
