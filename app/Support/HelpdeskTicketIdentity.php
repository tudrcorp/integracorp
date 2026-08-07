<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\HelpDesk;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

final class HelpdeskTicketIdentity
{
    public static function isCreator(HelpDesk $record, ?Authenticatable $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        $creatorId = $record->created_by_user_id;

        if ($creatorId !== null) {
            return (int) $creatorId === (int) $user->getAuthIdentifier();
        }

        $createdBy = trim((string) $record->created_by);
        $userName = trim((string) $user->name);

        if ($createdBy === '' || $userName === '') {
            return false;
        }

        return mb_strtolower($createdBy) === mb_strtolower($userName);
    }

    public static function isLatestNoteAuthor(HelpDesk $ticket, User $user): bool
    {
        if ($ticket->latest_note_by_user_id !== null) {
            return (int) $ticket->latest_note_by_user_id === (int) $user->getAuthIdentifier();
        }

        return HelpdeskUnreadNoteTracker::actorsMatch(
            (string) ($ticket->latest_note_by ?? ''),
            $user->name,
        );
    }
}
