<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\HelpDesk;
use App\Models\HelpDeskNoteRead;
use App\Models\RrhhColaborador;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

final class HelpdeskUnreadNoteTracker
{
    /**
     * @var array<int, array<int, Carbon|string|null>>
     */
    private static array $readMapCache = [];

    private static ?bool $latestNoteColumnsAvailable = null;

    public static function unreadCountForAuthenticatedUser(): int
    {
        return self::unreadCountForUser(Auth::user());
    }

    public static function unreadCountForUser(?User $user): int
    {
        if ($user === null || ! self::trackingIsAvailable()) {
            return 0;
        }

        $count = 0;
        $readMap = self::readMapForUser($user);

        self::visibleTicketsQuery($user)
            ->select(self::ticketSelectColumns())
            ->orderByDesc('id')
            ->chunkById(200, function ($tickets) use ($user, $readMap, &$count): void {
                foreach ($tickets as $ticket) {
                    if (self::hasUnreadNotes($ticket, $user, $readMap)) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    public static function hasUnreadNotes(HelpDesk $ticket, ?User $user, ?array $readMap = null): bool
    {
        if ($user === null || ! self::trackingIsAvailable()) {
            return false;
        }

        $latestNote = self::latestNoteMeta($ticket);
        if ($latestNote === null) {
            return false;
        }

        if (self::actorsMatch($latestNote['by'], $user->name)) {
            return false;
        }

        $readMap ??= self::readMapForUser($user);
        $lastReadAt = $readMap[(int) $ticket->getKey()] ?? null;

        if ($lastReadAt === null) {
            return true;
        }

        return $latestNote['at']->gt(Carbon::parse($lastReadAt));
    }

    public static function markAsRead(HelpDesk $ticket, ?User $user, ?Carbon $readAt = null): void
    {
        if ($user === null || ! self::trackingIsAvailable()) {
            return;
        }

        $readAt ??= now();

        HelpDeskNoteRead::query()->updateOrCreate(
            [
                'help_desk_id' => (int) $ticket->getKey(),
                'user_id' => (int) $user->id,
            ],
            [
                'last_read_at' => $readAt,
            ],
        );

        unset(self::$readMapCache[(int) $user->id]);
    }

    public static function recordRowClass(HelpDesk $ticket, ?User $user = null): string
    {
        $user ??= Auth::user();

        return self::hasUnreadNotes($ticket, $user)
            ? 'fi-helpdesk-ta-has-unread-note'
            : '';
    }

    /**
     * @return array{at: CarbonImmutable, by: string}|null
     */
    public static function latestNoteMeta(HelpDesk $ticket): ?array
    {
        if (self::trackingIsAvailable() && $ticket->latest_note_at !== null) {
            $author = trim((string) ($ticket->latest_note_by ?? ''));

            return [
                'at' => CarbonImmutable::parse($ticket->latest_note_at)->timezone((string) config('app.timezone')),
                'by' => $author !== '' ? $author : 'Usuario',
            ];
        }

        return self::parseLatestNoteFromObservation((string) $ticket->observation, $ticket);
    }

    /**
     * @return array{at: CarbonImmutable, by: string}|null
     */
    private static function parseLatestNoteFromObservation(string $observation, HelpDesk $ticket): ?array
    {
        $trimmed = trim($observation);
        if ($trimmed === '') {
            return null;
        }

        $timezone = (string) config('app.timezone');
        $parts = preg_split('/\n\n(?=\[\d{2}\/\d{2}\/\d{4} \d{2}:\d{2} · )/', $trimmed);
        if ($parts === false || $parts === []) {
            return null;
        }

        $latest = null;

        foreach ($parts as $part) {
            $chunk = trim($part);
            if ($chunk === '') {
                continue;
            }

            if (preg_match('/^\[(?<meta>\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}) · (?<actor>[^\]]+)\]/', $chunk, $match) !== 1) {
                continue;
            }

            $at = CarbonImmutable::createFromFormat('d/m/Y H:i', $match['meta'], $timezone)
                ?: CarbonImmutable::parse($ticket->updated_at)->timezone($timezone);
            $actor = trim((string) $match['actor']);
            $actor = $actor !== '' ? $actor : 'Usuario';

            if ($latest === null || $at->gt($latest['at'])) {
                $latest = [
                    'at' => $at,
                    'by' => $actor,
                ];
            }
        }

        return $latest;
    }

    /**
     * @return array<int, Carbon|string|null>
     */
    private static function readMapForUser(User $user): array
    {
        $userId = (int) $user->id;

        if (isset(self::$readMapCache[$userId])) {
            return self::$readMapCache[$userId];
        }

        self::$readMapCache[$userId] = HelpDeskNoteRead::query()
            ->where('user_id', $userId)
            ->pluck('last_read_at', 'help_desk_id')
            ->all();

        return self::$readMapCache[$userId];
    }

    /**
     * @return Builder<HelpDesk>
     */
    private static function visibleTicketsQuery(User $user): Builder
    {
        $colaborador = RrhhColaborador::query()
            ->where('user_id', $user->id)
            ->first();

        return HelpDesk::query()
            ->where(function (Builder $query) use ($user, $colaborador): void {
                $query->where('created_by', $user->name);

                if ($colaborador !== null) {
                    $query->orWhereHas(
                        'rrhhColaboradores',
                        fn (Builder $sub): Builder => $sub->where('rrhh_colaboradors.id', $colaborador->id)
                    );
                }
            });
    }

    public static function actorsMatch(?string $left, ?string $right): bool
    {
        return mb_strtolower(trim((string) $left)) === mb_strtolower(trim((string) $right));
    }

    public static function trackingIsAvailable(): bool
    {
        if (self::$latestNoteColumnsAvailable === null) {
            self::$latestNoteColumnsAvailable = Schema::hasTable('help_desk_note_reads')
                && Schema::hasColumn('help_desks', 'latest_note_at')
                && Schema::hasColumn('help_desks', 'latest_note_by');
        }

        return self::$latestNoteColumnsAvailable;
    }

    /**
     * @return list<string>
     */
    private static function ticketSelectColumns(): array
    {
        $columns = ['id', 'observation', 'updated_at'];

        if (self::trackingIsAvailable()) {
            $columns[] = 'latest_note_at';
            $columns[] = 'latest_note_by';
        }

        return $columns;
    }
}
