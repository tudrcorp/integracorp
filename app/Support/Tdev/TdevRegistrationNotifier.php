<?php

declare(strict_types=1);

namespace App\Support\Tdev;

use App\Jobs\NotifyAnalystsOfTdevRegistrationJob;

final class TdevRegistrationNotifier
{
    public static function notifyAgency(int $agencyId): void
    {
        NotifyAnalystsOfTdevRegistrationJob::dispatch(
            NotifyAnalystsOfTdevRegistrationJob::RECORD_AGENCY,
            $agencyId,
        )
            ->afterResponse()
            ->onConnection('sync');
    }

    public static function notifyAgent(int $agentId): void
    {
        NotifyAnalystsOfTdevRegistrationJob::dispatch(
            NotifyAnalystsOfTdevRegistrationJob::RECORD_AGENT,
            $agentId,
        )
            ->afterResponse()
            ->onConnection('sync');
    }
}
