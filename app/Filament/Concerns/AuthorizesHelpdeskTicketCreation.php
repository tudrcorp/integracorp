<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Support\HelpdeskTicketCreationGate;
use App\Support\HelpdeskTicketIdentity;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesHelpdeskTicketCreation
{
    public static function canSeeCreateTicketButton(): bool
    {
        if (! parent::canCreate()) {
            return false;
        }

        return HelpdeskTicketCreationGate::allowsCreation()->shouldShowCreateTicketButton();
    }

    public static function canCreate(): bool
    {
        if (! parent::canCreate()) {
            return false;
        }

        return HelpdeskTicketCreationGate::allowsCreation()->allowed;
    }

    public static function currentUserIsHelpdeskTicketCreator(Model $record): bool
    {
        if (! $record instanceof \App\Models\HelpDesk) {
            return false;
        }

        return HelpdeskTicketIdentity::isCreator($record, auth()->user());
    }
}
