<?php

declare(strict_types=1);

namespace App\Support\Affiliations\PersonIdentity;

use App\Enums\PersonIdentitySource;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Company;
use App\Models\CompanyAssociate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Una persona tal como está registrada en una de las tres poblaciones, con su identidad comparable
 * y el contexto que hace falta para explicarle al analista dónde está.
 */
final class PersonIdentityRecord
{
    /**
     * Estados de persona que ya no cuentan: la persona salió de la población.
     *
     * @var list<string>
     */
    public const INACTIVE_PERSON_STATUSES = ['EXCLUIDO', 'EXCLUIDA', 'INACTIVO', 'INACTIVA', 'ANULADO', 'ANULADA', 'BAJA'];

    /**
     * Estados de afiliación que ya no cuentan: toda su población salió.
     *
     * @var list<string>
     */
    public const INACTIVE_AFFILIATION_STATUSES = ['EXCLUIDO', 'EXCLUIDA', 'INACTIVO', 'INACTIVA', 'ANULADO', 'ANULADA', 'BAJA'];

    public function __construct(
        public readonly PersonIdentitySource $source,
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $document,
        public readonly string $birthDate,
        public readonly ?string $documentKey,
        public readonly ?string $nameKey,
        public readonly ?string $birthDateKey,
        public readonly string $status,
        public readonly ?int $parentId,
        public readonly string $parentCode,
        public readonly string $parentName,
        public readonly string $parentStatus,
        public readonly bool $parentExists,
        public readonly ?Carbon $coverageStart = null,
        public readonly ?Carbon $coverageEnd = null,
        public readonly bool $repeatedName = false,
    ) {}

    /**
     * @param  Affiliation|AffiliationCorporate|Company|null  $parent  si ya se tiene a mano, evita una consulta
     */
    public static function fromModel(Model $model, ?Model $parent = null): self
    {
        $source = PersonIdentitySource::fromModel($model);
        [$name, $nameKey] = self::nameOf($model);

        if ($model instanceof CompanyAssociate) {
            $company = $parent instanceof Company ? $parent : ($model->company_id ? Company::query()->find($model->company_id, ['id', 'name']) : null);
            [$start, $end] = self::associateCoverage($model);

            return new self(
                source: $source,
                id: $model->getKey() !== null ? (int) $model->getKey() : null,
                name: $name,
                document: trim((string) $model->identity_card),
                birthDate: self::birthDateDisplay($model->getAttribute('birth_date')),
                documentKey: PersonIdentityNormalizer::documentKey($model->identity_card),
                nameKey: $nameKey,
                birthDateKey: PersonIdentityNormalizer::birthDateKey($model->getAttribute('birth_date')),
                status: self::statusOf($model->getAttribute('status')),
                parentId: $model->company_id !== null ? (int) $model->company_id : null,
                parentCode: 'Vuelo '.($start?->format('d/m/Y') ?? 'sin fecha'),
                parentName: (string) ($company?->name ?? 'Empresa #'.$model->company_id),
                parentStatus: '',
                parentExists: true,
                coverageStart: $start,
                coverageEnd: $end,
                repeatedName: PersonIdentityNormalizer::isRepeatedName((string) $model->full_name),
            );
        }

        $foreignKey = $model instanceof Affiliate ? 'affiliation_id' : 'affiliation_corporate_id';
        $parentClass = $model instanceof Affiliate ? Affiliation::class : AffiliationCorporate::class;
        $parentId = $model->getAttribute($foreignKey) !== null ? (int) $model->getAttribute($foreignKey) : null;

        if (! $parent instanceof $parentClass && $parentId !== null) {
            $parent = $parentClass::query()->find($parentId, $model instanceof Affiliate
                ? ['id', 'code', 'status', 'full_name_payer', 'full_name_ti']
                : ['id', 'code', 'status', 'name_corporate']);
        }

        return new self(
            source: $source,
            id: $model->getKey() !== null ? (int) $model->getKey() : null,
            name: $name,
            document: trim((string) $model->getAttribute('nro_identificacion')),
            birthDate: self::birthDateDisplay($model->getAttribute('birth_date')),
            documentKey: PersonIdentityNormalizer::documentKey($model->getAttribute('nro_identificacion')),
            nameKey: $nameKey,
            birthDateKey: PersonIdentityNormalizer::birthDateKey($model->getAttribute('birth_date')),
            status: self::statusOf($model->getAttribute('status')),
            parentId: $parentId,
            parentCode: (string) ($parent?->getAttribute('code') ?? ''),
            parentName: (string) ($parent instanceof AffiliationCorporate
                ? $parent->name_corporate
                : ($parent?->getAttribute('full_name_payer') ?: $parent?->getAttribute('full_name_ti') ?? '')),
            parentStatus: self::statusOf($parent?->getAttribute('status')),
            parentExists: $parent !== null,
            repeatedName: $model instanceof AffiliateCorporate
                ? PersonIdentityNormalizer::isRepeatedName((string) $model->first_name, (string) $model->last_name)
                : PersonIdentityNormalizer::isRepeatedName((string) $model->getAttribute('full_name')),
        );
    }

