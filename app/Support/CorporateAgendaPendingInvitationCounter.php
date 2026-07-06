<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CorporateAgendaInvitationStatus;
use App\Models\CorporateAgendaActivityParticipant;
use App\Models\RrhhColaborador;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CorporateAgendaPendingInvitationCounter
{
    public static function pendingInvitationCountForAuthenticatedUser(): int
    {
        return self::pendingInvitationCountForUser(Auth::user());
    }

    public static function pendingInvitationCountForUser(?User $user): int
    {
        $collaboratorIds = self::collaboratorIdsForUser($user);

        if ($collaboratorIds === []) {
            return 0;
        }

        return self::pendingInvitationsQuery($collaboratorIds)->count();
    }

    /**
     * @return array<string, int>
     */
    public static function pendingInvitationCountsByDateForAuthenticatedUser(Carbon $start, Carbon $end): array
    {
        return self::pendingInvitationCountsByDateForUser(Auth::user(), $start, $end);
    }

    /**
     * @return array<string, int>
     */
    public static function pendingInvitationCountsByDateForUser(?User $user, Carbon $start, Carbon $end): array
    {
        $collaboratorIds = self::collaboratorIdsForUser($user);

        if ($collaboratorIds === []) {
            return [];
        }

        return self::pendingInvitationsQuery($collaboratorIds)
            ->join('corporate_agenda_activities', 'corporate_agenda_activities.id', '=', 'corporate_agenda_activity_participants.activity_id')
            ->whereDate('corporate_agenda_activities.activity_date', '>=', $start->toDateString())
            ->whereDate('corporate_agenda_activities.activity_date', '<=', $end->toDateString())
            ->selectRaw('corporate_agenda_activities.activity_date as pending_date, COUNT(*) as pending_count')
            ->groupBy('corporate_agenda_activities.activity_date')
            ->pluck('pending_count', 'pending_date')
            ->mapWithKeys(fn (mixed $count, mixed $date): array => [
                Carbon::parse((string) $date)->toDateString() => (int) $count,
            ])
            ->all();
    }

    /**
     * @return array<int>
     */
    public static function collaboratorIdsForAuthenticatedUser(): array
    {
        return self::collaboratorIdsForUser(Auth::user());
    }

    /**
     * @return array<int>
     */
    public static function collaboratorIdsForUser(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $query = RrhhColaborador::query();
        $query->where(function (Builder $builder) use ($user): void {
            $builder->where('user_id', (int) $user->id);

            $email = is_string($user->email) ? trim($user->email) : '';
            if ($email !== '') {
                $builder->orWhere('emailCorporativo', $email)
                    ->orWhere('emailPersonal', $email);
            }

            $name = is_string($user->name) ? trim($user->name) : '';
            if ($name !== '') {
                $builder->orWhereRaw('LOWER(fullName) = ?', [Str::lower($name)]);
            }
        });

        return $query->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int>  $collaboratorIds
     * @return Builder<CorporateAgendaActivityParticipant>
     */
    private static function pendingInvitationsQuery(array $collaboratorIds): Builder
    {
        return CorporateAgendaActivityParticipant::query()
            ->whereIn('rrhh_colaborador_id', $collaboratorIds)
            ->where('invitation_status', CorporateAgendaInvitationStatus::Pending->value)
            ->whereHas('activity', function (Builder $activityQuery): void {
                $activityQuery->whereDate('activity_date', '>=', now()->toDateString());
            });
    }
}
