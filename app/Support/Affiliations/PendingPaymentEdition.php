<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\PaidMembership;
use App\Models\PaidMembershipCorporate;
use App\Models\PaymentEditLog;
use App\Models\User;
use App\Support\AffiliationPaymentBcvRateCalculator;
use App\Support\SecurityAudit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Edita los datos de un pago PENDIENTE desde «Pagos registrados» de
 * Administración (individual o corporativo), antes de aprobarlo.
 *
 * Al aprobar, {@see \App\Http\Controllers\PaidMembershipController::approvePayment()}
 * copia método, montos, bancos y referencias a la venta y la cobranza; por eso
 * solo se edita lo que aún no salió del pago: estatus PENDIENTE y sin venta,
 * cuotas, comisiones ni conciliaciones ({@see PendingPaymentDeletion::blockers()}).
 *
 * El total a pagar no se toca: lo fija la afiliación. Con él fijo, el monto en
 * dólares de un pago en divisas es el total y la tasa BCV se deriva (VES ÷ US$),
 * igual que al cargar el pago. Los comprobantes reemplazados no se borran; su
 * ruta anterior queda en la traza.
 */
final class PendingPaymentEdition
{
    public const NOTE_MIN_LENGTH = 15;

    public const NOTE_MAX_LENGTH = 1000;

    public const MAX_AMOUNT = 999999.99;

    public const OBSERVATIONS_MAX_LENGTH = 255;

    public const MULTIPLE = 'MULTIPLE';

    /** @var array<string, string> */
    public const METHOD_OPTIONS = [
        'ZELLE' => 'ZELLE',
        'TRANSFERENCIA US$' => 'TRANSFERENCIA(US$)',
        'EFECTIVO US$' => 'EFECTIVO US$',
        'LINK DE PAGO' => 'LINK DE PAGO',
        'PAGO MOVIL VES' => 'PAGO MOVIL(VES)',
        'TRANSFERENCIA VES' => 'TRANSFERENCIA(VES)',
        self::MULTIPLE => 'MULTIPLE',
    ];

    /** @var list<string> */
    public const USD_METHODS = ['ZELLE', 'TRANSFERENCIA US$', 'EFECTIVO US$', 'LINK DE PAGO'];

    /** @var list<string> */
    public const VES_METHODS = ['PAGO MOVIL VES', 'TRANSFERENCIA VES'];

    /** @var array<string, string> */
    public const MULTIPLE_USD_METHOD_OPTIONS = [
        'ZELLE' => 'ZELLE',
        'TRANSFERENCIA US$' => 'TRANSFERENCIA(US$)',
        'EFECTIVO US$' => 'EFECTIVO US$',
    ];

    /** @var array<string, string> */
    public const MULTIPLE_VES_METHOD_OPTIONS = [
        'PAGO MOVIL VES' => 'PAGO MOVIL(VES)',
        'TRANSFERENCIA VES' => 'TRANSFERENCIA(VES)',
    ];

    /** @var array<string, string> */
    public const BANK_USD_OPTIONS = [
        'CHASE BANK' => 'CHASE BANK',
        'BANK OF AMERICA' => 'BANK OF AMERICA',
        'BANESCO, S.A-US$' => 'BANESCO, S.A - US$',
        'BANCAMIGA - US$' => 'BANCAMIGA - US$',
        'BANCO DE VENEZUELA - US$' => 'BANCO DE VENEZUELA - US$',
    ];

    /** @var array<string, string> */
    public const BANK_USD_CASH_OPTIONS = [
        'BANCAMIGA - US$' => 'BANCAMIGA - US$',
        'BANCO DE VENEZUELA - US$' => 'BANCO DE VENEZUELA - US$',
    ];

    /** @var array<string, string> */
    public const BANK_VES_OPTIONS = [
        'BANCAMIGA(VES)' => 'BANCAMIGA',
        'BANCO DE VENEZUELA(VES)' => 'BANCO DE VENEZUELA',
    ];

