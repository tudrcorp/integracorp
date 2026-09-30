<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Enums\SystemNotificationKey;
use App\Jobs\NotifyAffiliationsTeamOfAffiliateUpdateJob;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\SecurityAudit;
use App\Support\SystemNotificationRecipients;
use App\Support\Telemedicine\TelemedicinePatientDisplayName;
use App\Support\Telemedicine\TelemedicinePatientIdentity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Única vía por la que Operaciones cambia datos de un afiliado, individual
 * ({@see Affiliate}) o corporativo ({@see AffiliateCorporate}).
 *
 * Solo toca datos personales: plan, cobertura, rango de edad, tarifa, montos,
 * estado, parentesco y voucher ILS quedan fuera aunque lleguen en `$data`. La
 * edad se deriva de la fecha de nacimiento y no se recalcula la tarifa: si la
 * edad nueva sale del rango tarifario (solo individuales lo tienen), el aviso a
 * Afiliaciones lo señala.
 *
 * Un valor equivalente al guardado no cuenta como cambio (`M` y `MASCULINO`,
 * `0424-1234567` y `04241234567`, `7/5/2009` y `07/05/2009`): abrir la ficha y
 * guardar sin tocar nada no escribe ni avisa.
 *
 * En la misma transacción se actualiza el paciente de telemedicina vinculado
 * (se vinculan por cédula): si no, un cambio de cédula lo dejaría huérfano.
 * El aviso a Afiliaciones se encola al confirmar la transacción.
 */
final class AffiliatePersonalDataUpdater
{
    public const SOURCE_EDIT_FORM = 'edit_form';

    public const SOURCE_MAPS_ADDRESS = 'maps_address';

    public const KIND_INDIVIDUAL = 'individual';

    public const KIND_CORPORATE = 'corporate';

    /**
     * Campos que Operaciones puede cambiar en un afiliado individual.
     *
     * @var array<string, string>
     */
    public const EDITABLE_FIELDS = [
        'full_name' => 'Nombre completo',
        'nro_identificacion' => 'Cédula',
        'sex' => 'Sexo',
        'birth_date' => 'Fecha de nacimiento',
        'age' => 'Edad',
        'phone' => 'Teléfono',
        'email' => 'Correo',
        'country_id' => 'País',
        'state_id' => 'Estado',
        'city_id' => 'Ciudad',
        'region' => 'Región',
        'address' => 'Dirección',
        'stature' => 'Estatura',
        'weight' => 'Peso',
    ];

    /**
     * Campos que Operaciones puede cambiar en un afiliado corporativo.
     *
     * @var array<string, string>
     */
    public const CORPORATE_EDITABLE_FIELDS = [
        'first_name' => 'Nombres',
        'last_name' => 'Apellidos',
        'nro_identificacion' => 'Cédula',
        'sex' => 'Sexo',
        'birth_date' => 'Fecha de nacimiento',
        'age' => 'Edad',
        'phone' => 'Teléfono',
        'email' => 'Correo',
        'position_company' => 'Cargo en la empresa',
        'address' => 'Dirección',
        'full_name_emergency' => 'Contacto de emergencia',
        'phone_emergency' => 'Teléfono de emergencia',
    ];

    /**
     * Campos del afiliado individual que se replican en su paciente.
     *
     * @var list<string>
     */
    public const TELEMEDICINE_SYNCED_FIELDS = [
        'full_name',
        'nro_identificacion',
        'sex',
        'birth_date',
        'age',
        'phone',
        'country_id',
        'state_id',
        'city_id',
        'region',
        'address',
    ];

    /**
     * Campos del afiliado corporativo que se replican en su paciente. El
     * nombre se arma con nombres y apellidos; el correo del paciente es el
     * del propio colaborador.
     *
     * @var list<string>
     */
    public const CORPORATE_TELEMEDICINE_SYNCED_FIELDS = [
        'nro_identificacion',
        'sex',
        'birth_date',
        'age',
        'phone',
        'email',
        'address',
    ];

    public const BIRTH_DATE_FORMAT = 'd/m/Y';

    public const SEX_OPTIONS = ['MASCULINO', 'FEMENINO'];

    private const UPPERCASE_TEXT_FIELDS = ['full_name', 'first_name', 'last_name', 'full_name_emergency', 'position_company', 'region'];

