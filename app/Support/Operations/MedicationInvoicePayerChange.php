<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationCoordinationService;
use App\Models\OperationMedicationInvoice;
use App\Models\OperationMedicationInvoiceEvent;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cambia a nombre de quién va una factura a paciente: el propio paciente o el grupo corporativo
 * (o la empresa de Nuevos Negocios) al que pertenece.
 *
 * Los datos del nuevo responsable (nombre, cédula o RIF, dirección, teléfono y correo) se toman
 * de su ficha vigente con {@see QuoteInvoicePayer}. Si la ficha no trae dirección, se usa la que
 * escriba el analista o, si el responsable no cambia de tipo, la que la factura ya tenía.
 *
 * Solo facturas pendientes por cobrar o cobradas; nunca anuladas. La cotización de origen no se
 * toca: conserva lo que se aprobó. Cada cambio queda en la bitácora de la factura.
 */
final class MedicationInvoicePayerChange
{
    public const AUDIT_PAYER_CHANGED = 'AUDIT_OPERATIONS_MEDICATION_INVOICE_PAYER_CHANGED';

    public const REASON_MIN = 5;

    public const REASON_MAX = 500;

    /** Estados en los que se puede cambiar el responsable. */
    public const CHANGEABLE_STATUSES = [
        OperationMedicationInvoice::STATUS_PENDING_PAYMENT,
        OperationMedicationInvoice::STATUS_PAID,
    ];

    public static function isChangeable(OperationMedicationInvoice $invoice): bool
    {
        return in_array($invoice->status, self::CHANGEABLE_STATUSES, true)
            && filled($invoice->operation_coordination_service_id);
    }

    /** Responsable actual; una factura sin tipo (emitida desde el inventario) va a nombre del paciente. */
    public static function currentType(OperationMedicationInvoice $invoice): string
    {
        return $invoice->payer_type === OperationMedicationInvoice::PAYER_CORPORATE
            ? OperationMedicationInvoice::PAYER_CORPORATE
            : OperationMedicationInvoice::PAYER_PATIENT;
    }

    public static function currentName(OperationMedicationInvoice $invoice): string
    {
        return filled($invoice->payer_name) ? (string) $invoice->payer_name : (string) $invoice->patient_name;
    }

    /**
     * Opciones del responsable para la factura, tomadas de las fichas vigentes.
     *
     * @return array{
     *     patient: array{name: string, identification: string|null, address: string|null, phone: string|null, email: string|null},
     *     corporate: array{name: string, identification: string|null, address: string|null, phone: string|null, email: string|null}|null,
     *     corporate_unavailable_reason: string|null
     * }|null null si la factura no tiene servicio médico
     */
    public static function options(OperationMedicationInvoice $invoice): ?array
    {
        $coordination = $invoice->relationLoaded('operationCoordinationService')
            ? $invoice->operationCoordinationService
            : OperationCoordinationService::query()->find($invoice->operation_coordination_service_id);

        return $coordination instanceof OperationCoordinationService ? QuoteInvoicePayer::options($coordination) : null;
    }

