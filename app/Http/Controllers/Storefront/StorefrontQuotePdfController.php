<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\IndividualQuote;
use App\Support\Storefront\StorefrontQuotePdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StorefrontQuotePdfController extends Controller
{
    public function __invoke(string $code): BinaryFileResponse
    {
        $record = IndividualQuote::query()
            ->where('code', $code)
            ->first();

        abort_unless($record instanceof IndividualQuote, 404);
        abort_unless(StorefrontQuotePdf::ensure($record), 503);

        $filename = trim($code).'.pdf';

        return response()->download(StorefrontQuotePdf::path($code), $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
