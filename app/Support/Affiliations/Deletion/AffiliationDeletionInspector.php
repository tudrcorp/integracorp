<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Deletion;

use App\Enums\PersonIdentitySource;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\AnnualCollection;
use App\Models\Collection as CollectionModel;
use App\Models\Commission;
use App\Models\CreditReconciliation;
use App\Models\OperationCoordinationService;
use App\Models\OperationServiceOrder;
use App\Models\Sale;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Support\Affiliations\PersonIdentity\PersonIdentityGuard;
use App\Support\Affiliations\PersonIdentity\PersonIdentityRecord;
use App\Support\Collections\CollectionAdjuster;
use App\Support\Telemedicine\TelemedicinePatientPlanBridge;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Revisión de seguridad antes de eliminar una afiliación. Solo lee.
 *
 * Bloquea (la afiliación no se puede eliminar mientras tenga):
 * - dinero movido: pagos aprobados o comprobantes por revisar, cuotas pagadas, comisiones, ventas,
 *   conciliaciones de crédito, cobranza anual o renovaciones;
 * - afiliados con casos clínicos en curso o servicios pendientes / en gestión (también órdenes de
 *   servicio en gestión);
 * - el código repetido en otra afiliación (no se podría separar su cobranza).
 *
 * Y calcula exactamente lo que hará la eliminación: afiliados que se ocultan, cuotas por pagar que se
 * anulan, pacientes de telemedicina que se desvinculan (o se pasan a la afiliación vigente de la
 * misma persona) y casos cerrados que se ocultan o se conservan, y por qué.
 */
final class AffiliationDeletionInspector
{
    /**
     * Casos que ya terminaron: no bloquean y se ocultan con la afiliación.
     *
     * @var list<string>
     */
    public const CLOSED_CASE_STATUSES = ['ALTA MEDICA', 'REGISTRO DIRECTO', 'RETAIL'];

    /**
     * @var list<string>
     */
    public const CLOSED_SERVICE_STATUSES = ['FINALIZADO', 'FINALIZADA'];

    /**
     * @var list<string>
     */
    public const CLOSED_ORDER_STATUSES = ['FINALIZADO', 'FINALIZADA', 'CADUCADA', 'CADUCADO', 'CANCELADA', 'CANCELADO', 'ANULADA', 'ANULADO'];

    /**
     * Pagos que cuentan como dinero movido o por revisar.
     *
     * @var list<string>
     */
    public const BLOCKING_PAYMENT_STATUSES = ['APROBADO', 'PAGADO', 'PENDIENTE'];

    public const SHOWN_ITEMS = 8;

    /**
     * @return array{
     *     ok: bool,
     *     checks: list<array{key: string, label: string, passed: bool, detail: string, lines: list<string>}>,
     *     affiliates: list<array{id: int, name: string, document: string, status: string}>,
     *     collections: list<array{id: int, invoice: string, due: string, amount: float}>,
     *     patients: list<array{id: int, name: string, document: string, affiliate_id: int, action: string, other: ?array{source: string, id: int, parent_id: ?int, parent_code: string, parent_name: string, status: string, where: string}, hide_cases: list<array{id: int, code: string, status: string, created: string}>, kept_cases: list<array{code: string, why: string}>}>
     * }
     */
    public static function inspect(AffiliationDeletionSubject $subject): array
    {
        $affiliates = $subject->affiliatesQuery()->get();
        $patients = self::patientsOf($subject, $affiliates);
        $patientIds = array_map(static fn (array $patient): int => $patient['id'], $patients);

        $checks = [
            self::paymentsCheck($subject),
            self::collectionsCheck($subject),
            self::accountingCheck($subject),
            self::renovationsCheck($subject),
            self::openCasesCheck($patientIds, $patients),
            self::pendingServicesCheck($patientIds, $patients),
            self::codeCheck($subject),
        ];

        return [
            'ok' => array_reduce($checks, static fn (bool $carry, array $check): bool => $carry && $check['passed'], true),
            'checks' => $checks,
            'affiliates' => $affiliates->map(static fn (Affiliate|AffiliateCorporate $affiliate): array => [
                'id' => (int) $affiliate->getKey(),
                'name' => AffiliationDeletionSubject::affiliateName($affiliate),
                'document' => trim((string) $affiliate->nro_identificacion),
                'status' => mb_strtoupper(trim((string) $affiliate->status)),
            ])->values()->all(),
            'collections' => CollectionModel::query()
                ->where('affiliation_code', $subject->code())
                ->where('status', CollectionAdjuster::STATUS_PENDING)
                ->orderBy('id')
                ->get(['id', 'collection_invoice_number', 'next_payment_date', 'total_amount'])
                ->map(static fn (CollectionModel $collection): array => [
                    'id' => (int) $collection->getKey(),
                    'invoice' => (string) ($collection->collection_invoice_number ?? '#'.$collection->getKey()),
                    'due' => (string) ($collection->next_payment_date ?? ''),
                    'amount' => round((float) $collection->total_amount, 2),
                ])->values()->all(),
            'patients' => $patients,
        ];
    }

