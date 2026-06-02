<?php

declare(strict_types=1);

namespace App\Support;

use App\Filament\Business\Resources\Helpdesks\HelpdeskResource;

final class HelpdeskBusinessCreateTicketHeaderAction
{
    public static function make(): \Filament\Actions\Action
    {
        return HelpdeskCreateTicketHeaderAction::make(HelpdeskResource::class);
    }
}
