<?php

declare(strict_types=1);

namespace App\Support\IndividualQuotes;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class IndividualQuoteDaySevenFollowUp
{
    public const FOLLOW_UP_DAYS = 7;

    public const ELIGIBLE_STATUS = IndividualQuoteFollowUp::ELIGIBLE_STATUS;

    public const IMAGE_PLAN_GUIDE = 'imagenes-seguimiento-cotizaciones/img1.png';

    public const IMAGE_PAYMENT_METHODS = 'imagenes-seguimiento-cotizaciones/img2.png';

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

    public static function planGuideImageUrl(): string
    {
        return IndividualQuoteFollowUp::publicAssetUrl(self::IMAGE_PLAN_GUIDE);
    }

    public static function paymentMethodsImageUrl(): string
    {
        return IndividualQuoteFollowUp::publicAssetUrl(self::IMAGE_PAYMENT_METHODS);
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
        ¡Hola, *{$allyName}*! Por la cotización de {$clientNames}, a continuación recibirás dos imágenes: cómo adquirir el plan y los métodos de pago en Tu Doctor en Casa 🩺🏡.

        {$footer}
        TEXT;
    }

    public static function planGuideImageCaption(): string
    {
        return '📋 Paso a paso para adquirir tu plan de salud — Tu Dr. En Casa';
    }

    public static function paymentMethodsImageCaption(): string
    {
        return '💳 Métodos de pago — Tu Dr. En Casa';
    }
}