    private const PHONE_FIELDS = ['phone', 'phone_emergency'];

    /**
     * @param  array<string, mixed>  $data
     */
    public static function update(
        Affiliate|AffiliateCorporate $affiliate,
        array $data,
        ?User $actor,
        string $source = self::SOURCE_EDIT_FORM,
    ): AffiliatePersonalDataUpdateResult {
        $incoming = self::normalize(array_intersect_key($data, self::editableFieldsFor($affiliate)));

        return DB::transaction(function () use ($affiliate, $incoming, $actor, $source): AffiliatePersonalDataUpdateResult {
            /** @var Affiliate|AffiliateCorporate $locked */
            $locked = $affiliate->newQuery()->whereKey($affiliate->getKey())->lockForUpdate()->firstOrFail();

            $changes = self::diff($locked, $incoming);

            if ($changes === []) {
                return new AffiliatePersonalDataUpdateResult($locked, [], false, null);
            }

            $patient = self::linkedTelemedicinePatient((string) $locked->nro_identificacion);

            if (array_key_exists('nro_identificacion', $changes)) {
                self::guardDocumentIsFreeInTelemedicine((string) $changes['nro_identificacion']['after'], $patient);
            }

            $locked->forceFill(array_map(
                static fn (array $change): mixed => $change['after'],
                $changes,
            ))->save();

            $telemedicineSynced = self::syncTelemedicinePatient($patient, $locked, $changes);
            $ageRangeWarning = $locked instanceof Affiliate ? self::ageRangeWarning($locked) : null;

            $result = new AffiliatePersonalDataUpdateResult($locked, $changes, $telemedicineSynced, $ageRangeWarning);

            SecurityAudit::log(
                $locked instanceof AffiliateCorporate
                    ? 'AUDIT_OPERATIONS_CORPORATE_AFFILIATE_PERSONAL_DATA_UPDATED'
                    : 'AUDIT_OPERATIONS_AFFILIATE_PERSONAL_DATA_UPDATED',
                'operations.affiliates.personal-data',
                [
                    'kind' => self::kindOf($locked),
                    'affiliate_id' => $locked->getKey(),
                    'source' => $source,
                    'changes' => $changes,
                    'telemedicine_patient_id' => $patient?->getKey(),
                    'telemedicine_synced' => $telemedicineSynced,
                    'age_range_warning' => $ageRangeWarning,
                ],
                $actor,
            );

            $payload = AffiliateUpdateNotificationMessage::payload($result, $actor, $source);

            DB::afterCommit(static function () use ($payload): void {
                NotifyAffiliationsTeamOfAffiliateUpdateJob::dispatch($payload);
            });

            return $result;
        });
    }

    /**
     * @return array<string, string>
     */
    public static function editableFieldsFor(Affiliate|AffiliateCorporate $affiliate): array
    {
        return $affiliate instanceof AffiliateCorporate ? self::CORPORATE_EDITABLE_FIELDS : self::EDITABLE_FIELDS;
    }

    public static function kindOf(Affiliate|AffiliateCorporate $affiliate): string
    {
        return $affiliate instanceof AffiliateCorporate ? self::KIND_CORPORATE : self::KIND_INDIVIDUAL;
    }

    /**
     * Si el aviso a Afiliaciones está activo y tiene a quién llegar
     * (Centro de notificaciones → «Actualización de afiliados (Operaciones)»).
     */
    public static function affiliationsTeamWillBeNotified(): bool
    {
        $key = SystemNotificationKey::OperationsAffiliateUpdate;

        return SystemNotificationRecipients::isActive($key)
            && (SystemNotificationRecipients::emails($key) !== [] || SystemNotificationRecipients::phones($key) !== []);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        $normalized = [];

        foreach ($data as $field => $value) {
            $normalized[$field] = match (true) {
                in_array($field, self::UPPERCASE_TEXT_FIELDS, true) => self::nullableString(mb_strtoupper(Str::squish((string) $value))),
                in_array($field, self::PHONE_FIELDS, true) => self::nullableString(self::normalizePhone((string) $value)),
                $field === 'nro_identificacion' => self::nullableString(self::normalizeDocument((string) $value)),
                $field === 'sex' => TelemedicinePatientIdentity::normalizeSex($value),
                $field === 'birth_date' => self::normalizeBirthDate($value),
                $field === 'email' => self::nullableString(mb_strtolower(trim((string) $value))),
                $field === 'address' => self::nullableString(Str::squish((string) $value)),
                in_array($field, ['country_id', 'state_id', 'city_id'], true) => filled($value) ? (int) $value : null,
                in_array($field, ['stature', 'weight'], true) => self::nullableString(str_replace(',', '.', trim((string) $value))),
                default => $value,
            };
        }

        if (array_key_exists('birth_date', $normalized)) {
            $normalized['age'] = self::ageFromBirthDate($normalized['birth_date']);
        } else {
            unset($normalized['age']);
        }

        return $normalized;
    }

