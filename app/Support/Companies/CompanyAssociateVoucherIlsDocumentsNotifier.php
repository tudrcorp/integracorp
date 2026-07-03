<?php

declare(strict_types=1);

namespace App\Support\Companies;

use App\Jobs\GenerateCompanyAssociateDocumentsAfterVoucherJob;

final class CompanyAssociateVoucherIlsDocumentsNotifier
{
    public static function queueGenerationAfterVoucherSave(int $associateId, int $requestedByUserId): void
    {
        GenerateCompanyAssociateDocumentsAfterVoucherJob::dispatch($associateId, $requestedByUserId);
    }
}
