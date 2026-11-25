<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Operations\ListedPatientInvoiceBatch;
use App\Support\Operations\MedicationInvoiceException;
use Illuminate\Console\Command;
use Throwable;

/**
 * Crea, a nombre del paciente, las facturas del lote de órdenes que Operaciones
 * entregó con su N.º de siniestro. Sin --execute solo muestra qué haría.
 */
class IssueListedPatientInvoicesCommand extends Command
{
    protected $signature = 'operations:issue-listed-patient-invoices
        {--execute : Crear las facturas. Sin esta opción solo se muestra la vista previa}
        {--usuario= : Email del responsable que queda en la factura y en la bitácora (obligatorio con --execute)}
        {--solo= : Emitir o previsualizar una sola orden, por ejemplo ORD-0601}';

    protected $description = 'Emite las facturas a pacientes de las órdenes listadas, con su N.º de siniestro (vista previa por defecto).';

    public function handle(): int
    {
        $only = trim((string) $this->option('solo'));
        $rows = ListedPatientInvoiceBatch::rows();

        if ($only !== '') {
            $rows = array_values(array_filter($rows, fn (array $row): bool => $row['order'] === $only));

            if ($rows === []) {
                $this->error($only.' no está en el lote.');

                return self::FAILURE;
            }
        }

        $decisions = array_map(fn (array $row): array => ListedPatientInvoiceBatch::assessRow($row), $rows);
        $this->table(
            ['Orden', 'Paciente en ficha', 'Total US$', 'Resultado', 'Detalle'],
            array_map(fn (array $decision): array => [
                $decision['order'],
                $decision['patient'] ?: '—',
                $decision['total_usd'] !== null ? number_format($decision['total_usd'], 2, '.', '') : '—',
                $decision['action'],
                $decision['detail'],
            ], $decisions),
        );

        $ready = array_values(array_filter($decisions, fn (array $decision): bool => $decision['action'] === ListedPatientInvoiceBatch::ACTION_ISSUE));
        $this->info(count($ready).' factura(s) por emitir · '.count($decisions).' fila(s) del lote.');

        if (! (bool) $this->option('execute')) {
            $this->comment('Vista previa. Para crearlas: php artisan operations:issue-listed-patient-invoices --execute --usuario=correo@tudrencasa.com');

            return self::SUCCESS;
        }

        if ($ready === []) {
            $this->warn('No hay facturas por emitir.');

            return self::SUCCESS;
        }

        $user = User::query()->where('email', trim((string) $this->option('usuario')))->where('status', 'ACTIVO')->first();

        if (! $user instanceof User) {
            $this->error('Indique --usuario con el email de un usuario activo. Ese nombre queda en la factura y en la bitácora.');

            return self::FAILURE;
        }

        $created = 0;
        $failed = 0;

        foreach ($rows as $row) {
            if (collect($ready)->firstWhere('order', $row['order']) === null) {
                continue;
            }

            try {
                $invoice = ListedPatientInvoiceBatch::issue($row, $user);
            } catch (MedicationInvoiceException $exception) {
                $failed++;
                $this->error($row['order'].': '.$exception->getMessage());

                continue;
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
                $this->error($row['order'].': no se pudo emitir la factura. '.$exception->getMessage());

                continue;
            }

            $created++;
            $this->info($row['order'].' → '.$invoice->displayNumber().' · '.$invoice->patient_name.' · US$ '.number_format((float) $invoice->total_usd, 2, '.', '').' · siniestro '.$invoice->claim_number.' · pendiente por cobrar');
        }

        $this->newLine();
        $this->info('Emitidas: '.$created.'. Fallidas: '.$failed.'.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
