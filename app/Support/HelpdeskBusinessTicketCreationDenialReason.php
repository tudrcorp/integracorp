<?php

declare(strict_types=1);

namespace App\Support;

final class HelpdeskBusinessTicketCreationDenialReason
{
    public const UNAUTHENTICATED = 'unauthenticated';

    public const MISSING_RRHH = 'missing_rrhh';

    public const MISSING_GROUP = 'missing_group';

    public const QUOTA_NOT_SET = 'quota_not_set';

    public const QUOTA_EXHAUSTED = 'quota_exhausted';
}
