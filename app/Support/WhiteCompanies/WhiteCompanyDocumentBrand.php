<?php

declare(strict_types=1);

namespace App\Support\WhiteCompanies;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\WhiteCompany;
use App\Models\WhiteCompanyPlanLabel;
use App\Services\AffiliationBusinessDocumentsService;
use App\Support\CreditReconciliations\CreditReconciliationAffiliationSnapshot;
use Illuminate\Support\Facades\Storage;

final class WhiteCompanyDocumentBrand
{
    public function __construct(
        public readonly ?WhiteCompany $company,
        public readonly string $primaryColor,
        public readonly ?string $logoAbsolutePath,
        public readonly ?string $certificateSignatureAbsolutePath,
        public readonly ?string $carnetTemplateImageAbsolutePath,
        public readonly ?string $carnetCompiledPdfAbsolutePath,
    ) {}

    public static function tdec(): self
    {
        return new self(
            company: null,
            primaryColor: WhiteCompanyBrandColor::DEFAULT,
            logoAbsolutePath: null,
            certificateSignatureAbsolutePath: null,
            carnetTemplateImageAbsolutePath: null,
            carnetCompiledPdfAbsolutePath: null,
        );
    }

    public static function fromCompany(?WhiteCompany $company): self
    {
        if ($company === null) {
            return self::tdec();
        }

        if ($company->exists) {
            $company->loadMissing(['planDocuments', 'planLabels']);
        } else {
            if (! $company->relationLoaded('planDocuments')) {
                $company->setRelation('planDocuments', collect());
            }

            if (! $company->relationLoaded('planLabels')) {
                $company->setRelation('planLabels', collect());
            }
        }

        return new self(
            company: $company,
            primaryColor: WhiteCompanyBrandColor::resolve($company->brand_primary_color),
            logoAbsolutePath: $company->logoAbsolutePath(),
            certificateSignatureAbsolutePath: $company->certificateSignatureAbsolutePath(),
            carnetTemplateImageAbsolutePath: $company->carnetTemplateImageAbsolutePath(),
            carnetCompiledPdfAbsolutePath: WhiteCompanyCarnetTemplateCompiler::resolveOrCompile($company),
        );
    }

    public static function forCorporate(AffiliationCorporate $record): self
    {
        $company = CreditReconciliationAffiliationSnapshot::whiteCompanyForAgencyCode($record->code_agency);
        $company?->loadMissing(['planDocuments', 'planLabels']);

        return self::fromCompany($company);
    }

    public static function forAffiliation(Affiliation $record): self
    {
        $company = null;

        if ($record->relationLoaded('whiteCompanyUser') && filled($record->whiteCompanyUser?->white_company_id)) {
            $company = WhiteCompany::query()
                ->with(['planDocuments', 'planLabels'])
                ->find($record->whiteCompanyUser->white_company_id);
        }

        if ($company === null) {
            $company = CreditReconciliationAffiliationSnapshot::whiteCompanyForAgencyCode($record->code_agency);
            $company?->loadMissing(['planDocuments', 'planLabels']);
        }

        return self::fromCompany($company);
    }

    public function isAllied(): bool
    {
        return $this->company !== null;
    }

    public function companyName(): string
    {
        if ($this->company === null) {
            return '';
        }

        return trim((string) $this->company->name);
    }

    public function logoDataUri(): string
    {
        return self::fileToDataUri($this->logoAbsolutePath);
    }

    public function signatureDataUri(): string
    {
        return self::fileToDataUri($this->certificateSignatureAbsolutePath);
    }

    private static function fileToDataUri(?string $absolutePath): string
    {
        if ($absolutePath === null || ! is_file($absolutePath)) {
            return '';
        }

        $contents = file_get_contents($absolutePath);

        if ($contents === false || $contents === '') {
            return '';
        }

        $mime = mime_content_type($absolutePath) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    public function condicionadoAbsolutePath(?int $planId): ?string
    {
        if ($this->company !== null && $planId !== null) {
            $relative = $this->company->condicionadoPathForPlan($planId);

            if (filled($relative)) {
                $absolute = Storage::disk('public')->path((string) $relative);

                if (is_file($absolute)) {
                    return $absolute;
                }
            }
        }

        return AffiliationBusinessDocumentsService::condicionadoAbsolutePathForPlanId($planId);
    }

    public function planDisplayName(?int $planId, string $fallback): string
    {
        $name = trim((string) ($this->labelForPlan($planId)?->display_name ?? ''));

        return $name !== '' ? $name : $fallback;
    }

    public function planShortLabel(?int $planId, string $fallback): string
    {
        $label = $this->labelForPlan($planId);
        $short = trim((string) ($label?->short_label ?? ''));

        if ($short !== '') {
            return mb_strtoupper($short);
        }

        $name = trim((string) ($label?->display_name ?? ''));

        if ($name === '') {
            return $fallback;
        }

        $derived = preg_replace('/^plan\s+/iu', '', $name) ?? $name;

        return mb_strtoupper(trim($derived));
    }

    private function labelForPlan(?int $planId): ?WhiteCompanyPlanLabel
    {
        if ($this->company === null || $planId === null) {
            return null;
        }

        return $this->company->planLabelForPlan($planId);
    }
}
