<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\DownloadZone;
use App\Support\Storefront\StorefrontDownloadZoneCatalog;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorefrontDownloadZoneController extends Controller
{
    public function __invoke(int $downloadZone): StreamedResponse
    {
        $record = StorefrontDownloadZoneCatalog::findDownloadable($downloadZone);

        abort_unless($record instanceof DownloadZone, 404);

        $response = StorefrontDownloadZoneCatalog::download($record);

        abort_unless($response instanceof StreamedResponse, 404);

        return $response;
    }

    public function image(int $downloadZone): BinaryFileResponse
    {
        $record = StorefrontDownloadZoneCatalog::findDownloadable($downloadZone);

        abort_unless($record instanceof DownloadZone, 404);

        $path = StorefrontDownloadZoneCatalog::resolveImageAbsolutePath($record);

        abort_unless(is_string($path) && is_file($path), 404);

        return response()->file($path, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
