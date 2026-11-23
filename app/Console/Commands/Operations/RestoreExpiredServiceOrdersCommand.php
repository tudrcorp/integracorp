<?php

declare(strict_types=1);

namespace App\Console\Commands\Operations;

use App\Models\OperationServiceOrder;
use App\Models\User;
use App\Support\Operations\ExpiredServiceOrderRestorer;
use App\Support\Operations\OperationServiceOrderValidity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Command\Command as CommandAlias;
use Throwable;

/**
 * Devuelve las órdenes de servicio CADUCADAS a su estado anterior y regenera su
 * PDF sin la franja de caducada.
 *
 * Sin --apply solo simula. Se niega a aplicar si la caducidad vigente volvería a
 * caducar las órdenes restauradas (apáguela antes en Operaciones → Parámetros de
 * órdenes), salvo con --force. Es idempotente: solo toca órdenes que sigan
 * CADUCADAS, así que se puede repetir o retomar. Deja un CSV con cada cambio.
 */
class RestoreExpiredServiceOrdersCommand extends Command
{
    protected $signature = 'operation-service-orders:restore-expired
                            {--apply : Restaura las órdenes; sin esta opción solo simula}
                            {--order=* : ID o número de orden (repetible). Sin esto, todas las CADUCADAS}
                            {--without-pdf : No regenera el PDF (se puede hacer luego con operation-service-orders:ensure-pdf)}
                            {--user= : ID del usuario que figura como responsable en la orden y en la traza}
                            {--force : Restaura aunque la caducidad vigente las vuelva a caducar}';

    protected $description = 'Devuelve las órdenes de servicio CADUCADAS a su estado anterior y regenera su PDF sin la marca de caducada.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $withPdf = ! (bool) $this->option('without-pdf');
        $orderFilter = array_values(array_filter((array) $this->option('order'), 'filled'));

        $actor = $this->resolveActor();

        if ($actor === false) {
            return CommandAlias::FAILURE;
        }

        OperationServiceOrderValidity::flushSettings();

        $orders = ExpiredServiceOrderRestorer::candidatesQuery($orderFilter)->orderBy('id')->get();

        if ($orders->isEmpty()) {
            $this->components->info('No hay órdenes CADUCADAS que restaurar.');

            return CommandAlias::SUCCESS;
        }

        $previous = ExpiredServiceOrderRestorer::previousStatuses($orders->modelKeys());
        $summary = ExpiredServiceOrderRestorer::summarize($orders, $previous);

        $this->renderSummary($summary, $apply, $withPdf);

        if ($summary['would_expire_again'] > 0) {
            $rule = 'La caducidad está activada con '.OperationServiceOrderValidity::validityDays().' día(s): '
                .$summary['would_expire_again'].' de estas órdenes volverían a quedar CADUCADAS en el siguiente barrido.';

            if (! (bool) $this->option('force')) {
                $this->components->error($rule);
                $this->line('  Apague la caducidad en Operaciones → Parámetros de órdenes (o suba los días) y vuelva a ejecutar.');
                $this->line('  Si de verdad quiere restaurarlas igual, agregue --force.');

                return CommandAlias::FAILURE;
            }

            $this->components->warn($rule.' Se continúa por --force.');
        }

        if (! $apply) {
            $this->newLine();
            $this->components->info('Simulación: no se cambió nada. Para aplicar, agregue --apply.');

            return CommandAlias::SUCCESS;
        }

        $updatedBy = $actor instanceof User && filled($actor->name)
            ? (string) $actor->name
            : 'Restauración de órdenes caducadas';

        $csvPath = 'operation-service-orders/restore-expired-'.now()->format('Ymd-His').'.csv';
        $rows = [['id', 'orden', 'estado_antes', 'estado_despues', 'origen_estado', 'updated_by_antes', 'updated_at_antes', 'pdf_antes', 'pdf_nuevo', 'error_pdf']];

        $restored = 0;
        $skipped = 0;
        $pdfFailures = 0;

        $bar = $this->output->createProgressBar($orders->count());
        $bar->start();