    /**
     * Pacientes de telemedicina asociados hoy a la afiliación (por la misma vía que usa el resto del
     * sistema) y qué se hará con cada uno y con sus casos cerrados.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Affiliate|AffiliateCorporate>  $affiliates
     * @return list<array<string, mixed>>
     */
    private static function patientsOf(AffiliationDeletionSubject $subject, $affiliates): array
    {
        $since = Carbon::parse($subject->affiliation->created_at ?? now())->startOfDay();
        $patients = [];

        foreach ($affiliates as $affiliate) {
            $affiliate->setRelation($subject->isCorporate() ? 'affiliationCorporate' : 'affiliation', $subject->affiliation);

            foreach (TelemedicinePatientPlanBridge::linkedPatientsFor($affiliate) as $patient) {
                /** @var TelemedicinePatient $patient */
                if (isset($patients[(int) $patient->getKey()])) {
                    continue;
                }

                $other = self::otherActiveRecord($subject, $affiliate);
                $hide = [];
                $kept = [];

                foreach (TelemedicineCase::query()->where('telemedicine_patient_id', $patient->getKey())->orderBy('id')->get(['id', 'code', 'status', 'created_at']) as $case) {
                    $status = mb_strtoupper(trim((string) $case->status));

                    if (! in_array($status, self::CLOSED_CASE_STATUSES, true)) {
                        continue;
                    }

                    if ($other !== null) {
                        $kept[] = ['code' => (string) $case->code, 'why' => 'la persona sigue vigente en '.$other['parent_code'].', a quien pertenece su historia'];

                        continue;
                    }

                    if ($case->created_at !== null && $case->created_at->lt($since)) {
                        $kept[] = ['code' => (string) $case->code, 'why' => 'es anterior a esta afiliación (abierto el '.$case->created_at->format('d/m/Y').')'];

                        continue;
                    }

                    $hide[] = [
                        'id' => (int) $case->getKey(),
                        'code' => (string) $case->code,
                        'status' => $status,
                        'created' => $case->created_at?->format('d/m/Y') ?? '',
                    ];
                }

                $patients[(int) $patient->getKey()] = [
                    'id' => (int) $patient->getKey(),
                    'name' => trim((string) $patient->full_name),
                    'document' => trim((string) $patient->nro_identificacion),
                    'affiliate_id' => (int) $affiliate->getKey(),
                    'action' => $other !== null && $other['source'] !== PersonIdentitySource::Associate->value ? 'reubicar' : 'desvincular',
                    'other' => $other,
                    'hide_cases' => $hide,
                    'kept_cases' => $kept,
                ];
            }
        }

        return array_values($patients);
    }

    /**
     * La misma persona vigente en otra afiliación o en Nuevos Negocios (la de verdad, si esta se cargó
     * por error). Sus casos le pertenecen a ella y el paciente se le asocia.
     *
     * @return array{source: string, id: int, parent_id: ?int, parent_code: string, parent_name: string, status: string, where: string}|null
     */
    public static function otherActiveRecord(AffiliationDeletionSubject $subject, Affiliate|AffiliateCorporate $affiliate): ?array
    {
        $record = PersonIdentityRecord::fromModel($affiliate, $subject->affiliation);
        $ownSource = $subject->isCorporate() ? PersonIdentitySource::Corporate : PersonIdentitySource::Individual;

        foreach (PersonIdentityGuard::conflictsFor($record) as $conflict) {
            $existing = $conflict['existing'];

            if ($existing->source === $ownSource && $existing->parentId === $subject->id()) {
                continue;
            }

            return [
                'source' => $existing->source->value,
                'id' => (int) $existing->id,
                'parent_id' => $existing->parentId,
                'parent_code' => $existing->parentCode,
                'parent_name' => $existing->parentName,
                'status' => $existing->status,
                'where' => $existing->whereDescription(),
            ];
        }

        return null;
    }

