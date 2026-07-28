<?php

declare(strict_types=1);

namespace App\Support\IndividualQuotes;

use App\Models\IndividualQuote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class IndividualQuoteDayTwelveFollowUp
{
    public const FOLLOW_UP_DAYS = 12;

    public const ELIGIBLE_STATUS = IndividualQuoteFollowUp::ELIGIBLE_STATUS;

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
        ¡Hola, *{$allyName}*! 😊 Te saludamos de Tu Doctor en Casa 🩺 🏡. Para ayudarte a mantener la excelente atención con tu cliente {$clientNames}, te recordamos que su cotización vence pronto. Si requiere una propuesta más flexible o a la medida para cerrar la venta, avísame y la adaptamos de inmediato.

        {$footer}
        TEXT;
    }

    /**
     * Mensaje de seguimiento directo al cliente cotizado (día 12).
     */
    public static function clientWhatsappBody(IndividualQuote $quote): string
    {
        $clientName = filled($quote->full_name)
            ? (string) $quote->full_name
            : 'Cliente';

        return <<<TEXT
        ¡Hola, *{$clientName}*! 😊 Te saludamos de Tu Doctor en Casa 🩺🏡 Tu cotización vence pronto, pero tu tranquilidad es lo primero. Si necesitas ajustar el presupuesto o ver un plan a tu medida, avísame y lo agendamos hoy mismo.
        TEXT;
    }
}
