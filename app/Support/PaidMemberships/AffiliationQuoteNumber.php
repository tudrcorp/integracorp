<?php

declare(strict_types=1);

namespace App\Support\PaidMemberships;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;

final class AffiliationQuoteNumber
{
    /**
     * Número de cotización para cobranzas individuales.
     * Si la cotización fue eliminada, usa el código desnormalizado o N/A.
     */
    public static function forIndividual(?Affiliation $affiliation): string
    {
        if ($affiliation === null) {
            return 'N/A';
        }

        $quoteCode = $affiliation->individual_quote?->code;
        if (is_string($quoteCode) && $quoteCode !== '') {
            return $quoteCode;
        }

        $storedCode = $affiliation->code_individual_quote;
        if (is_string($storedCode) && $storedCode !== '') {
            return $storedCode;
        }

        return 'N/A';
    }

    /**
     * Número de cotización para cobranzas corporativas.
     * Si la cotización fue eliminada, usa el código desnormalizado o N/A.
     */
    public static function forCorporate(?AffiliationCorporate $affiliation): string
    {
        if ($affiliation === null) {
            return 'N/A';
        }

        $quoteCode = $affiliation->corporate_quote?->code;
        if (is_string($quoteCode) && $quoteCode !== '') {
            return $quoteCode;
        }

        $storedCode = $affiliation->getAttribute('code_corporate_quote');
        if (is_string($storedCode) && $storedCode !== '') {
            return $storedCode;
        }

        return 'N/A';
    }
}