    /**
     * Nombre legible y nombre comparable del registro.
     *
     * @return array{0: string, 1: ?string}
     */
    public static function nameOf(Model $model): array
    {
        if ($model instanceof AffiliateCorporate) {
            $first = trim((string) $model->first_name);
            $last = trim((string) $model->last_name);
            $display = PersonIdentityNormalizer::isRepeatedName($first, $last) || trim($last, '. ') === ''
                ? $first
                : trim($first.' '.$last);

            return [$display, PersonIdentityNormalizer::nameKey($first, $last)];
        }

        $full = trim((string) $model->getAttribute('full_name'));

        return [$full, PersonIdentityNormalizer::nameKey($full)];
    }

    /**
     * Si la persona sigue vigente en su población. Un asociado de Nuevos Negocios lo está hasta que
     * termina la cobertura de su vuelo; una persona de una afiliación, mientras ella y su afiliación
     * no estén dadas de baja.
     */
    public function isActive(?Carbon $today = null): bool
    {
        if (in_array($this->status, self::INACTIVE_PERSON_STATUSES, true)) {
            return false;
        }

        if ($this->source === PersonIdentitySource::Associate) {
            $today ??= now()->startOfDay();

            return $this->coverageEnd === null || $this->coverageEnd->greaterThanOrEqualTo($today);
        }

        return $this->parentExists && ! in_array($this->parentStatus, self::INACTIVE_AFFILIATION_STATUSES, true);
    }

    /**
     * Dos registros vigentes chocan en el tiempo. Dos vuelos de Nuevos Negocios solo chocan si sus
     * días de cobertura se cruzan: viajar dos veces no es estar duplicado.
     */
    public function overlapsInTime(self $other): bool
    {
        if ($this->source !== PersonIdentitySource::Associate || $other->source !== PersonIdentitySource::Associate) {
            return true;
        }

        if ($this->coverageStart === null || $other->coverageStart === null) {
            return true;
        }

        $thisEnd = $this->coverageEnd ?? $this->coverageStart;
        $otherEnd = $other->coverageEnd ?? $other->coverageStart;

        return $this->coverageStart->lessThanOrEqualTo($otherEnd) && $other->coverageStart->lessThanOrEqualTo($thisEnd);
    }

    public function sameRecordAs(self $other): bool
    {
        return $this->source === $other->source && $this->id !== null && $this->id === $other->id;
    }

    public function reference(): string
    {
        return $this->source->value.':'.($this->id ?? 'nuevo');
    }

    /**
     * Dónde está la persona, en una frase: «Afiliado corporativo en TDEC-COR-00053 (EMPRESA), estatus ACTIVO».
     */
    public function whereDescription(): string
    {
        $where = match ($this->source) {
            PersonIdentitySource::Individual => 'en la afiliación individual '.($this->parentCode ?: '#'.$this->parentId),
            PersonIdentitySource::Corporate => 'en la afiliación corporativa '.($this->parentCode ?: '#'.$this->parentId).($this->parentName !== '' ? ' ('.$this->parentName.')' : ''),
            PersonIdentitySource::Associate => 'de '.$this->parentName.' ('.mb_strtolower($this->parentCode).($this->coverageEnd !== null ? ', cobertura hasta el '.$this->coverageEnd->format('d/m/Y') : '').')',
        };

        return $this->source->label().' '.$where.($this->status !== '' ? ', estatus '.$this->status : '');
    }

    /**
     * Foto de lo que se ve hoy del registro, para guardar en el detector.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source->value,
            'id' => $this->id,
            'name' => $this->name,
            'document' => $this->document,
            'birth_date' => $this->birthDate,
            'document_key' => $this->documentKey,
            'name_key' => $this->nameKey,
            'birth_date_key' => $this->birthDateKey,
            'status' => $this->status,
            'parent_id' => $this->parentId,
            'parent_code' => $this->parentCode,
            'parent_name' => $this->parentName,
            'parent_status' => $this->parentStatus,
            'where' => $this->whereDescription(),
        ];
    }

    /**
     * Inicio y fin de la cobertura de un asociado: el vuelo, o el voucher ILS cuando ya lo tiene.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private static function associateCoverage(CompanyAssociate $associate): array
    {
        $flight = $associate->flight_date instanceof Carbon ? $associate->flight_date->copy()->startOfDay() : PersonIdentityNormalizer::birthDate($associate->flight_date);
        $voucherStart = PersonIdentityNormalizer::birthDate($associate->date_init);
        $voucherEnd = PersonIdentityNormalizer::birthDate($associate->date_end);
        $days = max(1, (int) ($associate->registration_period_days ?? 1));

        $start = $voucherStart ?? $flight;
        $end = $flight?->copy()->addDays($days - 1);

        if ($voucherEnd !== null && ($end === null || $voucherEnd->greaterThan($end))) {
            $end = $voucherEnd;
        }

        return [$start, $end];
    }

    private static function statusOf(mixed $status): string
    {
        if ($status instanceof \BackedEnum) {
            $status = $status->value;
        }

        return mb_strtoupper(trim((string) $status));
    }

    private static function birthDateDisplay(mixed $value): string
    {
        $date = PersonIdentityNormalizer::birthDate($value);

        return $date?->format('d/m/Y') ?? trim($value instanceof \DateTimeInterface ? $value->format('d/m/Y') : (string) $value);
    }
}
