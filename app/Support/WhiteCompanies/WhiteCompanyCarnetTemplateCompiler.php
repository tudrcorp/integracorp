<?php

declare(strict_types=1);

namespace App\Support\WhiteCompanies;

use App\Models\WhiteCompany;
use App\Support\AffiliateCard\AffiliateCardPageLayout;
use App\Support\AffiliateCard\AffiliateCardTemplateBuilder;

final class WhiteCompanyCarnetTemplateCompiler
{
    public static function compiledPathFor(WhiteCompany $company): string
    {
        return storage_path(
            'app/affiliate-card/templates/white-company-'.$company->getKey().'/carnet-individual-affiliation.pdf'
        );
    }

    public static function compile(WhiteCompany $company): ?string
    {
        $image = $company->carnetTemplateImageAbsolutePath();

        if ($image === null) {
            return null;
        }

        return AffiliateCardTemplateBuilder::buildForTemplateKey(
            AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION,
            self::compiledPathFor($company),
            $image,
        );
    }

    public static function resolveOrCompile(WhiteCompany $company): ?string
    {
        $image = $company->carnetTemplateImageAbsolutePath();

        if ($image === null) {
            return null;
        }

        $output = self::compiledPathFor($company);

        if (! is_file($output) || filemtime($output) < filemtime($image)) {
            self::compile($company);
        }

        return is_file($output) ? $output : null;
    }
}
