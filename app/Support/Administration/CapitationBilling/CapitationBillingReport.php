<?php

declare(strict_types=1);

namespace App\Support\Administration\CapitationBilling;

use App\Enums\AdministrationReportType;
use App\Enums\OperationReportFormat;
use App\Models\CapitationBillingClosing;
use App\Models\Supplier;
use App\Support\Administration\Reports\AdministrationReportGenerator;
use App\Support\BcvOfficialRate;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Throwable;

/**
 * Entrada y salida de la pre-facturación de capitado en el Centro de reportes: qué
 * proveedores y meses se pueden elegir, la validación de lo que llega del formulario (el
 * job la repite) y la escritura del archivo en la carpeta del analista.
 */
final class CapitationBillingReport
{
    /** Meses hacia atrás que ofrece el selector. */
    public const MONTHS_BACK = 18;

    public static function userCan(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::CAPITATION_BILLING_REPORT);
    }

    /**
     * Proveedores de la red Outsourcing con alias de Integracorp (sin alias no se sabe qué
     * afiliaciones lo llevan).
     *
     * @return array<int, string>
     */
    public static function supplierOptions(): array
    {
        return Supplier::query()
            ->where('is_outsourcing_medical_department', true)
            ->whereNotNull('integracorp_alias')
            ->where('integracorp_alias', '<>', '')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->map(fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * @return array<string, string> `Y-m` => «Noviembre 2026»
     */
    public static function periodOptions(?CarbonImmutable $now = null): array
    {
        $month = ($now ?? CarbonImmutable::now())->startOfMonth();
        $options = [];

        for ($i = 0; $i <= self::MONTHS_BACK; $i++) {
            $options[$month->format('Y-m')] = ucfirst($month->locale('es')->translatedFormat('F Y'));
            $month = $month->subMonthNoOverflow();
        }

        return $options;
    }

    public static function supplier(mixed $id): Supplier
    {
        $supplier = is_numeric($id) ? Supplier::query()->find((int) $id) : null;

        if (! $supplier instanceof Supplier || ! $supplier->is_outsourcing_medical_department || blank($supplier->integracorp_alias)) {
            throw new InvalidArgumentException('Seleccione un proveedor de la red Outsourcing.');
        }

        return $supplier;
    }

    public static function period(mixed $value, ?CarbonImmutable $now = null): CarbonImmutable
    {
        $value = (string) $value;

        if (! array_key_exists($value, self::periodOptions($now))) {
            throw new InvalidArgumentException('Seleccione el mes del reporte.');
        }

        return CarbonImmutable::createFromFormat('!Y-m', $value)->startOfMonth();
    }

    /**
     * Tasa BCV sugerida: la oficial del día, guardada una hora para no consultar la API en
     * cada carga de la pantalla. Si la API no responde, el campo queda vacío y editable.
     */
    public static function suggestedBcvRate(): ?float
    {
        try {
            $cached = Cache::get('capitation-billing:bcv-rate');

            if (is_numeric($cached)) {
                return (float) $cached;
            }

            $rate = BcvOfficialRate::resolve();

            if ($rate !== null && $rate > 0) {
                Cache::put('capitation-billing:bcv-rate', $rate, now()->addHour());
            }

            return $rate;
        } catch (Throwable) {
            return null;
        }
    }

    public static function rate(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(',', '.', (string) $value);

        if (! is_numeric($normalized)) {
            throw new InvalidArgumentException('La tasa BCV debe ser un número.');
        }

        return (float) $normalized;
    }

    public static function closing(Supplier $supplier, CarbonImmutable $period): ?CapitationBillingClosing
    {
        return CapitationBillingClosing::query()
            ->where('supplier_id', $supplier->getKey())
            ->where('period', $period->startOfMonth()->toDateString())
            ->first();
    }

    /**
     * Genera el archivo en la carpeta del analista y devuelve cuántos afiliados trae.
     *
     * @param  list<array{concept: string, amount: float}>  $discounts
     */
    public static function store(OperationReportFormat $format, Supplier $supplier, CarbonImmutable $period, array $discounts, ?float $bcvRate, int $userId, string $filename): CapitationBillingSnapshot
    {
        $snapshot = CapitationBillingClosingService::snapshot($supplier, $period, $discounts, $bcvRate);

        AdministrationReportGenerator::storeWith($userId, $filename, function (string $path) use ($snapshot, $format): void {
            CapitationBillingWriter::write($snapshot, $format, $path);
        });

        return $snapshot;
    }

    public static function filename(OperationReportFormat $format, ?CarbonImmutable $now = null): string
    {
        return AdministrationReportGenerator::filename(AdministrationReportType::CapitationBilling, $format, $now);
    }

    /**
     * El PDF de cientos de afiliados tarda unos segundos: va siempre en cola. Excel y CSV se
     * escriben en menos de un segundo y se descargan al instante.
     */
    public static function shouldQueue(OperationReportFormat $format): bool
    {
        return $format === OperationReportFormat::Pdf;
    }
}
