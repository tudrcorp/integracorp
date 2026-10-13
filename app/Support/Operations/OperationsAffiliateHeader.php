<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Enums\CompanyAssociateStatus;
use App\Filament\Operations\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\CompanyAssociate;
use App\Models\TelemedicinePatient;
use App\Support\ClinicalEntitlements\ClinicalEntitlementWindow;
use App\Support\Companies\CompanyAssociateStatusManager;
use App\Support\PatientAge;
use App\Support\Telemedicine\TelemedicinePatientIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Encabezado de las fichas de afiliado del panel de Operaciones (individual,
 * corporativo y nuevos negocios): quién es, si está cubierto, hasta cuándo y si
 * ya es paciente de telemedicina, de un vistazo y sin abrir pestañas.
 *
 * @phpstan-type HeaderFact array{label: string, value: string, copy?: string}
 * @phpstan-type HeaderData array{
 *     eyebrow: string,
 *     name: string,
 *     initials: string,
 *     status: array{label: string, tone: string},
 *     tags: array<int, string>,
 *     facts: array<int, HeaderFact>,
 *     coverage: array{label: string, hint: ?string, tone: string}|null,
 *     patient: array{linked: bool, url: ?string},
 *     alert: array{title: string, meta: ?string, body: string}|null
 * }
 */
final class OperationsAffiliateHeader
{
    /** Días antes de la renovación en que la vigencia se marca en ámbar. */
    public const RENEWAL_WARNING_DAYS = 30;

    public static function forAffiliate(Affiliate $affiliate): Htmlable
    {
        $affiliation = $affiliate->affiliation;

        return self::render([
            'eyebrow' => 'Ficha del afiliado · Individual',
            'name' => self::name($affiliate->full_name),
            'initials' => self::initials($affiliate->full_name),
            'status' => self::affiliateStatus($affiliate->status),
            'tags' => self::tags($affiliate->relationship),
            'facts' => self::facts([
                ['label' => 'Cédula', 'value' => self::document($affiliate->nro_identificacion), 'copy' => self::text($affiliate->nro_identificacion)],
                ['label' => 'Edad', 'value' => PatientAge::labelOrNull($affiliate->birth_date, $affiliate->age)],
                ['label' => 'Sexo', 'value' => self::text($affiliate->sex)],
            ]),
            'coverage' => self::coverage($affiliation?->effective_date, $affiliate->status),
            'patient' => self::patient($affiliate->nro_identificacion),
            'alert' => null,
        ]);
    }

    public static function forAffiliateCorporate(AffiliateCorporate $affiliate): Htmlable
    {
        $affiliation = $affiliate->affiliationCorporate;
        $fullName = trim(((string) $affiliate->first_name).' '.((string) $affiliate->last_name));

        return self::render([
            'eyebrow' => 'Ficha del afiliado · Corporativo',
            'name' => self::name($fullName),
            'initials' => self::initials($fullName),
            'status' => self::affiliateStatus($affiliate->status),
            'tags' => self::tags($affiliate->relationship),
            'facts' => self::facts([
                ['label' => 'Cédula', 'value' => self::document($affiliate->nro_identificacion), 'copy' => self::text($affiliate->nro_identificacion)],
                ['label' => 'Edad', 'value' => PatientAge::labelOrNull($affiliate->birth_date, $affiliate->age)],
                ['label' => 'Sexo', 'value' => self::text($affiliate->sex)],
            ]),
            'coverage' => self::coverage($affiliation?->effective_date, $affiliate->status),
            'patient' => self::patient($affiliate->nro_identificacion),
            'alert' => null,
        ]);
    }

    public static function forCompanyAssociate(CompanyAssociate $associate): Htmlable
    {
        $status = CompanyAssociateStatusManager::resolveStatus($associate) ?? CompanyAssociateStatus::ActivoSinVaucherIls;

        $alert = null;

        if ($status === CompanyAssociateStatus::Anulado) {
            $alert = [
                'title' => 'Razón de anulación',
                'meta' => $associate->annulled_at ? 'Anulado el '.$associate->annulled_at->format('d/m/Y H:i') : null,
                'body' => filled($associate->annulment_reason) ? (string) $associate->annulment_reason : 'Sin razón registrada',
            ];
        }

        return self::render([
            'eyebrow' => 'Ficha del asociado · Nuevos Negocios',
            'name' => self::name($associate->full_name),
            'initials' => self::initials($associate->full_name),
            'status' => [
                'label' => mb_strtoupper($status->label()),
                'tone' => match ($status) {
                    CompanyAssociateStatus::Activo => 'success',
                    CompanyAssociateStatus::ActivoSinVaucherIls => 'warning',
                    CompanyAssociateStatus::Anulado => 'danger',
                },
            ],
            'tags' => [],
            'facts' => self::facts([
                ['label' => 'Cédula', 'value' => self::document($associate->identity_card), 'copy' => self::text($associate->identity_card)],
                ['label' => 'Edad', 'value' => PatientAge::labelOrNull($associate->birth_date, $associate->age)],
                ['label' => 'Sexo', 'value' => self::text($associate->sex)],
            ]),
            'coverage' => self::associateCoverage($associate, $status),
            'patient' => self::patient($associate->identity_card),
            'alert' => $alert,
        ]);
    }

