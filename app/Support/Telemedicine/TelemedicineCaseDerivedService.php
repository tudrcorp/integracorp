<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineServiceList;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Servicio derivado que el médico tiene por atender en un caso: el que dejó
 * indicado en la última consulta (`telemedicine_service_list_drift_id`). La
 * siguiente consulta del caso arranca justamente desde ese servicio.
 *
 * Se calcula en SQL, como {@see TelemedicineCaseFollowUpSchedule}, para mostrar,
 * filtrar y ordenar la tabla del escritorio sin consultas por fila.
 */
final class TelemedicineCaseDerivedService
{
    public const TONE_PENDING = 'pending';

    public const TONE_NONE = 'none';

    public const TONE_CRITICAL = 'critical';

    public const TONE_DERIVED = 'derived';

    /**
     * Valor del filtro para los casos con consultas pero sin derivado indicado.
     */
    public const FILTER_NONE = '__sin_derivado__';

    /**
     * Subconsulta de la última consulta del caso de la fila.
     */
    private static function latestConsultationCondition(string $alias): string
    {
        $cases = (new TelemedicineCase)->getTable();
        $consultations = (new TelemedicineConsultationPatient)->getTable();

        return "{$alias}.id = (select max(dmx.id) from {$consultations} dmx where dmx.telemedicine_case_id = {$cases}.id)";
    }

    /**
     * Expresión SQL con el nombre del servicio derivado de la última consulta.
     */
    public static function latestDriftNameSql(): string
    {
        $consultations = (new TelemedicineConsultationPatient)->getTable();
        $services = (new TelemedicineServiceList)->getTable();

        return "(select dsl.name from {$consultations} dlc"
            ." inner join {$services} dsl on dsl.id = dlc.telemedicine_service_list_drift_id"
            .' where '.self::latestConsultationCondition('dlc').')';
    }

    /**
     * Expresión SQL con la fecha de la última consulta del caso.
     */
    public static function latestConsultationAtSql(): string
    {
        $consultations = (new TelemedicineConsultationPatient)->getTable();

        return "(select dlc2.created_at from {$consultations} dlc2 where ".self::latestConsultationCondition('dlc2').')';
    }

    /**
     * Agrega `latest_drift_name` y `latest_consultation_at` a la consulta.
     *
     * @param  Builder<TelemedicineCase>  $query
     * @return Builder<TelemedicineCase>
     */
    public static function withDerivedServiceColumns(Builder $query): Builder
    {
        $cases = (new TelemedicineCase)->getTable();

        if ($query->getQuery()->columns === null) {
            $query->select("{$cases}.*");
        }

        return $query
            ->selectRaw(self::latestDriftNameSql().' as latest_drift_name')
            ->selectRaw(self::latestConsultationAtSql().' as latest_consultation_at');
    }

    /**
     * @param  Builder<TelemedicineCase>  $query
     * @return Builder<TelemedicineCase>
     */
    public static function whereDerivedService(Builder $query, ?string $value): Builder
    {
        if (blank($value)) {
            return $query;
        }

        $consultations = (new TelemedicineConsultationPatient)->getTable();
        $cases = (new TelemedicineCase)->getTable();

        if ($value === self::FILTER_NONE) {
            return $query
                ->whereRaw("exists (select 1 from {$consultations} fcx where fcx.telemedicine_case_id = {$cases}.id)")
                ->whereRaw(self::latestDriftNameSql().' is null');
        }

        return $query->whereRaw(self::latestDriftNameSql().' = ?', [$value]);
    }

    /**
     * Casos con una AMD (asistencia médica domiciliaria) por realizar: la
     * última consulta dejó AMD como derivado y todavía no se registró la
     * consulta de la AMD, que pasaría a ser la última. Se reconoce por nombre
     * porque el catálogo no tiene un código estable para el servicio.
     *
     * @param  Builder<TelemedicineCase>  $query
     * @return Builder<TelemedicineCase>
     */
    public static function whereAmdPending(Builder $query): Builder
    {
        $latestDriftName = self::latestDriftNameSql();

        return $query->where(function (Builder $amd) use ($latestDriftName): void {
            $amd->whereRaw($latestDriftName." like 'AMD%'")
                ->orWhereRaw($latestDriftName." like '%ASISTENCIA MEDICA DOMICILIARIA%'");
        });
    }

    public static function driftNameIsAmd(?string $name): bool
    {
        $normalized = mb_strtoupper(Str::ascii(trim((string) $name)));

        return str_starts_with($normalized, 'AMD') || str_contains($normalized, 'ASISTENCIA MEDICA DOMICILIARIA');
    }

    /**
     * Opciones del filtro: los servicios que alguna vez se indicaron como
     * derivados (por nombre, porque el catálogo repite nombres con ids distintos).
     *
     * @return array<string, string>
     */
    public static function filterOptions(): array
    {
        $options = TelemedicineServiceList::query()
            ->whereIn('id', TelemedicineConsultationPatient::query()
                ->whereNotNull('telemedicine_service_list_drift_id')
                ->select('telemedicine_service_list_drift_id'))
            ->orderBy('name')
            ->pluck('name')
            ->filter(fn (?string $name): bool => filled($name))
            ->unique()
            ->mapWithKeys(fn (string $name): array => [$name => $name])
            ->all();

        return $options + [self::FILTER_NONE => 'Sin derivado indicado'];
    }

    /**
     * Texto de la celda «Derivado por atender».
     *
     * @return array{label: string, detail: string|null, tone: string}
     */
    public static function describe(
        ?string $driftName,
        mixed $latestConsultationAt,
        bool $pendingFirstConsultation,
        ?CarbonInterface $now = null,
    ): array {
        if ($pendingFirstConsultation) {
            return ['label' => 'Pendiente de primera consulta', 'detail' => null, 'tone' => self::TONE_PENDING];
        }

        $detail = self::consultationDetail($latestConsultationAt, $now);
        $driftName = trim((string) $driftName);

        if ($driftName === '') {
            return ['label' => 'Sin derivado indicado', 'detail' => $detail, 'tone' => self::TONE_NONE];
        }

        return [
            'label' => $driftName,
            'detail' => $detail,
            'tone' => TelemedicineDerivedServiceBadge::driftNameIsCritical($driftName) ? self::TONE_CRITICAL : self::TONE_DERIVED,
        ];
    }

    private static function consultationDetail(mixed $latestConsultationAt, ?CarbonInterface $now): ?string
    {
        if (blank($latestConsultationAt)) {
            return null;
        }

        $now ??= now();
        $at = $latestConsultationAt instanceof CarbonInterface
            ? $latestConsultationAt
            : Carbon::parse((string) $latestConsultationAt);

        return 'Consulta del '.$at->format('d/m/Y').' · '.$at->locale('es')->diffForHumans($now, CarbonInterface::DIFF_RELATIVE_TO_NOW);
    }
}
