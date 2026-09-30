<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MissingCollectionsFromSaleGenerator;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class GenerateMissingCollectionsFromSaleCommand extends Command
{
    protected $signature = 'collections:generate-from-sale
                            {affiliation_code : Código individual o corporativo (ej. TDEC-IND-000119 o TDEC-COR-00025)}
                            {--invoice= : Factura de la venta ya registrada. Si se omite, usa la última venta}
                            {--execute : Sin este flag solo muestra la vista previa}';

    protected $description = 'Crea cobranzas pendientes a partir de una venta ya registrada (individual o corporativa). No duplica la venta.';

    public function handle(MissingCollectionsFromSaleGenerator $generator): int
    {
        $code = trim((string) $this->argument('affiliation_code'));
        $invoice = $this->option('invoice');
        $persist = (bool) $this->option('execute');

        try {
            $result = $generator->generate(
                affiliationCode: $code,
                invoiceNumber: is_string($invoice) && $invoice !== '' ? $invoice : null,
                persist: $persist,
                actor: 'SISTEMA',
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('No se pudo generar la cobranza: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info($persist ? 'Cobranzas creadas (la venta no se tocó).' : 'Vista previa: no se escribió nada. Agregue --execute para crear.');
        $this->line('Afiliación: '.$result['affiliation_code'].' ('.$this->affiliationKindLabel($result['affiliation_kind'] ?? 'individual').')');
        $this->line('Venta #'.$result['sale_id'].'  factura '.$result['invoice_number']);
        $this->line('Frecuencia: '.$result['payment_frequency']);
        $this->line('Fechas nuevas: '.($result['dates'] === [] ? 'ninguna' : implode(', ', $result['dates'])));
        $this->line('Ya existían: '.$result['skipped_existing']);
        $this->line('Creadas: '.$result['created']);

        if (! $persist && $result['dates'] !== []) {
            $this->comment('Para crearlas: php artisan collections:generate-from-sale '.$result['affiliation_code'].' --invoice='.$result['invoice_number'].' --execute');
        }

        return self::SUCCESS;
    }

    private function affiliationKindLabel(string $kind): string
    {
        return $kind === 'corporate' ? 'corporativa' : 'individual';
    }
}