    /**
     * @return array{key: string, label: string, passed: bool, detail: string, lines: list<string>}
     */
    private static function paymentsCheck(AffiliationDeletionSubject $subject): array
    {
        $counts = $subject->paymentsQuery()
            ->whereIn('status', self::BLOCKING_PAYMENT_STATUSES)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $lines = [];
        foreach ($counts as $status => $total) {
            $lines[] = $total.' '.($status === 'PENDIENTE' ? 'comprobante(s) de pago por revisar' : 'pago(s) '.mb_strtolower((string) $status).'(s)');
        }

        return self::check(
            'payments',
            'Sin pagos registrados',
            $lines === [],
            $lines === []
                ? 'No tiene pagos aprobados ni comprobantes por revisar.'
                : 'Tiene dinero registrado. Una afiliación con pagos no se elimina: désela de baja para que su historia contable siga cuadrando.',
            $lines,
        );
    }

    /**
     * @return array{key: string, label: string, passed: bool, detail: string, lines: list<string>}
     */
    private static function collectionsCheck(AffiliationDeletionSubject $subject): array
    {
        $paid = CollectionModel::query()
            ->where('affiliation_code', $subject->code())
            ->where('status', CollectionAdjuster::STATUS_PAID)
            ->count();

        return self::check(
            'collections',
            'Sin cuotas pagadas',
            $paid === 0,
            $paid === 0
                ? 'No tiene avisos de cobro pagados. Las cuotas por pagar que tenga se anulan y vuelven si se restaura.'
                : 'Tiene '.$paid.' aviso(s) de cobro pagado(s). No se puede eliminar: désela de baja.',
            [],
        );
    }

    /**
     * @return array{key: string, label: string, passed: bool, detail: string, lines: list<string>}
     */
    private static function accountingCheck(AffiliationDeletionSubject $subject): array
    {
        $code = $subject->code();
        $found = array_filter([
            'comisión(es)' => Commission::query()->where('affiliation_code', $code)->count(),
            'venta(s)' => Sale::query()->where('affiliation_code', $code)->count(),
            'conciliación(es) de crédito' => CreditReconciliation::query()->where('affiliation_code', $code)->count(),
            'registro(s) de cobranza anual' => AnnualCollection::query()->where('affiliation_code', $code)->count(),
        ]);

        $lines = [];
        foreach ($found as $label => $total) {
            $lines[] = $total.' '.$label;
        }

        return self::check(
            'accounting',
            'Sin comisiones, ventas ni conciliaciones',
            $lines === [],
            $lines === []
                ? 'No aparece en comisiones, ventas, conciliaciones ni cobranza anual.'
                : 'Ya forma parte de la contabilidad. Eliminarla descuadraría reportes emitidos: désela de baja.',
            $lines,
        );
    }

    /**
     * @return array{key: string, label: string, passed: bool, detail: string, lines: list<string>}
     */
    private static function renovationsCheck(AffiliationDeletionSubject $subject): array
    {
        $total = $subject->renovationsQuery()->count();

        return self::check(
            'renovations',
            'Sin renovaciones',
            $total === 0,
            $total === 0
                ? 'No tiene registros de renovación.'
                : 'Tiene '.$total.' registro(s) de renovación: ya cumplió un ciclo de vigencia. Désela de baja.',
            [],
        );
    }

    /**
     * @param  list<int>  $patientIds
     * @param  list<array<string, mixed>>  $patients
     * @return array{key: string, label: string, passed: bool, detail: string, lines: list<string>}
     */
    private static function openCasesCheck(array $patientIds, array $patients): array
    {
        $names = self::patientNames($patients);
        $lines = [];

        if ($patientIds !== []) {
            $open = TelemedicineCase::query()
                ->whereIn('telemedicine_patient_id', $patientIds)
                ->where(function ($query): void {
                    $query->whereNull('status')->orWhereNotIn('status', self::CLOSED_CASE_STATUSES);
                })
                ->orderBy('id')
                ->get(['id', 'code', 'status', 'telemedicine_patient_id']);

            foreach ($open as $case) {
                $lines[] = 'Caso '.$case->code.' de '.($names[(int) $case->telemedicine_patient_id] ?? 'un afiliado').' — '.mb_strtolower(trim((string) $case->status) ?: 'sin estatus');
            }
        }

        return self::check(
            'open_cases',
            'Sin casos clínicos en curso',
            $lines === [],
            $lines === []
                ? 'Ningún afiliado tiene un caso abierto. Los casos ya cerrados se ocultan con la afiliación y vuelven si se restaura.'
                : 'Hay casos clínicos en curso. Deben cerrarse (alta médica) en Telemedicina u Operaciones antes de eliminar la afiliación.',
            $lines,
        );
    }

