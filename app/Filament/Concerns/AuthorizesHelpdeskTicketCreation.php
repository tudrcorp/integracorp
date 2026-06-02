<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Support\HelpdeskTicketCreationGate;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesHelpdeskTicketCreation
{
    public static function helpdeskEnforcesCreationQuota(): bool
    {
        return false;
    }

    public static function canSeeCreateTicketButton(): bool
    {
        if (! parent::canCreate()) {
            return false;
        }

        return HelpdeskTicketCreationGate::allowsCreation(
            enforceGroupQuota: static::helpdeskEnforcesCreationQuota(),
        )->shouldShowCreateTicketButton();
    }

    public static function canCreate(): bool
    {
        if (! parent::canCreate()) {
            return false;
        }

        return HelpdeskTicketCreationGate::allowsCreation(
            enforceGroupQuota: static::helpdeskEnforcesCreationQuota(),
        )->allowed;
    }

    public static function currentUserIsHelpdeskTicketCreator(Model $record): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        return trim((string) $record->getAttribute('created_by')) === trim((string) $user->name);
    }
}