    /** @var array<string, string> */
    public const BANK_VES_MULTIPLE_OPTIONS = [
        'BANCAMIGA - VES' => 'BANCAMIGA - VES',
        'BANCO DE VENEZUELA - VES' => 'BANCO DE VENEZUELA - VES',
    ];

    /**
     * Columnas que esta acción puede cambiar. `total_amount` no está a propósito.
     *
     * @var list<string>
     */
    public const EDITABLE_FIELDS = [
        'payment_method',
        'payment_method_usd',
        'payment_method_ves',
        'pay_amount_usd',
        'pay_amount_ves',
        'tasa_bcv',
        'name_ti_usd',
        'bank_usd',
        'bank_ves',
        'reference_payment_usd',
        'reference_payment_ves',
        'document_usd',
        'document_ves',
        'date_payment_voucher',
        'observations_payment',
    ];

    /** @var array<string, string> */
    public const FIELD_LABELS = [
        'payment_method' => 'Método de pago',
        'payment_method_usd' => 'Método en dólares (múltiple)',
        'payment_method_ves' => 'Método en bolívares (múltiple)',
        'pay_amount_usd' => 'Monto en US$',
        'pay_amount_ves' => 'Monto en VES',
        'tasa_bcv' => 'Tasa BCV',
        'name_ti_usd' => 'Titular de la cuenta (US$)',
        'bank_usd' => 'Banco (US$)',
        'bank_ves' => 'Banco (VES)',
        'reference_payment_usd' => 'Referencia (US$)',
        'reference_payment_ves' => 'Referencia (VES)',
        'document_usd' => 'Comprobante (US$)',
        'document_ves' => 'Comprobante (VES)',
        'date_payment_voucher' => 'Fecha del comprobante',
        'observations_payment' => 'Observaciones',
        'status' => 'Estatus',
    ];

    private const NOT_APPLICABLE = 'N/A';

    /** @var list<string> */
    private const AMOUNT_FIELDS = ['pay_amount_usd', 'pay_amount_ves', 'tasa_bcv', 'total_amount'];

    /**
     * Motivos por los que el pago no se puede editar. Vacío = se puede.
     *
     * @return list<string>
     */
    public static function blockers(PaidMembership|PaidMembershipCorporate $payment): array
    {
        $status = strtoupper(trim((string) $payment->status));

        if ($status !== PendingPaymentDeletion::PENDING_STATUS) {
            return ['El pago está '.($status !== '' ? $status : 'sin estatus').': solo se editan pagos PENDIENTES.'];
        }

        if ((float) $payment->total_amount <= 0) {
            return ['El pago no tiene un total a pagar válido; no se puede recalcular. Revise la afiliación.'];
        }

        return PendingPaymentDeletion::blockers($payment);
    }

    /**
     * Huella de lo editable: si cambia entre abrir el formulario y guardar, otro
     * usuario tocó el pago y no se pisa su cambio.
     */
    public static function fingerprint(PaidMembership|PaidMembershipCorporate $payment): string
    {
        $values = [
            'status' => strtoupper(trim((string) $payment->status)),
            'total_amount' => self::comparable('total_amount', $payment->total_amount),
        ];

        foreach (self::EDITABLE_FIELDS as $field) {
            $values[$field] = self::comparable($field, $payment->getAttribute($field));
        }

        return hash('sha256', (string) json_encode($values));
    }

    /**
     * Estado inicial del formulario: valores actuales sin los «N/A». Los
     * comprobantes no se precargan (algunos son URLs externas); se reemplazan.
     *
     * @return array<string, mixed>
     */
    public static function formState(PaidMembership|PaidMembershipCorporate $payment): array
    {
        $state = ['fingerprint' => self::fingerprint($payment), 'document_usd' => null, 'document_ves' => null];

        foreach (self::EDITABLE_FIELDS as $field) {
            if (in_array($field, ['document_usd', 'document_ves'], true)) {
                continue;
            }

            $state[$field] = self::blankToNull($payment->getAttribute($field));
        }

        return $state;
    }