    /**
     * @throws MedicationInvoiceException
     */
    public static function change(OperationMedicationInvoice $invoice, ?string $type, mixed $reason, mixed $manualAddress = null, ?User $user = null): OperationMedicationInvoice
    {
        $user ??= Auth::user();
        $userName = filled($user?->name) ? (string) $user->name : 'SISTEMA';
        $reason = trim(preg_replace('/\s+/u', ' ', (string) $reason) ?? '');

        if (! in_array($type, [OperationMedicationInvoice::PAYER_PATIENT, OperationMedicationInvoice::PAYER_CORPORATE], true)) {
            throw new MedicationInvoiceException('Indique a nombre de quién va la factura: el paciente o el grupo corporativo.');
        }

        if (mb_strlen($reason) < self::REASON_MIN) {
            throw new MedicationInvoiceException('Indique el motivo del cambio.');
        }

        $reason = mb_substr($reason, 0, self::REASON_MAX);

        [$result, $before, $after] = DB::transaction(function () use ($invoice, $type, $reason, $manualAddress, $user, $userName): array {
            $locked = OperationMedicationInvoice::query()->whereKey($invoice->getKey())->lockForUpdate()->first();

            if (! $locked instanceof OperationMedicationInvoice) {
                throw new MedicationInvoiceException('La factura ya no existe.');
            }

            $number = $locked->displayNumber();

            if (! in_array($locked->status, self::CHANGEABLE_STATUSES, true)) {
                throw new MedicationInvoiceException('La factura '.$number.' está anulada: no se le cambia el responsable.');
            }

            $options = self::options($locked);

            if ($options === null) {
                throw new MedicationInvoiceException('La factura '.$number.' no tiene un servicio médico asociado para tomar los datos del responsable.');
            }

            $payer = $type === OperationMedicationInvoice::PAYER_PATIENT ? $options['patient'] : $options['corporate'];

            if ($payer === null) {
                throw new MedicationInvoiceException($options['corporate_unavailable_reason'] ?? 'El paciente no pertenece a un grupo corporativo.');
            }

            $sameType = self::currentType($locked) === $type;
            $address = $payer['address'] ?? self::clean($manualAddress) ?? ($sameType ? self::clean($locked->payer_address) : null);

            if ($address === null) {
                throw new MedicationInvoiceException(($type === OperationMedicationInvoice::PAYER_PATIENT ? 'La ficha del paciente' : 'La ficha del grupo corporativo')
                    .' no tiene dirección: escríbala en el cambio individual de la factura '.$number.'.');
            }

            $after = [
                'payer_type' => $type,
                'payer_name' => $payer['name'],
                'payer_identification' => $payer['identification'],
                'payer_address' => mb_substr($address, 0, QuoteInvoicePayer::ADDRESS_MAX),
                'payer_phone' => self::limit($payer['phone'] ?? ($sameType ? $locked->payer_phone : null), QuoteInvoicePayer::PHONE_MAX),
                'payer_email' => self::limit($payer['email'] ?? ($sameType ? $locked->payer_email : null), QuoteInvoicePayer::EMAIL_MAX),
            ];

            $before = $locked->only(array_keys($after));

            if ($before === $after) {
                throw new MedicationInvoiceException('La factura '.$number.' ya está a nombre de '.$payer['name'].'.');
            }

            $locked->forceFill($after)->save();

            $beforeLabel = mb_strtolower((string) OperationMedicationInvoice::payerLabel(self::currentTypeFrom($before['payer_type'])));
            $afterLabel = mb_strtolower((string) OperationMedicationInvoice::payerLabel($type));

            OperationMedicationInvoiceEvent::query()->create([
                'operation_medication_invoice_id' => $locked->getKey(),
                'event' => OperationMedicationInvoiceEvent::EVENT_PAYER_CHANGED,
                'description' => 'Responsable de pago cambiado por '.$userName.': '
                    .$beforeLabel.' ('.(filled($before['payer_name']) ? $before['payer_name'] : $locked->patient_name).')'
                    .' → '.$afterLabel.' ('.$after['payer_name'].(filled($after['payer_identification']) ? ', '.$after['payer_identification'] : '').'). '
                    .'Motivo: '.$reason.'.',
                'payload' => ['before' => $before, 'after' => $after, 'reason' => $reason],
                'user_id' => $user?->getKey(),
                'user_name' => $userName,
            ]);

            return [$locked, $before, $after];
        });

        SecurityAudit::log(self::AUDIT_PAYER_CHANGED, MedicationInvoiceIssuer::AUDIT_ROUTE, [
            'operation_medication_invoice_id' => $result->getKey(),
            'before' => $before['payer_type'],
            'after' => $after['payer_type'],
        ], $user);

        return $result;
    }

    /**
     * Cambio masivo: cada factura toma los datos de su propio paciente o grupo, en su propia
     * transacción. Las que no se pueden cambiar se omiten con el motivo.
     *
     * @param  iterable<int, int|string>  $ids
     * @return array{changed: list<string>, skipped: list<array{number: string, reason: string}>}
     */
    public static function changeMany(iterable $ids, ?string $type, mixed $reason, ?User $user = null): array
    {
        $result = ['changed' => [], 'skipped' => []];
        $ids = array_values(array_unique(array_map('intval', is_array($ids) ? $ids : iterator_to_array($ids))));

        OperationMedicationInvoice::query()
            ->whereKey($ids)
            ->with('operationCoordinationService')
            ->orderBy('id')
            ->chunk(100, function ($invoices) use (&$result, $type, $reason, $user): void {
                foreach ($invoices as $invoice) {
                    try {
                        $result['changed'][] = self::change($invoice, $type, $reason, null, $user)->displayNumber();
                    } catch (MedicationInvoiceException $exception) {
                        $result['skipped'][] = ['number' => $invoice->displayNumber(), 'reason' => $exception->getMessage()];
                    } catch (Throwable $exception) {
                        report($exception);
                        $result['skipped'][] = ['number' => $invoice->displayNumber(), 'reason' => 'Error inesperado; intente de nuevo.'];
                    }
                }
            });

        return $result;
    }

    private static function currentTypeFrom(mixed $type): string
    {
        return $type === OperationMedicationInvoice::PAYER_CORPORATE
            ? OperationMedicationInvoice::PAYER_CORPORATE
            : OperationMedicationInvoice::PAYER_PATIENT;
    }

    private static function limit(mixed $value, int $max): ?string
    {
        $text = self::clean($value);

        return $text !== null ? mb_substr($text, 0, $max) : null;
    }

    private static function clean(mixed $value): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

        return $text === '' ? null : $text;
    }
}
