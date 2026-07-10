<?php

declare(strict_types=1);

namespace App\Support\Companies;

use App\Jobs\GenerateAndDeliverCompanyAssociateDocumentsAfterRegistrationJob;
use App\Jobs\NotifyAnalystsOfCompanyAssociateRegistrationJob;

final class CompanyAssociateRegistrationNotifier
{
    public static function notify(int $associateId): void
    {
        NotifyAnalystsOfCompanyAssociateRegistrationJob::dispatch($associateId)
            ->afterResponse()
            ->onConnection('sync');

        GenerateAndDeliverCompanyAssociateDocumentsAfterRegistrationJob::dispatch($associateId)
            ->afterResponse()
            ->onConnection('sync');
    }
}
