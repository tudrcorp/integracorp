<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\FarmadocOrder;
use App\Services\Farmadoc\FarmadocAllyClient;
use App\Support\Farmadoc\FarmadocException;
use App\Support\Farmadoc\FarmadocOrderTracker;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Actualiza desde Farmadoc el estatus de los pedidos pendientes o en proceso.
 * Farmadoc no tiene listado de pedidos: se consulta uno por uno, empezando por el
 * que lleva más tiempo sin revisarse.
 */
class RefreshFarmadocOrderStatuses implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const MAX_ORDERS_PER_RUN = 200;

    public const LOOKBACK_DAYS = 60;

    public int $tries = 1;

    public int $timeout = 600;

    public int $uniqueFor = 900;

    /**
     * @param  list<int>|null  $orderIds  Solo estos pedidos; null = todos los abiertos.
     */
    public function __construct(public ?array $orderIds = null) {}

    public function uniqueId(): string
    {
        return $this->orderIds === null ? 'all-open' : 'ids-'.md5(implode(',', $this->orderIds));
    }

    public function handle(FarmadocAllyClient $client): void
    {
        $orders = FarmadocOrder::query()
            ->open()
            ->when(
                $this->orderIds !== null,
                fn ($query) => $query->whereKey($this->orderIds),
                fn ($query) => $query->where('created_at', '>=', now()->subDays(self::LOOKBACK_DAYS)),
            )
            ->orderByRaw('status_checked_at IS NOT NULL')
            ->orderBy('status_checked_at')
            ->limit(self::MAX_ORDERS_PER_RUN)
            ->get();

        $failed = 0;

        foreach ($orders as $order) {
            try {
                FarmadocOrderTracker::refresh($order, $client);
            } catch (FarmadocException $exception) {
                $failed++;

                if ($this->isConfigurationProblem($exception)) {
                    Log::warning('Estatus de pedidos Farmadoc: integración no configurada', [
                        'message' => $exception->getMessage(),
                    ]);

                    return;
                }
            }
        }

        if ($failed > 0) {
            Log::info('Estatus de pedidos Farmadoc: algunos pedidos no se pudieron consultar', [
                'checked' => $orders->count(),
                'failed' => $failed,
            ]);
        }
    }

    private function isConfigurationProblem(FarmadocException $exception): bool
    {
        return in_array($exception->getMessage(), [
            'La integración con Farmadoc no está configurada.',
            'Farmadoc rechazó la credencial del aliado.',
            'Farmadoc no autorizó esta conexión.',
            'No se pudo contactar a Farmadoc. Verifique la conexión e intente de nuevo.',
        ], true);
    }
}
