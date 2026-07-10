<?php

declare(strict_types=1);

namespace App\Support\AffiliateCard;

use App\Services\AffiliationBusinessDocumentsService;
use Illuminate\Support\Facades\File;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

final class IndividualAffiliationPlanQrGenerator
{
    private const CACHE_DIRECTORY = 'app/cache/affiliate-card-qr';

    public static function condicionadoUrlForPlanId(?int $planId): ?string
    {
        return AffiliationBusinessDocumentsService::condicionadoPublicUrlForPlanId($planId);
    }

    public static function qrAbsolutePathForPlanId(?int $planId): ?string
    {
        if ($planId === null) {
            return null;
        }

        $url = self::condicionadoUrlForPlanId($planId);

        if ($url === null) {
            return null;
        }

        $cachePath = storage_path(self::CACHE_DIRECTORY.'/plan-'.$planId.'.png');

        if (! is_file($cachePath)) {
            self::generateToPath($url, $cachePath);
        }

        return is_file($cachePath) ? $cachePath : null;
    }

    private static function generateToPath(string $url, string $absolutePath): void
    {
        File::ensureDirectoryExists(dirname($absolutePath));

        $qrBinary = QrCode::format('png')
            ->size(300)
            ->errorCorrection('M')
            ->margin(0)
            ->generate($url);

        File::put($absolutePath, $qrBinary);
    }
}