        foreach ($orders as $order) {
            /** @var OperationServiceOrder $order */
            $target = $previous[(int) $order->getKey()] ?? ['status' => ExpiredServiceOrderRestorer::FALLBACK_STATUS, 'source' => 'supuesto'];
            $before = [
                'updated_by' => (string) ($order->updated_by ?? ''),
                'updated_at' => (string) ($order->getRawOriginal('updated_at') ?? ''),
                'pdf' => (string) ($order->service_order_pdf_path ?? ''),
            ];

            try {
                if (! ExpiredServiceOrderRestorer::restore($order, $target['status'], $updatedBy)) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }
            } catch (Throwable $exception) {
                $skipped++;
                $this->newLine();
                $this->components->error("{$order->order_number}: no se pudo restaurar ({$exception->getMessage()})");
                $bar->advance();

                continue;
            }

            $restored++;
            $pdf = ['path' => null, 'error' => null];

            if ($withPdf) {
                $pdf = ExpiredServiceOrderRestorer::regeneratePdf($order);

                if ($pdf['error'] !== null) {
                    $pdfFailures++;
                }
            }

            $rows[] = [
                (string) $order->getKey(),
                (string) $order->order_number,
                OperationServiceOrderValidity::STATUS_EXPIRED,
                $target['status'],
                $target['source'],
                $before['updated_by'],
                $before['updated_at'],
                $before['pdf'],
                (string) ($pdf['path'] ?? ''),
                (string) ($pdf['error'] ?? ''),
            ];

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->writeCsv($csvPath, $rows);

        $this->components->twoColumnDetail('Órdenes restauradas', (string) $restored);
        $this->components->twoColumnDetail('Omitidas (ya no estaban CADUCADAS o fallaron)', (string) $skipped);

        if ($withPdf) {
            $this->components->twoColumnDetail('PDF que no se pudieron regenerar', (string) $pdfFailures);
        }

        $this->newLine();
        $this->line('  Respaldo de los cambios (CSV): '.Storage::disk('local')->path($csvPath));

        if ($pdfFailures > 0) {
            $this->components->warn('Algunos PDF fallaron (detalle en el CSV). Las órdenes sí quedaron restauradas; regenere esos PDF con: php artisan operation-service-orders:ensure-pdf {orden} --force');
        }

        return $pdfFailures > 0 ? CommandAlias::FAILURE : CommandAlias::SUCCESS;
    }

    /**
     * @return User|false|null false = error ya informado; null = sin usuario
     */
    private function resolveActor(): User|false|null
    {
        $userId = $this->option('user');

        if (blank($userId)) {
            return null;
        }

        $user = ctype_digit((string) $userId) ? User::query()->find((int) $userId) : null;

        if (! $user instanceof User) {
            $this->components->error("No existe el usuario con ID {$userId}.");

            return false;
        }

        Auth::setUser($user);

        return $user;
    }

    /**
     * @param  array{total: int, from_trace: int, assumed: int, would_expire_again: int, by_status: array<string, int>}  $summary
     */
    private function renderSummary(array $summary, bool $apply, bool $withPdf): void
    {
        $this->components->info($apply ? 'Restauración de órdenes CADUCADAS' : 'Simulación de restauración de órdenes CADUCADAS');

        $this->components->twoColumnDetail('Órdenes CADUCADAS encontradas', (string) $summary['total']);
        $this->components->twoColumnDetail('Estado anterior tomado de la traza', (string) $summary['from_trace']);
        $this->components->twoColumnDetail('Sin traza (vuelven a '.ExpiredServiceOrderRestorer::FALLBACK_STATUS.')', (string) $summary['assumed']);

        foreach ($summary['by_status'] as $status => $count) {
            $this->components->twoColumnDetail('  Quedarán en '.$status, (string) $count);
        }

        $this->components->twoColumnDetail(
            'Caducidad vigente',
            OperationServiceOrderValidity::expirationEnabled()
                ? 'activada · '.OperationServiceOrderValidity::validityDays().' día(s)'
                : 'desactivada',
        );
        $this->components->twoColumnDetail('Volverían a caducar', (string) $summary['would_expire_again']);
        $this->components->twoColumnDetail('Regenerar PDF sin la marca', $withPdf ? 'sí' : 'no (--without-pdf)');
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '\\');
        }

        rewind($handle);
        Storage::disk('local')->put($path, (string) stream_get_contents($handle));
        fclose($handle);
    }
}
