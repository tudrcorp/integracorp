<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicinePatient;
use App\Models\WhiteCompany;
use App\Support\PdfOpaqueImage;
use App\Support\WhiteCompanies\WhiteCompanyOwnership;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Marca (logo y paleta) con la que se imprimen los documentos clínicos de
 * telemedicina: informe médico, seguimiento, récipe y órdenes.
 *
 * Si el paciente pertenece a una afiliación de una empresa aliada, el documento
 * lleva el logo de la aliada y una paleta derivada de su color principal; si
 * no, la marca de Tu Dr En Casa de siempre. La aliada se resuelve por
 * WhiteCompanyOwnership (jerarquía comercial), nunca por `code_agency` plano.
 *
 * Se resuelve al generar el documento: un PDF ya emitido no cambia aunque la
 * aliada cambie después su logo o su color. Resolver la marca nunca puede
 * impedir emitir un documento clínico: ante cualquier fallo se usa la de TDEC.
 */
final class TelemedicineDocumentBrand
{
    public const DEFAULT_PRIMARY = '#00ADEF';

    public const DEFAULT_BORDER = '#0090c7';

    public const DEFAULT_DEEP = '#0c4a6e';

    public const DEFAULT_TINT = '#f0fdff';

    public const DEFAULT_LOGO = 'image/logoNewPdf.png';

    public const DEFAULT_NAME = 'Tu Doctor en Casa';

    /**
     * Contraste mínimo del color principal contra el blanco: es el que tiene el
     * cian de TDEC. Por debajo, el texto blanco de las cabeceras de tabla deja
     * de leerse y el color se oscurece hasta alcanzarlo.
     */
    public const MIN_CONTRAST_ON_WHITE = 2.5;

    public function __construct(
        public readonly ?WhiteCompany $company,
        public readonly string $primary,
        public readonly string $border,
        public readonly string $deep,
        public readonly string $tint,
        private readonly ?string $logoAbsolutePath,
    ) {}

    public static function default(): self
    {
        return new self(
            company: null,
            primary: self::DEFAULT_PRIMARY,
            border: self::DEFAULT_BORDER,
            deep: self::DEFAULT_DEEP,
            tint: self::DEFAULT_TINT,
            logoAbsolutePath: null,
        );
    }

    public static function fromCompany(?WhiteCompany $company): self
    {
        if ($company === null) {
            return self::default();
        }

        $base = self::normalizeHex($company->brand_primary_color);

        if ($base === null) {
            return new self(
                company: $company,
                primary: self::DEFAULT_PRIMARY,
                border: self::DEFAULT_BORDER,
                deep: self::DEFAULT_DEEP,
                tint: self::DEFAULT_TINT,
                logoAbsolutePath: $company->logoAbsolutePath(),
            );
        }

        $primary = self::readableOnWhite($base);

        return new self(
            company: $company,
            primary: $primary,
            border: self::mix($primary, '#000000', 0.17),
            deep: self::mix($primary, '#000000', 0.56),
            tint: self::mix($primary, '#ffffff', 0.94),
            logoAbsolutePath: $company->logoAbsolutePath(),
        );
    }