    /**
     * Valida los datos recibidos y devuelve las columnas tal como se guardan,
     * con las mismas convenciones que la carga del pago («N/A», montos y tasa).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException con todos los errores juntos
     */
    public static function normalize(PaidMembership|PaidMembershipCorporate $payment, array $data): array
    {
        $errors = [];
        $total = round((float) $payment->total_amount, 2);
        $method = self::text($data['payment_method'] ?? null);

        if (! array_key_exists((string) $method, self::METHOD_OPTIONS)) {
            throw new InvalidArgumentException('Seleccione un método de pago válido.');
        }

        $attributes = array_fill_keys(self::EDITABLE_FIELDS, self::NOT_APPLICABLE);
        $attributes['payment_method'] = $method;
        $attributes['date_payment_voucher'] = self::voucherDate($data['date_payment_voucher'] ?? null, $errors);
        $attributes['observations_payment'] = self::observations($data['observations_payment'] ?? null, $errors);

        $isUsd = in_array($method, self::USD_METHODS, true);
        $isVes = in_array($method, self::VES_METHODS, true);
        $isMultiple = $method === self::MULTIPLE;
        $usdMethod = $isMultiple ? self::text($data['payment_method_usd'] ?? null) : ($isUsd ? $method : null);

        if ($isMultiple) {
            if (! array_key_exists((string) $usdMethod, self::MULTIPLE_USD_METHOD_OPTIONS)) {
                $errors[] = 'Seleccione el método de pago en dólares.';
            }

            $vesMethod = self::text($data['payment_method_ves'] ?? null);
            if (! array_key_exists((string) $vesMethod, self::MULTIPLE_VES_METHOD_OPTIONS)) {
                $errors[] = 'Seleccione el método de pago en bolívares.';
            }

            $attributes['payment_method_usd'] = (string) $usdMethod;
            $attributes['payment_method_ves'] = (string) $vesMethod;
        }

        /** Tramo en dólares */
        if ($isUsd || $isMultiple) {
            $needsHolder = in_array($usdMethod, ['ZELLE', 'TRANSFERENCIA US$', 'LINK DE PAGO'], true);
            $needsReference = in_array($usdMethod, ['ZELLE', 'LINK DE PAGO'], true);
            $needsBank = ! $isMultiple && in_array($usdMethod, ['TRANSFERENCIA US$', 'EFECTIVO US$'], true);
            $allowsBank = $isMultiple || $needsBank;

            if ($needsHolder) {
                $holder = self::text($data['name_ti_usd'] ?? null);
                if ($holder === null) {
                    $errors[] = 'Indique el nombre del titular de la cuenta en dólares.';
                } elseif (mb_strlen($holder) > 255) {
                    $errors[] = 'El nombre del titular no puede pasar de 255 caracteres.';
                }
                $attributes['name_ti_usd'] = $holder ?? self::NOT_APPLICABLE;
            }

            if ($needsReference) {
                $reference = self::text($data['reference_payment_usd'] ?? null);
                if ($reference === null) {
                    $errors[] = 'Indique la referencia del pago en dólares.';
                } elseif (! preg_match('/^[A-Za-z0-9\-]{1,100}$/', $reference)) {
                    $errors[] = 'La referencia en dólares solo admite letras, números y guion (máximo 100).';
                }
                $attributes['reference_payment_usd'] = $reference ?? self::NOT_APPLICABLE;
            }

            if ($allowsBank) {
                $options = $usdMethod === 'EFECTIVO US$' && ! $isMultiple ? self::BANK_USD_CASH_OPTIONS : self::BANK_USD_OPTIONS;
                $bank = self::text($data['bank_usd'] ?? null);
                if ($bank === null && $needsBank) {
                    $errors[] = 'Seleccione el banco del pago en dólares.';
                } elseif ($bank !== null && ! self::isAllowedOption($bank, $options, $payment->bank_usd)) {
                    $errors[] = 'El banco en dólares no es una opción válida.';
                }
                $attributes['bank_usd'] = $bank ?? self::NOT_APPLICABLE;
            }

            $attributes['document_usd'] = self::document($payment, 'document_usd', $data['document_usd'] ?? null, ! $isMultiple, 'en dólares', $errors);
        }

        /** Tramo en bolívares */
        if ($isVes || $isMultiple) {
            $vesAmount = self::amount($data['pay_amount_ves'] ?? null, 'El monto en bolívares', $errors);
            $attributes['pay_amount_ves'] = $vesAmount ?? 0;

            $options = $isMultiple ? self::BANK_VES_MULTIPLE_OPTIONS : self::BANK_VES_OPTIONS;
            $bank = self::text($data['bank_ves'] ?? null);
            if ($bank === null && $isMultiple) {
                $errors[] = 'Seleccione el banco del pago en bolívares.';
            } elseif ($bank !== null && ! self::isAllowedOption($bank, $options, $payment->bank_ves)) {
                $errors[] = 'El banco en bolívares no es una opción válida.';
            }
            $attributes['bank_ves'] = $bank ?? self::NOT_APPLICABLE;

            $reference = self::text($data['reference_payment_ves'] ?? null);
            if ($reference === null && $isMultiple) {
                $errors[] = 'Indique la referencia del pago en bolívares.';
            } elseif ($reference !== null && ! preg_match('/^\d{1,6}$/', $reference)) {
                $errors[] = 'La referencia en bolívares son los últimos 6 dígitos (solo números).';
            }
            $attributes['reference_payment_ves'] = $reference ?? self::NOT_APPLICABLE;

            $attributes['document_ves'] = self::document($payment, 'document_ves', $data['document_ves'] ?? null, true, 'en bolívares', $errors);
        }

        if ($isUsd) {
            $attributes['pay_amount_usd'] = $total;
            $attributes['pay_amount_ves'] = 0;
            $attributes['tasa_bcv'] = in_array(self::text($payment->payment_method), self::USD_METHODS, true) ? $payment->tasa_bcv : null;
        }

        if ($isVes) {
            $attributes['pay_amount_usd'] = 0;
            $attributes['tasa_bcv'] = self::rate(AffiliationPaymentBcvRateCalculator::rateFromVesAndUsdTotal($attributes['pay_amount_ves'], $total), $errors);
        }

        if ($isMultiple) {
            $usdAmount = self::amount($data['pay_amount_usd'] ?? null, 'El monto en dólares', $errors);
            $attributes['pay_amount_usd'] = $usdAmount ?? 0;

            if ($usdAmount !== null && $usdAmount >= $total) {
                $errors[] = 'En un pago múltiple el monto en dólares debe ser menor que el total a pagar ('.number_format($total, 2, ',', '.').' US$); el resto se paga en bolívares.';
            } elseif ($usdAmount !== null) {
                $attributes['tasa_bcv'] = self::rate(AffiliationPaymentBcvRateCalculator::rateFromVesAndRemainingUsd($attributes['pay_amount_ves'], $total - $usdAmount), $errors);
            }
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', array_values(array_unique($errors))));
        }

        return $attributes;
    }

