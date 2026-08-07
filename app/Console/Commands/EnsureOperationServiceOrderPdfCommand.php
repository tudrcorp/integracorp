<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OperationServiceOrder;
use App\Services\OperationServiceOrderPdfService;
use Illuminate\Console\Command;

class EnsureOperationServiceOrderPdfCommand extends Command
{
    protected $signature = 'operation-service-orders:ensure-pdf
                            {order? : ID o número de orden (ej. 52 o ORD-0052)}
                            {--missing : Solo órdenes sin service_order_pdf_path}
                            {--force : Regenera aunque ya exista el PDF}';

    protected $description = 'Genera y persiste el PDF de orden(es) de servicio de Operaciones';

    public function handle(): int
    {
        $orderArg = $this->argument('order');
        $onlyMissing = (bool) $this->option('missing');
        $force = (bool) $this->option('force');

        $query = OperationServiceOrder::query()->orderBy('id');

        if (is_string($orderArg) && $orderArg !== '') {
            if (ctype_digit($orderArg)) {
                $query->whereKey((int) $orderArg);
            } else {
                $query->where('order_number', $orderArg);
            }
        } elseif ($onlyMissing) {
            $query->where(function ($builder): void {
                $builder->whereNull('service_order_pdf_path')
                    ->orWhere('service_order_pdf_path', '');
            });
        } else {
            $this->components->error('Indica {order} o usa --missing.');

            return self::FAILURE;
        }

        $orders = $query->get();
        if ($orders->isEmpty()) {
            $this->components->warn('No se encontraron órdenes.');

            return self::SUCCESS;
        }

        $ok = 0;
        foreach ($orders as $order) {
            try {
                $path = OperationServiceOrderPdfService::ensurePersisted($order, $force);
                $this->components->info("{$order->order_number} → {$path}");
                $ok++;
            } catch (\Throwable $exception) {
                $this->components->error("{$order->order_number}: {$exception->getMessage()}");
            }
        }

        $this->components->info("PDFs listos: {$ok}/{$orders->count()}");

        return $ok === $orders->count() ? self::SUCCESS : self::FAILURE;
    }
}