    /**
     * Marca del documento a partir de su payload (`telemedicine_patient_id` o,
     * en su defecto, la consulta).
     *
     * @param  array<string, mixed>  $data
     */
    public static function forData(array $data): self
    {
        try {
            return self::fromCompany(self::companyForPatient(self::patientFromData($data)));
        } catch (Throwable $exception) {
            Log::warning('TelemedicineDocumentBrand: no se pudo resolver la marca; se usa la de TDEC', [
                'telemedicine_patient_id' => $data['telemedicine_patient_id'] ?? null,
                'telemedicine_consultation_id' => $data['telemedicine_consultation_id'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            return self::default();
        }
    }

    public static function forPatient(?TelemedicinePatient $patient): self
    {
        try {
            return self::fromCompany(self::companyForPatient($patient));
        } catch (Throwable $exception) {
            Log::warning('TelemedicineDocumentBrand: no se pudo resolver la marca; se usa la de TDEC', [
                'telemedicine_patient_id' => $patient?->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return self::default();
        }
    }

    /**
     * Empresa aliada del paciente: por su afiliación individual o corporativa.
     */
    public static function companyForPatient(?TelemedicinePatient $patient): ?WhiteCompany
    {
        if ($patient === null) {
            return null;
        }

        $affiliationId = (int) ($patient->afilliation_id ?? 0);
        if ($affiliationId > 0) {
            $affiliation = Affiliation::query()->find($affiliationId);
            $company = $affiliation !== null ? WhiteCompanyOwnership::forAffiliation($affiliation) : null;

            if ($company !== null) {
                return $company;
            }
        }

        $corporateId = (int) ($patient->afilliation_corporate_id ?? 0);
        if ($corporateId > 0) {
            $corporate = AffiliationCorporate::query()->find($corporateId);

            return $corporate !== null ? WhiteCompanyOwnership::forAffiliation($corporate) : null;
        }

        return null;
    }

    public function isAllied(): bool
    {
        return $this->company !== null;
    }

    public function name(): string
    {
        $name = trim((string) ($this->company?->name ?? ''));

        return $name !== '' ? $name : self::DEFAULT_NAME;
    }

    /**
     * Logo opaco listo para DomPDF. Si la aliada no tiene logo legible, el de
     * TDEC: un documento clínico nunca sale sin logo.
     */
    public function logoDataUri(): string
    {
        if ($this->logoAbsolutePath !== null) {
            $logo = PdfOpaqueImage::fromPath($this->logoAbsolutePath);

            if ($logo !== '') {
                return $logo;
            }
        }

        return PdfOpaqueImage::fromPath(public_path(self::DEFAULT_LOGO));
    }

    /**
     * Si el logo impreso es el de la aliada (y no el de respaldo de TDEC).
     */
    public function printsAlliedLogo(): bool
    {
        return $this->logoAbsolutePath !== null
            && PdfOpaqueImage::fromPath($this->logoAbsolutePath) !== '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function patientFromData(array $data): ?TelemedicinePatient
    {
        $patientId = (int) ($data['telemedicine_patient_id'] ?? 0);

        if ($patientId < 1) {
            $consultationId = (int) ($data['telemedicine_consultation_id'] ?? 0);
            $patientId = $consultationId > 0
                ? (int) (TelemedicineConsultationPatient::query()->whereKey($consultationId)->value('telemedicine_patient_id') ?? 0)
                : 0;
        }

        if ($patientId < 1) {
            return null;
        }

        return TelemedicinePatient::query()
            ->select(['id', 'afilliation_id', 'afilliation_corporate_id'])
            ->find($patientId);
    }

    private static function normalizeHex(mixed $color): ?string
    {
        $normalized = strtolower(ltrim(trim((string) $color), '#'));

        if (preg_match('/^[0-9a-f]{3}$/', $normalized) === 1) {
            $normalized = $normalized[0].$normalized[0].$normalized[1].$normalized[1].$normalized[2].$normalized[2];
        }

        return preg_match('/^[0-9a-f]{6}$/', $normalized) === 1 ? '#'.$normalized : null;
    }

    /**
     * Oscurece el color lo justo para que el blanco se lea encima.
     */
    private static function readableOnWhite(string $hex): string
    {
        $color = $hex;

        for ($step = 1; self::contrastOnWhite($color) < self::MIN_CONTRAST_ON_WHITE && $step <= 20; $step++) {
            $color = self::mix($hex, '#000000', $step * 0.05);
        }

        return $color;
    }

    public static function contrastOnWhite(string $hex): float
    {
        return 1.05 / (self::luminance($hex) + 0.05);
    }

    private static function luminance(string $hex): float
    {
        [$r, $g, $b] = self::rgb($hex);

        $linear = static function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $linear($r) + 0.7152 * $linear($g) + 0.0722 * $linear($b);
    }

    /**
     * Mezcla `$hex` con `$target` en la proporción `$ratio` (0 = sin cambio).
     */
    private static function mix(string $hex, string $target, float $ratio): string
    {
        $ratio = max(0.0, min(1.0, $ratio));
        $from = self::rgb($hex);
        $to = self::rgb($target);

        $channels = array_map(
            static fn (int $a, int $b): string => str_pad(dechex((int) round($a + ($b - $a) * $ratio)), 2, '0', STR_PAD_LEFT),
            $from,
            $to,
        );

        return '#'.implode('', $channels);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }
}