    /**
     * Tasa que resultaría con los datos del formulario, para mostrarla en vivo.
     */
    public static function previewRate(mixed $method, mixed $vesAmount, mixed $usdAmount, mixed $total): ?string
    {
        if (in_array($method, self::VES_METHODS, true)) {
            return AffiliationPaymentBcvRateCalculator::rateFromVesAndUsdTotal($vesAmount, $total);
        }

        if ($method === self::MULTIPLE && is_numeric($usdAmount) && is_numeric($total)) {
            return AffiliationPaymentBcvRateCalculator::rateFromVesAndRemainingUsd($vesAmount, (float) $total - (float) $usdAmount);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{trace_id: int, changed: list<string>}
     *
     * @throws InvalidArgumentException si no se puede editar o los datos no son válidos
     */
    public static function update(
        PaidMembership|PaidMembershipCorporate $payment,
        array $data,
        string $note,
        User $actor,
        ?string $panel = null,
        ?string $expectedFingerprint = null,
    ): array {
        $note = trim((string) preg_replace('/\s+/u', ' ', $note));
        if (mb_strlen($note) < self::NOTE_MIN_LENGTH) {
            throw new InvalidArgumentException('El motivo debe explicar por qué se edita, en al menos '.self::NOTE_MIN_LENGTH.' caracteres.');
        }

        if (mb_strlen($note) > self::NOTE_MAX_LENGTH) {
            throw new InvalidArgumentException('El motivo no puede pasar de '.self::NOTE_MAX_LENGTH.' caracteres.');
        }

        if (! Schema::hasTable('payment_edit_logs')) {
            throw new InvalidArgumentException('Falta aplicar la actualización de base de datos de este módulo. Avise a soporte.');
        }

        $class = $payment::class;

        return DB::transaction(static function () use ($class, $payment, $data, $note, $actor, $panel, $expectedFingerprint): array {
            /** @var PaidMembership|PaidMembershipCorporate|null $locked */
            $locked = $class::query()->whereKey($payment->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                throw new InvalidArgumentException('Este pago ya no existe: otro usuario lo eliminó. Recargue la página.');
            }

            $blockers = self::blockers($locked);
            if ($blockers !== []) {
                throw new InvalidArgumentException(implode(' ', $blockers));
            }

            if ($expectedFingerprint !== null && ! hash_equals(self::fingerprint($locked), $expectedFingerprint)) {
                throw new InvalidArgumentException('Otro usuario modificó este pago mientras usted lo editaba. Cierre el formulario y ábralo de nuevo para ver los datos actuales.');
            }

            $attributes = self::normalize($locked, $data);
            $changes = [];

            foreach ($attributes as $field => $value) {
                $before = $locked->getAttribute($field);

                if (self::comparable($field, $before) !== self::comparable($field, $value)) {
                    $changes[$field] = ['old' => $before, 'new' => $value];
                }
            }

            if ($changes === []) {
                throw new InvalidArgumentException('No hay cambios que guardar: los datos son iguales a los actuales.');
            }

            $snapshot = $locked->getAttributes();
            $owner = self::owner($locked);
            $type = $locked instanceof PaidMembershipCorporate ? PendingPaymentDeletion::TYPE_CORPORATE : PendingPaymentDeletion::TYPE_INDIVIDUAL;

            $locked->forceFill(array_intersect_key($attributes, $changes))->save();

            $trace = PaymentEditLog::query()->create([
                'payment_type' => $type,
                'payment_id' => (int) $locked->getKey(),
                'affiliation_id' => $owner?->getKey(),
                'affiliation_code' => $owner?->getAttribute('code'),
                'changes' => $changes,
                'snapshot' => $snapshot,
                'note' => $note,
                'panel' => $panel,
                'actor_id' => $actor->getKey(),
                'actor_name' => (string) $actor->name,
                'ip' => request()?->ip(),
            ]);

            SecurityAudit::log('AUDIT_AFFILIATION_PENDING_PAYMENT_EDITED', 'affiliations.payments.edit-pending', [
                'payment_type' => $type,
                'payment_id' => (int) $locked->getKey(),
                'affiliation_code' => $owner?->getAttribute('code'),
                'trace_id' => (int) $trace->getKey(),
                'panel' => $panel,
                'note' => $note,
                'changed_fields' => array_keys($changes),
            ], $actor);

            return ['trace_id' => (int) $trace->getKey(), 'changed' => array_keys($changes)];
        });
    }

    public static function displayValue(string $field, mixed $value): string
    {
        $text = self::blankToNull($value);

        if ($text === null) {
            return '—';
        }

        if (in_array($field, ['pay_amount_usd', 'pay_amount_ves', 'tasa_bcv'], true) && is_numeric($text)) {
            return number_format((float) $text, 2, ',', '.');
        }

        if (in_array($field, ['document_usd', 'document_ves'], true)) {
            return basename((string) $text);
        }

        return (string) $text;
    }

    private static function owner(PaidMembership|PaidMembershipCorporate $payment): Affiliation|AffiliationCorporate|null
    {
        return $payment instanceof PaidMembershipCorporate
            ? $payment->affiliation_corporate
            : $payment->affiliation;
    }

    /**
     * @param  list<string>  $errors
     */
    private static function voucherDate(mixed $value, array &$errors): string
    {
        $text = self::text($value);

        if ($text === null) {
            $errors[] = 'Indique la fecha del comprobante.';

            return self::NOT_APPLICABLE;
        }

        try {
            $date = preg_match('#^\d{2}/\d{2}/\d{4}$#', $text) === 1
                ? Carbon::createFromFormat('!d/m/Y', $text)
                : Carbon::parse($text);
        } catch (Throwable) {
            $errors[] = 'La fecha del comprobante no es válida.';

            return self::NOT_APPLICABLE;
        }

        if ($date === false || $date->isAfter(today()) || $date->year < 2000) {
            $errors[] = 'La fecha del comprobante no puede ser futura ni anterior al año 2000.';

            return self::NOT_APPLICABLE;
        }

        return $date->format('d/m/Y');
    }

    /**
     * @param  list<string>  $errors
     */
    private static function observations(mixed $value, array &$errors): string
    {
        $text = self::text($value);

        if ($text !== null && mb_strlen($text) > self::OBSERVATIONS_MAX_LENGTH) {
            $errors[] = 'Las observaciones no pueden pasar de '.self::OBSERVATIONS_MAX_LENGTH.' caracteres.';
        }

        return $text ?? self::NOT_APPLICABLE;
    }

    /**
     * @param  list<string>  $errors
     */
    private static function amount(mixed $value, string $label, array &$errors): ?float
    {
        $amount = AffiliationPaymentBcvRateCalculator::positiveAmount(is_string($value) ? trim($value) : $value);

        if ($amount === null) {
            $errors[] = $label.' es obligatorio y debe ser mayor que cero.';

            return null;
        }

        if ($amount > self::MAX_AMOUNT) {
            $errors[] = $label.' no puede pasar de '.number_format(self::MAX_AMOUNT, 2, ',', '.').'.';

            return null;
        }

        return round($amount, 2);
    }

    /**
     * @param  list<string>  $errors
     */
    private static function rate(?string $rate, array &$errors): ?float
    {
        if ($rate === null || round((float) $rate, 2) <= 0 || (float) $rate > self::MAX_AMOUNT) {
            $errors[] = 'No se pudo calcular la tasa BCV con esos montos. Revise el monto en bolívares.';

            return null;
        }

        return round((float) $rate, 2);
    }

    /**
     * Comprobante que queda: el archivo nuevo si se subió; si no, el actual.
     *
     * @param  list<string>  $errors
     */
    private static function document(
        PaidMembership|PaidMembershipCorporate $payment,
        string $column,
        mixed $upload,
        bool $required,
        string $label,
        array &$errors,
    ): string {
        if (is_array($upload)) {
            $upload = array_values(array_filter($upload))[0] ?? null;
        }

        $new = self::text($upload);

        if ($new !== null) {
            if (str_contains($new, '..') || str_starts_with($new, '/') || ! Storage::disk(PaymentVoucherFile::DISK)->exists($new)) {
                $errors[] = 'El comprobante '.$label.' no terminó de subirse. Vuelva a adjuntarlo.';

                return self::NOT_APPLICABLE;
            }

            return $new;
        }

        $current = self::blankToNull($payment->getAttribute($column));

        if ($current === null && $required) {
            $errors[] = 'Adjunte el comprobante '.$label.': este pago no tiene uno cargado.';
        }

        return $current ?? self::NOT_APPLICABLE;
    }

    /**
     * @param  array<string, string>  $options
     */
    private static function isAllowedOption(string $value, array $options, mixed $current): bool
    {
        return array_key_exists($value, $options) || $value === self::text($current);
    }

    private static function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private static function blankToNull(mixed $value): ?string
    {
        $text = self::text($value);

        return $text === null || strtoupper($text) === self::NOT_APPLICABLE ? null : $text;
    }

    /**
     * Valor normalizado para comparar: «N/A», vacío y null son lo mismo, y los
     * montos se comparan con dos decimales.
     */
    private static function comparable(string $field, mixed $value): string
    {
        $text = self::blankToNull($value);

        if ($text === null) {
            return '';
        }

        return in_array($field, self::AMOUNT_FIELDS, true) && is_numeric($text)
            ? number_format((float) $text, 2, '.', '')
            : $text;
    }
}