    /**
     * @param  list<int>  $patientIds
     * @param  list<array<string, mixed>>  $patients
     * @return array{key: string, label: string, passed: bool, detail: string, lines: list<string>}
     */
    private static function pendingServicesCheck(array $patientIds, array $patients): array
    {
        $names = self::patientNames($patients);
        $lines = [];

        if ($patientIds !== []) {
            $services = OperationCoordinationService::query()
                ->whereIn('telemedicine_patient_id', $patientIds)
                ->orderBy('id')
                ->get(['id', 'status', 'telemedicine_patient_id', 'type_service']);

            foreach ($services as $service) {
                $status = mb_strtoupper(trim((string) $service->status));

                if (! in_array($status, self::CLOSED_SERVICE_STATUSES, true) && ! Str::contains($status, ['ANULAD', 'CANCELAD'])) {
                    $lines[] = 'Servicio #'.$service->getKey().(filled($service->type_service) ? ' ('.mb_strtolower((string) $service->type_service).')' : '')
                        .' de '.($names[(int) $service->telemedicine_patient_id] ?? 'un afiliado').' — '.mb_strtolower($status ?: 'sin estatus');
                }
            }

            $orders = OperationServiceOrder::query()
                ->whereIn('operation_coordination_service_id', $services->pluck('id')->all() ?: [0])
                ->where(function ($query): void {
                    $query->whereNull('status')->orWhereNotIn('status', self::CLOSED_ORDER_STATUSES);
                })
                ->get(['id', 'status', 'operation_coordination_service_id']);

            $byService = $services->keyBy('id');

            foreach ($orders as $order) {
                $patientId = (int) ($byService->get((int) $order->operation_coordination_service_id)?->telemedicine_patient_id ?? 0);
                $lines[] = 'Orden de servicio #'.$order->getKey().' de '.($names[$patientId] ?? 'un afiliado').' — '.mb_strtolower(trim((string) $order->status) ?: 'sin estatus');
            }
        }

        return self::check(
            'pending_services',
            'Sin servicios pendientes ni en gestión',
            $lines === [],
            $lines === []
                ? 'Ningún afiliado tiene servicios médicos ni órdenes de servicio abiertos.'
                : 'Hay servicios médicos u órdenes abiertos. Deben finalizarse en Operaciones antes de eliminar la afiliación.',
            $lines,
        );
    }

    /**
     * @return array{key: string, label: string, passed: bool, detail: string, lines: list<string>}
     */
    private static function codeCheck(AffiliationDeletionSubject $subject): array
    {
        $modelClass = $subject->affiliation::class;
        $shared = $modelClass::query()->withTrashed()
            ->where('code', $subject->code())
            ->whereKeyNot($subject->id())
            ->count();

        $otherClass = $subject->affiliation instanceof AffiliationCorporate ? \App\Models\Affiliation::class : AffiliationCorporate::class;
        $shared += $otherClass::query()->withTrashed()->where('code', $subject->code())->count();

        return self::check(
            'code',
            'Código único',
            $shared === 0 && trim($subject->code()) !== '',
            $shared === 0 && trim($subject->code()) !== ''
                ? 'Su código no lo usa otra afiliación.'
                : 'Otra afiliación usa el mismo código ('.$subject->code().'). La cobranza se liga por código y no se podría separar: revíselo con Sistemas.',
            [],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $patients
     * @return array<int, string>
     */
    private static function patientNames(array $patients): array
    {
        $names = [];

        foreach ($patients as $patient) {
            $names[(int) $patient['id']] = (string) $patient['name'];
        }

        return $names;
    }

    /**
     * @param  list<string>  $lines
     * @return array{key: string, label: string, passed: bool, detail: string, lines: list<string>}
     */
    private static function check(string $key, string $label, bool $passed, string $detail, array $lines): array
    {
        return ['key' => $key, 'label' => $label, 'passed' => $passed, 'detail' => $detail, 'lines' => $lines];
    }
}
