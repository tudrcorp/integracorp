<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\HelpDesk;
use App\Models\User;
use Illuminate\Support\Carbon;

final class HelpdeskSla
{
    /**
     * Horas hasta primera respuesta / resolución por prioridad.
     *
     * @return array{first_response: int, resolution: int}
     */
    public static function hoursForPriority(?string $priority): array
    {
        return match (strtoupper(trim((string) $priority))) {
            'ALTA' => ['first_response' => 4, 'resolution' => 24],
            'BAJA' => ['first_response' => 48, 'resolution' => 120],
            default => ['first_response' => 24, 'resolution' => 72],
        };
    }

    public static function applyOnCreate(HelpDesk $ticket, ?Carbon $acceptedAt = null): void
    {
        $acceptedAt ??= Carbon::now((string) config('app.timezone'));
        $hours = self::hoursForPriority($ticket->priority);

        $ticket->terms_accepted_at = $acceptedAt;
        $ticket->first_response_due_at = $acceptedAt->copy()->addHours($hours['first_response']);
        $ticket->resolution_due_at = $acceptedAt->copy()->addHours($hours['resolution']);
    }

    public static function refreshDueDatesForPriority(HelpDesk $ticket): void
    {
        $anchor = $ticket->terms_accepted_at ?? $ticket->created_at ?? Carbon::now((string) config('app.timezone'));
        $hours = self::hoursForPriority($ticket->priority);

        $ticket->first_response_due_at = Carbon::parse($anchor)->addHours($hours['first_response']);
        $ticket->resolution_due_at = Carbon::parse($anchor)->addHours($hours['resolution']);
    }

    public static function markFirstResponseIfNeeded(HelpDesk $ticket, ?User $actor = null): void
    {
        if ($ticket->first_responded_at !== null) {
            return;
        }

        if ($actor instanceof User && HelpdeskTicketIdentity::isCreator($ticket, $actor)) {
            return;
        }

        $ticket->first_responded_at = Carbon::now((string) config('app.timezone'));
        self::refreshBreachFlag($ticket);
    }

    public static function markResolved(HelpDesk $ticket): void
    {
        $ticket->resolved_at = Carbon::now((string) config('app.timezone'));
        $ticket->cancelled_at = null;
        self::refreshBreachFlag($ticket);
    }

    public static function markCancelled(HelpDesk $ticket): void
    {
        $ticket->cancelled_at = Carbon::now((string) config('app.timezone'));
        $ticket->resolved_at = null;
        self::refreshBreachFlag($ticket);
    }

    public static function markReopened(HelpDesk $ticket): void
    {
        $ticket->resolved_at = null;
        $ticket->cancelled_at = null;
        $ticket->cancellation_reason = null;
        self::refreshBreachFlag($ticket);
    }

    public static function refreshBreachFlag(HelpDesk $ticket): void
    {
        if (self::isBreached($ticket)) {
            $ticket->sla_breached_at ??= Carbon::now((string) config('app.timezone'));

            return;
        }

        $ticket->sla_breached_at = null;
    }

    public static function isBreached(HelpDesk $ticket): bool
    {
        $now = Carbon::now((string) config('app.timezone'));

        if ($ticket->first_response_due_at !== null && $ticket->first_responded_at === null) {
            if ($now->greaterThan(Carbon::parse($ticket->first_response_due_at))) {
                return true;
            }
        }

        if (
            $ticket->resolution_due_at !== null
            && $ticket->resolved_at === null
            && $ticket->cancelled_at === null
            && ! in_array($ticket->status, HelpdeskTaskStatusOptions::terminalStatuses(), true)
        ) {
            if ($now->greaterThan(Carbon::parse($ticket->resolution_due_at))) {
                return true;
            }
        }

        return false;
    }

    public static function badgeLabel(HelpDesk $ticket): ?string
    {
        if (in_array($ticket->status, HelpdeskTaskStatusOptions::terminalStatuses(), true)) {
            return $ticket->sla_breached_at !== null ? 'SLA incumplido' : 'SLA cumplido';
        }

        if (self::isBreached($ticket)) {
            return 'SLA vencido';
        }

        $due = $ticket->first_responded_at === null
            ? $ticket->first_response_due_at
            : $ticket->resolution_due_at;

        if ($due === null) {
            return null;
        }

        $hoursLeft = Carbon::now((string) config('app.timezone'))->diffInHours(Carbon::parse($due), false);

        if ($hoursLeft <= 4) {
            return 'SLA en riesgo';
        }

        return 'SLA a tiempo';
    }

    public static function badgeColor(HelpDesk $ticket): string
    {
        return match (self::badgeLabel($ticket)) {
            'SLA vencido', 'SLA incumplido' => 'danger',
            'SLA en riesgo' => 'warning',
            'SLA cumplido', 'SLA a tiempo' => 'success',
            default => 'gray',
        };
    }
}