    /**
     * Cédula tal como la guarda Negocios: solo dígitos, sin prefijo ni separadores.
     */
    public static function normalizeDocument(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    public static function normalizePhone(string $value): string
    {
        return preg_replace('/[^\d+]/', '', $value) ?? '';
    }

    /**
     * Regla de validación: la cédula, ya sin prefijo ni separadores, tiene 5 a 12 dígitos.
     */
    public static function documentIsValid(mixed $value): bool
    {
        $raw = trim((string) $value);

        return preg_match('/^[VEJPGvejpg]?[\s.\-]*[\d.\-\s]+$/', $raw) === 1
            && preg_match('/^\d{5,12}$/', self::normalizeDocument($raw)) === 1;
    }

    /**
     * Regla de validación: un teléfono vacío es válido; si no, 10 a 15 dígitos
     * después de quitar espacios, guiones y paréntesis.
     */
    public static function phoneIsValid(mixed $value): bool
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return true;
        }

        return preg_match('/^\+?[\d\s().\-]+$/', $raw) === 1
            && preg_match('/^\+?\d{10,15}$/', self::normalizePhone($raw)) === 1;
    }

    /**
     * La base mezcla `d/m/Y`, `d-m-Y` y `j/n/Y`: se acepta cualquiera y se guarda `d/m/Y`.
     */
    public static function normalizeBirthDate(mixed $value): ?string
    {
        $date = self::parseBirthDate($value);

        return $date?->format(self::BIRTH_DATE_FORMAT);
    }

    public static function parseBirthDate(mixed $value): ?Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (['d/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'Y-m-d', 'Y-m-d H:i:s'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }

            if ($date !== null && $date->format($format) === $value) {
                return $date;
            }
        }

        return null;
    }

    public static function ageFromBirthDate(?string $birthDate): ?string
    {
        $date = self::parseBirthDate($birthDate);

        if ($date === null || $date->isFuture()) {
            return null;
        }

        return (string) $date->age;
    }

    /**
     * Mensaje cuando la edad del afiliado quedó fuera del rango tarifario con
     * el que se le cobra. La tarifa no se toca: lo decide Negocios.
     */
    public static function ageRangeWarning(Affiliate $affiliate): ?string
    {
        $age = $affiliate->age;

        if (! is_numeric($age) || $affiliate->age_range_id === null) {
            return null;
        }

        $range = $affiliate->ageRange()->first(['id', 'range', 'age_init', 'age_end']);

        if ($range === null || ! is_numeric($range->age_init) || ! is_numeric($range->age_end)) {
            return null;
        }

        $age = (int) $age;

        if ($age >= (int) $range->age_init && $age <= (int) $range->age_end) {
            return null;
        }

        return 'La edad ('.$age.' años) quedó fuera del rango tarifario asignado ('
            .($range->range ?: $range->age_init.'–'.$range->age_end)
            .'). La tarifa no se recalculó: revisar con Negocios.';
    }

    /**
     * Forma comparable de un valor: dos valores equivalentes no son un cambio.
     */
    public static function comparable(string $field, mixed $value): string
    {
        return match (true) {
            in_array($field, self::UPPERCASE_TEXT_FIELDS, true), $field === 'address' => mb_strtoupper(Str::squish((string) $value)),
            in_array($field, self::PHONE_FIELDS, true) => ltrim(self::normalizePhone((string) $value), '+'),
            $field === 'nro_identificacion' => self::normalizeDocument((string) $value),
            $field === 'sex' => (string) TelemedicinePatientIdentity::normalizeSex($value),
            $field === 'birth_date' => (string) (self::normalizeBirthDate($value) ?? trim((string) $value)),
            $field === 'email' => mb_strtolower(trim((string) $value)),
            in_array($field, ['country_id', 'state_id', 'city_id'], true) => filled($value) ? (string) (int) $value : '',
            in_array($field, ['stature', 'weight'], true) => str_replace(',', '.', trim((string) $value)),
            default => trim((string) $value),
        };
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @return array<string, array{label: string, before: mixed, after: mixed}>
     */
    private static function diff(Affiliate|AffiliateCorporate $affiliate, array $incoming): array
    {
        $labels = self::editableFieldsFor($affiliate);
        $changes = [];

        foreach ($incoming as $field => $after) {
            $before = $affiliate->getAttribute($field);

            if (self::comparable($field, $before) === self::comparable($field, $after)) {
                continue;
            }

            $changes[$field] = [
                'label' => $labels[$field],
                'before' => $before,
                'after' => $after,
            ];
        }

        return $changes;
    }

    private static function linkedTelemedicinePatient(string $document): ?TelemedicinePatient
    {
        $document = TelemedicinePatientIdentity::normalizeDocument($document);

        if ($document === '') {
            return null;
        }

        return TelemedicinePatient::query()
            ->where('nro_identificacion', $document)
            ->lockForUpdate()
            ->first();
    }

    private static function guardDocumentIsFreeInTelemedicine(string $newDocument, ?TelemedicinePatient $linked): void
    {
        $owner = TelemedicinePatient::query()
            ->where('nro_identificacion', TelemedicinePatientIdentity::normalizeDocument($newDocument))
            ->first(['id', 'full_name']);

        if ($owner === null || ($linked !== null && $owner->is($linked))) {
            return;
        }

        throw ValidationException::withMessages([
            'nro_identificacion' => [
                'La cédula '.$newDocument.' ya pertenece al paciente de telemedicina «'.$owner->full_name
                .'». No se puede asignar a este afiliado sin unificar antes los registros con Telemedicina.',
            ],
        ]);
    }

    /**
     * Replica en el paciente solo lo que cambió: lo que Telemedicina haya
     * corregido por su lado en otros campos se respeta.
     *
     * @param  array<string, array{label: string, before: mixed, after: mixed}>  $changes
     */
    private static function syncTelemedicinePatient(
        ?TelemedicinePatient $patient,
        Affiliate|AffiliateCorporate $affiliate,
        array $changes,
    ): bool {
        if ($patient === null) {
            return false;
        }

        $syncedFields = $affiliate instanceof AffiliateCorporate
            ? self::CORPORATE_TELEMEDICINE_SYNCED_FIELDS
            : self::TELEMEDICINE_SYNCED_FIELDS;

        $attributes = [];

        foreach ($syncedFields as $field) {
            if (array_key_exists($field, $changes)) {
                $attributes[$field] = $changes[$field]['after'];
            }
        }

        if ($affiliate instanceof AffiliateCorporate && (array_key_exists('first_name', $changes) || array_key_exists('last_name', $changes))) {
            $attributes['full_name'] = mb_strtoupper(TelemedicinePatientDisplayName::fromAffiliateCorporate($affiliate));
        }

        if (array_key_exists('nro_identificacion', $attributes)) {
            $attributes['nro_identificacion'] = TelemedicinePatientIdentity::normalizeDocument((string) $attributes['nro_identificacion']);
        }

        if ($attributes === []) {
            return false;
        }

        $patient->forceFill($attributes)->save();

        return true;
    }

    private static function nullableString(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * Nombres legibles de país, estado y ciudad para el aviso.
     */
    public static function displayValue(string $field, mixed $value): string
    {
        if (blank($value)) {
            return '—';
        }

        $name = match ($field) {
            'country_id' => Country::query()->whereKey($value)->value('name'),
            'state_id' => State::query()->whereKey($value)->value('definition'),
            'city_id' => City::query()->whereKey($value)->value('definition'),
            default => null,
        };

        if ($name !== null) {
            return (string) $name;
        }

        return match ($field) {
            'age' => $value.' años',
            'stature' => $value.' m',
            'weight' => $value.' kg',
            default => (string) $value,
        };
    }
}
