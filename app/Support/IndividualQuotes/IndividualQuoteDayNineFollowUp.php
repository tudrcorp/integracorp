<?php

declare(strict_types=1);

namespace App\Support\IndividualQuotes;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class IndividualQuoteDayNineFollowUp
{
    public const FOLLOW_UP_DAYS = 9;

    public const ELIGIBLE_STATUS = IndividualQuoteFollowUp::ELIGIBLE_STATUS;

    public const BENEFITS_FLYER = 'imagenes-seguimiento-cotizaciones/flayer.pdf';

    public const BENEFITS_FLYER_FILENAME = 'flayer.pdf';

    /**
     * @return Collection<int, Collection<int, \App\Models\IndividualQuote>>
     */
    public static function groupedQuotesForDate(?Carbon $referenceDate = null): Collection
    {
        return IndividualQuoteFollowUp::groupedQuotesForDate(self::FOLLOW_UP_DAYS, $referenceDate);
    }

    public static function resolveAllyName(Collection $quotes): string
    {
        return IndividualQuoteFollowUp::resolveAllyName($quotes);
    }

    /**
     * @param  Collection<int, \App\Models\IndividualQuote>  $quotes
     */
    public static function formatClientNames(Collection $quotes): string
    {
        return IndividualQuoteFollowUp::formatClientNames($quotes);
    }

    /**
     * @param  Collection<int, \App\Models\IndividualQuote>  $quotes
     */
    public static function formatQuoteCodes(Collection $quotes): string
    {
        return IndividualQuoteFollowUp::formatQuoteCodes($quotes);
    }

    public static function benefitsFlyerUrl(): string
    {
        return IndividualQuoteFollowUp::publicAssetUrl(self::BENEFITS_FLYER);
    }

    /**
     * @param  Collection<int, \App\Models\IndividualQuote>  $quotes
     */
    public static function whatsappBody(Collection $quotes): string
    {
        $allyName = self::resolveAllyName($quotes);
        $clientNames = self::formatClientNames($quotes);
        $footer = IndividualQuoteFollowUp::trackingFooter(
            $quotes,
            'Le apoya en el proceso de seguimiento de las cotizaciones generadas en la fecha indicada.',
        );

        return <<<TEXT
        ¡Hola, *{$allyName}*! Por la cotización de {$clientNames}, a continuación recibirás un flyer de beneficios de Tu Doctor en Casa 🩺🏡.

        {$footer}
        TEXT;
    }

    public static function benefitsFlyerCaption(): string
    {
        return '📄 Flyer de beneficios — Tu Dr. En Casa';
    }
}
