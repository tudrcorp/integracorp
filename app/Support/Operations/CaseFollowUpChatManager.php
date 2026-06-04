<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineCaseChatRead;
use App\Models\TelemedicineCaseMessage;
use App\Models\User;
use App\Support\Filament\Operations\OperationsSupplierScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class CaseFollowUpChatManager
{
    public const FOLLOW_UP_STATUS = 'EN SEGUIMIENTO';

    /**
     * @return Builder<TelemedicineCase>
     */
    public static function followUpCasesQuery(?User $user = null): Builder
    {
        $user ??= Auth::user();

        $query = TelemedicineCase::query()
            ->where('status', self::FOLLOW_UP_STATUS)
            ->with([
                'telemedicineDoctor:id,full_name',
                'telemedicinePatient:id,full_name',
            ])
            ->latest('updated_at');

        if ($user !== null && in_array('ATENMEDI', $user->departament ?? [], true)) {
            $query->where('managed_by', 'ATENMEDI');
        }

        OperationsSupplierScope::applyToQuery($query);

        return $query;
    }

    public static function canAccessCase(?User $user, TelemedicineCase $case): bool
    {
        if ($user === null) {
            return false;
        }

        if ($case->status !== self::FOLLOW_UP_STATUS) {
            return false;
        }

        if (in_array('ATENMEDI', $user->departament ?? [], true) && $case->managed_by !== 'ATENMEDI') {
            return false;
        }

        $supplierId = OperationsSupplierScope::currentSupplierId();

        if ($supplierId !== null && (int) $case->supplier_id !== $supplierId) {
            return false;
        }

        return true;
    }

    /**
     * @return Collection<int, TelemedicineCase>
     */
    public static function followUpCasesForChat(?User $user = null): Collection
    {
        $user ??= Auth::user();

        if ($user === null) {
            return new Collection;
        }

        return self::followUpCasesQuery($user)->get();
    }

    /**
     * @return Collection<int, TelemedicineCaseMessage>
     */
    public static function messagesForCase(int $telemedicineCaseId, int $limit = 200): Collection
    {
        return TelemedicineCaseMessage::query()
            ->where('telemedicine_case_id', $telemedicineCaseId)
            ->with(['user:id,name,email'])
            ->oldest('created_at')
            ->limit($limit)
            ->get();
    }

    public static function latestMessageIdForCase(int $telemedicineCaseId): ?int
    {
        $latestId = TelemedicineCaseMessage::query()
            ->where('telemedicine_case_id', $telemedicineCaseId)
            ->max('id');

        return $latestId !== null ? (int) $latestId : null;
    }

    public static function sendMessage(TelemedicineCase $case, User $user, string $body): TelemedicineCaseMessage
    {
        abort_unless(self::canAccessCase($user, $case), 403);

        $message = TelemedicineCaseMessage::query()->create([
            'telemedicine_case_id' => $case->id,
            'user_id' => $user->id,
            'body' => trim($body),
        ]);

        $case->touch();

        self::markCaseAsRead($user, $case->id, $message->created_at ?? now());

        return $message->load(['user:id,name,email']);
    }

    public static function markCaseAsRead(User $user, int $telemedicineCaseId, ?Carbon $readAt = null): void
    {
        $readAt ??= now();

        TelemedicineCaseChatRead::query()->updateOrCreate(
            [
                'telemedicine_case_id' => $telemedicineCaseId,
                'user_id' => $user->id,
            ],
            [
                'last_read_at' => $readAt,
            ]
        );
    }

    public static function unreadCountForCase(User $user, int $telemedicineCaseId): int
    {
        $lastReadAt = TelemedicineCaseChatRead::query()
            ->where('telemedicine_case_id', $telemedicineCaseId)
            ->where('user_id', $user->id)
            ->value('last_read_at');

        return TelemedicineCaseMessage::query()
            ->where('telemedicine_case_id', $telemedicineCaseId)
            ->when(
                $lastReadAt !== null,
                fn (Builder $query): Builder => $query->where('created_at', '>', $lastReadAt),
                fn (Builder $query): Builder => $query
            )
            ->where('user_id', '!=', $user->id)
            ->count();
    }

    public static function totalUnreadCount(?User $user = null): int
    {
        $user ??= Auth::user();

        if ($user === null) {
            return 0;
        }

        $caseIds = self::followUpCasesQuery($user)->pluck('id');

        if ($caseIds->isEmpty()) {
            return 0;
        }

        $reads = TelemedicineCaseChatRead::query()
            ->where('user_id', $user->id)
            ->whereIn('telemedicine_case_id', $caseIds)
            ->pluck('last_read_at', 'telemedicine_case_id');

        $total = 0;

        foreach ($caseIds as $caseId) {
            $lastReadAt = $reads->get($caseId);

            $total += TelemedicineCaseMessage::query()
                ->where('telemedicine_case_id', $caseId)
                ->when(
                    $lastReadAt !== null,
                    fn (Builder $query): Builder => $query->where('created_at', '>', $lastReadAt)
                )
                ->where('user_id', '!=', $user->id)
                ->count();
        }

        return $total;
    }

    /**
     * @param  Collection<int, TelemedicineCase>  $cases
     * @return array<int, int>
     */
    public static function unreadCountsByCase(User $user, Collection $cases): array
    {
        if ($cases->isEmpty()) {
            return [];
        }

        $caseIds = $cases->pluck('id');
        $reads = TelemedicineCaseChatRead::query()
            ->where('user_id', $user->id)
            ->whereIn('telemedicine_case_id', $caseIds)
            ->pluck('last_read_at', 'telemedicine_case_id');

        $counts = [];

        foreach ($caseIds as $caseId) {
            $lastReadAt = $reads->get($caseId);

            $counts[(int) $caseId] = TelemedicineCaseMessage::query()
                ->where('telemedicine_case_id', $caseId)
                ->when(
                    $lastReadAt !== null,
                    fn (Builder $query): Builder => $query->where('created_at', '>', $lastReadAt)
                )
                ->where('user_id', '!=', $user->id)
                ->count();
        }

        return $counts;
    }

    /**
     * @param  Collection<int, TelemedicineCase>  $cases
     * @return array<int, array{body: string, created_at: string, user_name: string|null}>
     */
    public static function latestMessagePreviewByCase(Collection $cases): array
    {
        if ($cases->isEmpty()) {
            return [];
        }

        $caseIds = $cases->pluck('id');

        $latestIds = TelemedicineCaseMessage::query()
            ->select('telemedicine_case_id', DB::raw('MAX(id) as latest_id'))
            ->whereIn('telemedicine_case_id', $caseIds)
            ->groupBy('telemedicine_case_id')
            ->pluck('latest_id', 'telemedicine_case_id');

        if ($latestIds->isEmpty()) {
            return [];
        }

        $messages = TelemedicineCaseMessage::query()
            ->whereIn('id', $latestIds->values())
            ->with(['user:id,name,email'])
            ->get()
            ->keyBy('telemedicine_case_id');

        $previews = [];

        foreach ($messages as $caseId => $message) {
            $previews[(int) $caseId] = [
                'body' => (string) $message->body,
                'created_at' => optional($message->created_at)->toIso8601String() ?? '',
                'user_name' => $message->user?->name ?? $message->user?->email,
            ];
        }

        return $previews;
    }

    public static function managedByBadgeColor(?string $managedBy): string
    {
        return match (mb_strtoupper((string) $managedBy)) {
            'ATENMEDI' => 'success',
            'TDG' => 'info',
            default => 'gray',
        };
    }
}
