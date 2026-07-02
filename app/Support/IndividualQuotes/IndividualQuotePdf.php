<?php

namespace App\Support\IndividualQuotes;

use App\Models\IndividualQuote;

class IndividualQuotePdf
{
    public static function storagePath(IndividualQuote $quote): string
    {
        return public_path('storage/quotes/'.$quote->code.'.pdf');
    }

    public static function exists(IndividualQuote $quote): bool
    {
        return self::existsForCode($quote->code);
    }

    public static function existsForCode(?string $code): bool
    {
        return filled($code) && file_exists(public_path('storage/quotes/'.$code.'.pdf'));
    }

    public static function previewUrl(IndividualQuote $quote): ?string
    {
        return self::previewUrlForCode($quote->code);
    }

    public static function previewUrlForCode(?string $code): ?string
    {
        if (! self::existsForCode($code)) {
            return null;
        }

        return asset('storage/quotes/'.$code.'.pdf');
    }

    public static function downloadUrl(IndividualQuote $quote): ?string
    {
        return self::previewUrl($quote);
    }
}