    /**
     * Vigencia anual contada desde `effective_date`, como las renovaciones
     * (§4.4): se pinta en ámbar dentro de la ventana de renovación y en rojo si
     * ya pasó la fecha sin renovar.
     *
     * @return array{label: string, hint: ?string, tone: string}|null
     */
    public static function coverage(mixed $effectiveDate, ?string $status, ?CarbonImmutable $today = null): ?array
    {
        $start = ClinicalEntitlementWindow::parseToStartOfDay($effectiveDate);

        if ($start === null) {
            return null;
        }

        $today ??= CarbonImmutable::today();
        // Igual que AffiliationAffiliateFeeCalculator::renewalDateFromEffectiveDate(): al
        // aceptar la renovación se mueve effective_date, así que basta sumar un año.
        $renewal = $start->addYear();

        $label = 'Desde '.$start->format('d/m/Y').' · renueva '.$renewal->format('d/m/Y');

        if (strtoupper((string) $status) !== 'ACTIVO') {
            return ['label' => $label, 'hint' => null, 'tone' => 'gray'];
        }

        $days = (int) $today->diffInDays($renewal, false);

        return match (true) {
            $days < 0 => ['label' => $label, 'hint' => 'Renovación pendiente hace '.self::days(abs($days)), 'tone' => 'danger'],
            $days === 0 => ['label' => $label, 'hint' => 'Renueva hoy', 'tone' => 'warning'],
            $days <= self::RENEWAL_WARNING_DAYS => ['label' => $label, 'hint' => 'Renueva en '.self::days($days), 'tone' => 'warning'],
            default => ['label' => $label, 'hint' => null, 'tone' => 'success'],
        };
    }

    /**
     * @return array{label: string, hint: ?string, tone: string}|null
     */
    private static function associateCoverage(CompanyAssociate $associate, CompanyAssociateStatus $status): ?array
    {
        $start = $associate->registration_start_date;
        $end = $associate->registration_end_date;

        if ($start === null && $end === null) {
            return null;
        }

        $label = trim(($start ? 'Desde '.$start->format('d/m/Y') : '').($end ? ' · hasta '.$end->format('d/m/Y') : ''), ' ·');

        if ($status === CompanyAssociateStatus::Anulado || $end === null) {
            return ['label' => $label, 'hint' => null, 'tone' => 'gray'];
        }

        $days = (int) CarbonImmutable::today()->diffInDays(CarbonImmutable::instance($end)->startOfDay(), false);

        return match (true) {
            $days < 0 => ['label' => $label, 'hint' => 'Registro vencido hace '.self::days(abs($days)), 'tone' => 'danger'],
            $days <= 3 => ['label' => $label, 'hint' => $days === 0 ? 'Vence hoy' : 'Vence en '.self::days($days), 'tone' => 'warning'],
            default => ['label' => $label, 'hint' => null, 'tone' => 'success'],
        };
    }

    /**
     * @return array{linked: bool, url: ?string}
     */
    private static function patient(mixed $document): array
    {
        $normalized = TelemedicinePatientIdentity::normalizeDocument(is_scalar($document) ? (string) $document : null);

        $patient = $normalized === ''
            ? null
            : TelemedicinePatient::query()->where('nro_identificacion', $normalized)->first(['id']);

        $url = null;

        if ($patient !== null) {
            try {
                $url = TelemedicinePatientResource::canViewAny()
                    ? TelemedicinePatientResource::getUrl('view', ['record' => $patient])
                    : null;
            } catch (Throwable) {
                $url = null;
            }
        }

        return ['linked' => $patient !== null, 'url' => $url];
    }

    /**
     * @return array{label: string, tone: string}
     */
    private static function affiliateStatus(?string $status): array
    {
        $normalized = strtoupper(trim((string) $status));

        return [
            'label' => $normalized !== '' ? $normalized : 'SIN ESTADO',
            'tone' => match ($normalized) {
                'ACTIVO', 'ACTIVA' => 'success',
                'PENDIENTE' => 'warning',
                'INACTIVO', 'EXCLUIDO', 'EXCLUIDA' => 'danger',
                default => 'gray',
            },
        ];
    }

    /**
     * @param  array<int, array{label: string, value: ?string, copy?: ?string}|null>  $facts
     * @return array<int, array{label: string, value: string, copy?: ?string}>
     */
    private static function facts(array $facts): array
    {
        return array_values(array_filter(
            $facts,
            fn (?array $fact): bool => $fact !== null && filled($fact['value'] ?? null),
        ));
    }

    /**
     * @return array<int, string>
     */
    private static function tags(?string $relationship): array
    {
        $relationship = trim((string) $relationship);

        return $relationship !== '' ? [mb_convert_case(mb_strtolower($relationship), MB_CASE_TITLE)] : [];
    }

    private static function document(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return preg_match('/^\d/', $value) === 1 ? 'V-'.$value : $value;
    }

    private static function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private static function name(?string $name): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) $name));

        return $name !== '' ? mb_strtoupper($name) : 'SIN NOMBRE';
    }

    public static function initials(?string $name): string
    {
        $words = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return '?';
        }

        $first = mb_substr($words[0], 0, 1);
        $last = count($words) > 1 ? mb_substr($words[count($words) - 1], 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    private static function days(int $days): string
    {
        return $days === 1 ? '1 día' : $days.' días';
    }

    /**
     * @param  HeaderData  $header
     */
    private static function render(array $header): Htmlable
    {
        return new HtmlString(view('filament.operations.affiliates.view-header', ['header' => $header])->render());
    }
}
