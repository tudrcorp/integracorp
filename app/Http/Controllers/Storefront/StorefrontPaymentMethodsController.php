<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Support\Storefront\StorefrontPaymentMethodsDocument;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StorefrontPaymentMethodsController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $path = StorefrontPaymentMethodsDocument::absolutePath();

        abort_unless(is_string($path) && is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.StorefrontPaymentMethodsDocument::DOWNLOAD_FILENAME.'"',
        ]);
    }
}
