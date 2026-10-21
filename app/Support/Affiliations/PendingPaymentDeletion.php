<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Collection as CollectionModel;
use App\Models\Commission;
use App\Models\CreditReconciliation;
use App\Models\DeletedPaymentVoucher;
use App\Models\PaidMembership;
use App\Models\PaidMembershipCorporate;
use App\Models\Sale;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Elimina un pago PENDIENTE de «Historial de pagos» (individual o corporativo)
 * solo si no dejó rastro en ningún otro lugar.
 *
 * Cargar un pago únicamente crea la fila y sube el comprobante. Venta, cuotas
 * de cobranza, comisiones y conciliaciones de crédito nacen al aprobarlo. Si
 * alguna existe, el pago no es «solo pendiente» y no se borra: hay que
 * resolverlo antes en Administración. El recibo se busca siempre por número
 * **y** afiliación, porque los números de recibo se repiten entre afiliaciones.
 *
 * El comprobante no se destruye: se copia a un disco privado antes de borrar la
 * fila y el original público se quita solo cuando la transacción confirmó. Si
 * otro pago usa el mismo archivo, se deja donde está.
 */
final class PendingPaymentDeletion
{
    public const TYPE_INDIVIDUAL = 'INDIVIDUAL';

    public const TYPE_CORPORATE = 'CORPORATIVO';

    public const PENDING_STATUS = 'PENDIENTE';

    public const NOTE_MIN_LENGTH = 15;

    public const NOTE_MAX_LENGTH = 1000;

    public const ARCHIVE_DISK = 'local';

    public const ARCHIVE_DIRECTORY = 'comprobantes-eliminados';

    /** @var list<string> */
    private const DOCUMENT_COLUMNS = ['document_usd', 'document_ves'];

    /** Tablas de pagos que guardan rutas de comprobantes en el disco público. */
    private const PAYMENT_TABLES = ['paid_memberships', 'paid_membership_corporates', 'company_paid_memberships'];

    /**
     * Motivos por los que el pago no se puede eliminar. Vacío = se puede.
     *
     * @return list<string>
     */
    public static function blockers(PaidMembership|PaidMembershipCorporate $payment): array
    {
        $blockers = [];
        $status = strtoupper(trim((string) $payment->status));

        if ($status !== self::PENDING_STATUS) {
            $blockers[] = 'El pago está '.($status !== '' ? $status : 'sin estatus').': solo se eliminan pagos PENDIENTES.';
        }

        $ownerCode = self::ownerCode($payment);
        $invoice = trim((string) $payment->invoice_number);

        if ($invoice !== '' && $ownerCode !== null) {
            $sales = Sale::query()
                ->where('affiliation_code', $ownerCode)
                ->where('invoice_number', $invoice)
                ->get(['id', 'invoice_number', 'created_at']);

            if ($sales->isNotEmpty()) {
                $blockers[] = 'Tiene la venta '.$invoice.' en Administración → Ventas (ya pasó por aprobación). Resuélvala allí antes de eliminar el pago.';
            }

            $collections = CollectionModel::query()
                ->where('affiliation_code', $ownerCode)
                ->where(function ($query) use ($invoice, $sales): void {
                    $query->where('collection_invoice_number', $invoice);

                    if ($sales->isNotEmpty()) {
                        $query->orWhereIn('sale_id', $sales->modelKeys());
                    }
                })
                ->count();

            if ($collections > 0) {
                $blockers[] = 'Tiene '.$collections.' cuota(s) de cobranza ligadas al recibo '.$invoice.'.';
            }

            $commissions = Commission::query()
                ->where('affiliation_code', $ownerCode)
                ->where('code', $invoice)
                ->count();

            if ($commissions > 0) {
                $blockers[] = 'Tiene '.$commissions.' comisión(es) calculadas con el recibo '.$invoice.'.';
            }
        }

        $reconciliationColumn = $payment instanceof PaidMembershipCorporate ? 'paid_membership_corporate_id' : 'paid_membership_id';
        if (CreditReconciliation::query()->where($reconciliationColumn, $payment->getKey())->exists()) {
            $blockers[] = 'Tiene movimientos de crédito de empresa aliada (conciliación) ligados a este pago.';
        }

        return $blockers;
    }

    /**
     * @return array{archived: list<string>, kept: list<string>, trace_id: int|null}
     */
    public static function delete(PaidMembership|PaidMembershipCorporate $payment, string $note, User $actor, ?string $panel = null): array
    {
        $note = trim((string) preg_replace('/\s+/u', ' ', $note));
        if (mb_strlen($note) < self::NOTE_MIN_LENGTH) {
            throw new InvalidArgumentException('La nota debe explicar por qué se elimina, en al menos '.self::NOTE_MIN_LENGTH.' caracteres.');
        }

        if (mb_strlen($note) > self::NOTE_MAX_LENGTH) {
            throw new InvalidArgumentException('La nota no puede pasar de '.self::NOTE_MAX_LENGTH.' caracteres.');
        }

        if (! Schema::hasTable('deleted_payment_vouchers')) {
            throw new InvalidArgumentException('Falta aplicar la actualización de base de datos de este módulo. Avise a soporte.');
        }

        $class = $payment::class;
        $copied = [];

        try {
            $result = DB::transaction(static function () use ($class, $payment, $note, $actor, $panel, &$copied): array {
                /** @var PaidMembership|PaidMembershipCorporate|null $locked */
                $locked = $class::query()->whereKey($payment->getKey())->lockForUpdate()->first();

                if ($locked === null) {
                    throw new InvalidArgumentException('Este pago ya no existe: otro usuario lo eliminó o lo cambió. Recargue la página.');
                }

                $blockers = self::blockers($locked);
                if ($blockers !== []) {
                    throw new InvalidArgumentException(implode(' ', $blockers));
                }

                $files = self::archiveFiles($locked, $copied);
                $owner = self::owner($locked);
                $type = $locked instanceof PaidMembershipCorporate ? self::TYPE_CORPORATE : self::TYPE_INDIVIDUAL;

                $trace = DeletedPaymentVoucher::query()->create([
                    'payment_type' => $type,
                    'payment_id' => (int) $locked->getKey(),
                    'affiliation_id' => $owner?->getKey(),
                    'affiliation_code' => $owner?->getAttribute('code'),
                    'snapshot' => $locked->getAttributes(),
                    'files' => $files === [] ? null : $files,
                    'note' => $note,
                    'panel' => $panel,
                    'actor_id' => $actor->getKey(),
                    'actor_name' => (string) $actor->name,
                    'ip' => request()?->ip(),
                ]);

                $locked->delete();

                SecurityAudit::log('AUDIT_AFFILIATION_PENDING_PAYMENT_DELETED', 'affiliations.payments.delete-pending', [
                    'payment_type' => $type,
                    'payment_id' => (int) $locked->getKey(),
                    'affiliation_code' => $owner?->getAttribute('code'),
                    'trace_id' => (int) $trace->getKey(),
                    'panel' => $panel,
                    'note' => $note,
                    'files' => $files,
                ], $actor);

                return ['files' => $files, 'trace_id' => (int) $trace->getKey()];
            });
        } catch (Throwable $exception) {
            // La fila sigue en su sitio: las copias archivadas sobran.
            foreach ($copied as $archivedPath) {
                Storage::disk(self::ARCHIVE_DISK)->delete($archivedPath);
            }

            throw $exception;
        }

        $archived = [];
        $kept = [];

        foreach ($result['files'] as $file) {
            if (($file['archived_path'] ?? null) === null) {
                $kept[] = (string) $file['original_path'];

                continue;
            }

            try {
                Storage::disk(PaymentVoucherFile::DISK)->delete((string) $file['original_path']);
                $archived[] = (string) $file['original_path'];
            } catch (Throwable $exception) {
                // El pago ya no existe y la copia está a salvo: el original público queda huérfano.
                Log::warning('No se pudo quitar el comprobante público de un pago eliminado.', [
                    'trace_id' => $result['trace_id'],
                    'path' => $file['original_path'],
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return ['archived' => $archived, 'kept' => $kept, 'trace_id' => $result['trace_id']];
    }

    /**
     * Pagos PENDIENTES que no se pueden eliminar porque ya tienen rastro de
     * aprobación (venta, cobranza, comisión o conciliación). Para revisarlos con
     * Administración.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function blockedPendingPayments(): Collection
    {
        $rows = collect();

        foreach ([PaidMembership::class => 'affiliation', PaidMembershipCorporate::class => 'affiliation_corporate'] as $class => $relation) {
            $class::query()
                ->with($relation)
                ->whereRaw('UPPER(TRIM(status)) = ?', [self::PENDING_STATUS])
                ->chunkById(200, function ($payments) use (&$rows): void {
                    foreach ($payments as $payment) {
                        $blockers = self::blockers($payment);
                        if ($blockers === []) {
                            continue;
                        }

                        $owner = self::owner($payment);
                        $invoice = trim((string) $payment->invoice_number);
                        $sale = $invoice !== '' && $owner !== null
                            ? Sale::query()->where('affiliation_code', $owner->getAttribute('code'))->where('invoice_number', $invoice)->first()
                            : null;

                        $rows->push([
                            'tipo' => $payment instanceof PaidMembershipCorporate ? self::TYPE_CORPORATE : self::TYPE_INDIVIDUAL,
                            'pago_id' => (int) $payment->getKey(),
                            'afiliacion' => (string) ($owner?->getAttribute('code') ?? ''),
                            'titular_o_empresa' => (string) ($owner instanceof AffiliationCorporate ? $owner->name_corporate : ($owner?->full_name_ti ?? '')),
                            'cargado_el' => $payment->created_at?->format('d/m/Y H:i'),
                            'cargado_por' => (string) $payment->created_by,
                            'recibo' => $invoice,
                            'monto_usd' => $payment->pay_amount_usd,
                            'monto_bs' => $payment->pay_amount_ves,
                            'referencia_bs' => $payment->reference_payment_ves,
                            'referencia_usd' => $payment->reference_payment_usd,
                            'venta_id' => $sale?->getKey(),
                            'venta_creada' => $sale?->created_at?->format('d/m/Y H:i'),
                            'venta_comision' => $sale?->status_payment_commission,
                            'motivos' => implode(' ', $blockers),
                        ]);
                    }
                });
        }

        return $rows;
    }

    public static function ownerCode(PaidMembership|PaidMembershipCorporate $payment): ?string
    {
        $code = self::owner($payment)?->getAttribute('code');

        return filled($code) ? (string) $code : null;
    }

    private static function owner(PaidMembership|PaidMembershipCorporate $payment): Affiliation|AffiliationCorporate|null
    {
        return $payment instanceof PaidMembershipCorporate
            ? $payment->affiliation_corporate
            : $payment->affiliation;
    }

    /**
     * Copia cada comprobante al disco privado (no destructivo). Devuelve el plan:
     * qué se archivó y qué se dejó en su sitio porque otro pago lo usa.
     *
     * @param  list<string>  $copied
     * @return list<array{column: string, original_path: string, archived_path: string|null, kept_reason: string|null}>
     */
    private static function archiveFiles(PaidMembership|PaidMembershipCorporate $payment, array &$copied): array
    {
        $files = [];
        $type = $payment instanceof PaidMembershipCorporate ? 'corporativo' : 'individual';

        foreach (self::DOCUMENT_COLUMNS as $column) {
            $path = PaymentVoucherFile::path($payment, $column === 'document_usd' ? PaymentVoucherFile::CURRENCY_USD : PaymentVoucherFile::CURRENCY_VES);
            if ($path === null) {
                continue;
            }

            if (self::fileUsedElsewhere($payment, $path)) {
                $files[] = ['column' => $column, 'original_path' => $path, 'archived_path' => null, 'kept_reason' => 'Otro pago usa el mismo archivo'];

                continue;
            }

            if (! PaymentVoucherFile::exists($path)) {
                $files[] = ['column' => $column, 'original_path' => $path, 'archived_path' => null, 'kept_reason' => 'El archivo ya no estaba en el servidor'];

                continue;
            }

            $archivedPath = self::ARCHIVE_DIRECTORY.'/'.$type.'/'.$payment->getKey().'/'.basename($path);
            $stream = Storage::disk(PaymentVoucherFile::DISK)->readStream($path);

            if ($stream === null || ! Storage::disk(self::ARCHIVE_DISK)->writeStream($archivedPath, $stream)) {
                throw new InvalidArgumentException('No se pudo resguardar el comprobante antes de eliminar. No se eliminó nada; intente de nuevo.');
            }

            if (is_resource($stream)) {
                fclose($stream);
            }

            $copied[] = $archivedPath;
            $files[] = ['column' => $column, 'original_path' => $path, 'archived_path' => $archivedPath, 'kept_reason' => null];
        }

        return $files;
    }

    private static function fileUsedElsewhere(PaidMembership|PaidMembershipCorporate $payment, string $path): bool
    {
        foreach (self::PAYMENT_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $query = DB::table($table)->where(function ($builder) use ($table, $path): void {
                foreach (self::DOCUMENT_COLUMNS as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $builder->orWhere($column, $path);
                    }
                }
            });

            if ($table === $payment->getTable()) {
                $query->where('id', '!=', $payment->getKey());
            }

            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }
}
