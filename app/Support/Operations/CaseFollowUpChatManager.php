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

    /** Chat abierto desde el panel de Operaciones (comportamiento histórico). */
    public const CONTEXT_OPERATIONS = 'operations';

    /**
     * Chat abierto desde el panel de Telemedicina: el doctor de un proveedor ve los casos de su
     * proveedor; el doctor de TDG (sin proveedor) ve los casos sin proveedor.
     */
    public const CONTEXT_TELEMEDICINE = 'telemedicina';

    /**
     * @return list<string>
     */
    public static function contexts(): array
    {
        return [self::CONTEXT_OPERATIONS, self::CONTEXT_TELEMEDICINE];
    }

    public static function normalizeContext(?string $context): string
    {
        return in_array($context, self::contexts(), true) ? $context : self::CONTEXT_OPERATIONS;
    }

    /**
     * @return Builder<TelemedicineCase>
     */
    public static function followUpCasesQuery(?User $user = null, string $context = self::CONTEXT_OPERATIONS): Builder
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

        if (self::normalizeContext($context) === self::CONTEXT_TELEMEDICINE) {
            if (! $user instanceof User) {
                return $query->whereRaw('1 = 0');
            }

            return self::applyTelemedicineAudienceScope($query, $user);
        }

        OperationsSupplierScope::applyToQuery($query);

        return $query;
    }

    /**
     * @param  Builder<TelemedicineCase>  $query
     * @return Builder<TelemedicineCase>
     */
    private static function applyTelemedicineAudienceScope(Builder $query, User $user): Builder
    {
        if (filled($user->supplier_id)) {
            return $query->where('supplier_id', (int) $user->supplier_id);
        }

        if (self::isTdgTelemedicineDoctor($user)) {
            return $query->whereNull('supplier_id');
        }

        return $query;
    }

    private static function telemedicineAudienceAllowsCase(User $user, TelemedicineCase $case): bool
    {
        if (filled($user->supplier_id)) {
            return filled($case->supplier_id) && (int) $case->supplier_id === (int) $user->supplier_id;
        }

        if (self::isTdgTelemedicineDoctor($user)) {
            return blank($case->supplier_id);
        }

        return true;
    }

    /**
     * Doctor de TDG: tiene ficha de doctor, no pertenece a un proveedor y no es SUPERADMIN.
     */
    private static function isTdgTelemedicineDoctor(User $user): bool
    {
        return filled($user->doctor_id)
            && blank($user->supplier_id)
            && ! in_array('SUPERADMIN', $user->departament ?? [], true);
    }

    public static function canAccessCase(?User $user, TelemedicineCase $case, string $context = self::CONTEXT_OPERATIONS): bool
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

        if (self::normalizeContext($context) === self::CONTEXT_TELEMEDICINE) {
            return self::telemedicineAudienceAllowsCase($user, $case);
        }

        /** El proveedor sale del usuario evaluado (no de la sesión): también se usa desde la cola. */
        $supplierId = filled($user->supplier_id) ? (int) $user->supplier_id : null;

        if ($supplierId !== null && (int) $case->supplier_id !== $supplierId) {
            return false;
        }

        return true;
    }

    /**
     * @return Collection<int, TelemedicineCase>
     */
    public static function followUpCasesForChat(?User $user = null, string $context = self::CONTEXT_OPERATIONS): Collection
    {
        $user ??= Auth::user();

        if ($user === null) {
            return new Collection;
        }

        return self::followUpCasesQuery($user, $context)->get();
    }

    /**
     * Los últimos `$limit` mensajes del caso en orden cronológico (antes traía los más antiguos,
     * así que en un caso con más mensajes que el límite los nuevos no se veían).
     *
     * @return Collection<int, TelemedicineCaseMessage>
     */
    public static function messagesForCase(int $telemedicineCaseId, int $limit = 200): Collection
    {
        return TelemedicineCaseMessage::query()
            ->where('telemedicine_case_id', $telemedicineCaseId)
            ->with(['user:id,name,email'])
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get()
            ->reverse()
            ->values();
    }

    public static function countMessagesForCase(int $telemedicineCaseId): int
    {
        return TelemedicineCaseMessage::query()
            ->where('telemedicine_case_id', $telemedicineCaseId)
            ->count();
    }

    /**
     * Lista de casos del chat: acotada, con búsqueda en la base y solo las columnas que se pintan.
     * Devuelve `$limit + 1` filas como máximo para que quien llama sepa si hay más.
     *
     * @return Collection<int, TelemedicineCase>
     */
    public static function casesForChatList(User $user, string $context, string $search = '', int $limit = 30, array $excludeCaseIds = []): Collection
    {
        $query = self::chatListBaseQuery($user, $context, $search);

        if ($excludeCaseIds !== []) {
            $query->whereNotIn('telemedicine_cases.id', array_map('intval', $excludeCaseIds));
        }

        return $query->limit(max(1, $limit) + 1)->get();
    }

    /**
     * Los casos indicados (los que tienen mensajes sin leer), en el orden recibido y dentro del
     * alcance del usuario. No se pagina: son pocos y deben verse siempre primero.
     *
     * @param  list<int>  $orderedCaseIds
     * @return Collection<int, TelemedicineCase>
     */
    public static function casesByIdsForChatList(User $user, string $context, array $orderedCaseIds, string $search = ''): Collection
    {
        if ($orderedCaseIds === []) {
            return new Collection;
        }

        $position = array_flip(array_map('intval', $orderedCaseIds));

        return self::chatListBaseQuery($user, $context, $search)
            ->whereIn('telemedicine_cases.id', array_keys($position))
            ->get()
            ->sortBy(fn (TelemedicineCase $case): int => $position[(int) $case->id] ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * @return Builder<TelemedicineCase>
     */
    private static function chatListBaseQuery(User $user, string $context, string $search): Builder
    {
        $query = self::followUpCasesQuery($user, $context)
            ->setEagerLoads([])
            ->select([
                'telemedicine_cases.id',
                'telemedicine_cases.code',
                'telemedicine_cases.patient_name',
                'telemedicine_cases.managed_by',
                'telemedicine_cases.telemedicine_patient_id',
                'telemedicine_cases.telemedicine_doctor_id',
                'telemedicine_cases.updated_at',
            ])
            ->with(['telemedicinePatient:id,full_name']);

        $term = trim($search);

        if ($term !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

            $query->where(function (Builder $matches) use ($like): void {
                $matches->where('telemedicine_cases.code', 'like', $like)
                    ->orWhere('telemedicine_cases.patient_name', 'like', $like)
                    ->orWhere('telemedicine_cases.managed_by', 'like', $like)
                    ->orWhereHas('telemedicinePatient', fn (Builder $patient): Builder => $patient->where('full_name', 'like', $like))
                    ->orWhereHas('telemedicineDoctor', fn (Builder $doctor): Builder => $doctor->where('full_name', 'like', $like));
            });
        }

        return $query;
    }

    /**
     * Huella barata de la lista (cuántos casos y su última actividad): si no cambia, el ciclo de
     * actualización no necesita volver a pintar el chat.
     */
    public static function followUpCasesSignature(User $user, string $context): string
    {
        $row = self::followUpCasesQuery($user, $context)
            ->setEagerLoads([])
            ->reorder()
            ->toBase()
            ->selectRaw('COUNT(*) as total, MAX(updated_at) as last_activity')
            ->first();

        return ((int) ($row->total ?? 0)).'|'.((string) ($row->last_activity ?? ''));
    }

    public static function firstFollowUpCaseId(User $user, string $context): ?int
    {
        $id = self::followUpCasesQuery($user, $context)->setEagerLoads([])->value('telemedicine_cases.id');

        return $id !== null ? (int) $id : null;
    }

    public static function countFollowUpCases(User $user, string $context): int
    {
        return self::followUpCasesQuery($user, $context)->setEagerLoads([])->reorder()->count();
    }

    /**
     * Subconsulta con los ids de los casos del usuario: evita traer cientos de ids a PHP
     * para devolverlos en un `whereIn`.
     */
    private static function followUpCaseIdsSubquery(User $user, string $context): \Illuminate\Database\Query\Builder
    {
        return self::followUpCasesQuery($user, $context)
            ->setEagerLoads([])
            ->reorder()
            ->toBase()
            ->select('telemedicine_cases.id');
    }

    /**
     * No leídos por caso en UNA consulta (antes era una consulta por caso: 557 casos, 557 consultas).
     * Solo aparecen los casos con al menos un mensaje sin leer.
     *
     * @return array<int, int>
     */
    public static function unreadCountsForUser(User $user, string $context = self::CONTEXT_OPERATIONS): array
    {
        return array_map(
            static fn (array $summary): int => $summary['count'],
            self::unreadSummaryForUser($user, $context),
        );
    }

    /**
     * No leídos por caso con el id del último mensaje pendiente, ordenado del más reciente al más
     * antiguo. Una sola consulta.
     *
     * @return array<int, array{count: int, latest_id: int}>
     */
    public static function unreadSummaryForUser(User $user, string $context = self::CONTEXT_OPERATIONS): array
    {
        $rows = TelemedicineCaseMessage::query()
            ->toBase()
            ->leftJoin('telemedicine_case_chat_reads as r', function ($join) use ($user): void {
                $join->on('r.telemedicine_case_id', '=', 'telemedicine_case_messages.telemedicine_case_id')
                    ->where('r.user_id', '=', $user->id);
            })
            ->whereIn('telemedicine_case_messages.telemedicine_case_id', self::followUpCaseIdsSubquery($user, $context))
            ->where('telemedicine_case_messages.user_id', '!=', $user->id)
            ->where(function ($unread): void {
                $unread->whereNull('r.last_read_at')
                    ->orWhereColumn('telemedicine_case_messages.created_at', '>', 'r.last_read_at');
            })
            ->groupBy('telemedicine_case_messages.telemedicine_case_id')
            ->selectRaw('telemedicine_case_messages.telemedicine_case_id as case_id, COUNT(*) as unread, MAX(telemedicine_case_messages.id) as latest_id')
            ->orderByDesc('latest_id')
            ->get();

        $summary = [];

        foreach ($rows as $row) {
            $summary[(int) $row->case_id] = [
                'count' => (int) $row->unread,
                'latest_id' => (int) $row->latest_id,
            ];
        }

        return $summary;
    }

    /**
     * Primer mensaje que el usuario aún no ha leído en el caso (para el separador «Mensajes nuevos»).
     * Debe llamarse ANTES de marcar el caso como leído.
     */
    public static function firstUnreadMessageId(User $user, int $telemedicineCaseId): ?int
    {
        $lastReadAt = TelemedicineCaseChatRead::query()
            ->where('telemedicine_case_id', $telemedicineCaseId)
            ->where('user_id', $user->id)
            ->value('last_read_at');

        $id = TelemedicineCaseMessage::query()
            ->where('telemedicine_case_id', $telemedicineCaseId)
            ->where('user_id', '!=', $user->id)
            ->when($lastReadAt !== null, fn (Builder $query): Builder => $query->where('created_at', '>', $lastReadAt))
            ->min('id');

        return $id !== null ? (int) $id : null;
    }

    public static function latestMessageIdForCase(int $telemedicineCaseId): ?int
    {
        $latestId = TelemedicineCaseMessage::query()
            ->where('telemedicine_case_id', $telemedicineCaseId)
            ->max('id');

        return $latestId !== null ? (int) $latestId : null;
    }

    public static function sendMessage(TelemedicineCase $case, User $user, string $body, string $context = self::CONTEXT_OPERATIONS): TelemedicineCaseMessage
    {
        abort_unless(self::canAccessCase($user, $case, $context), 403);

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

    public static function totalUnreadCount(?User $user = null, string $context = self::CONTEXT_OPERATIONS): int
    {
        $user ??= Auth::user();

        if ($user === null) {
            return 0;
        }

        return array_sum(self::unreadCountsForUser($user, $context));
    }

    /**
     * No leídos de los casos indicados (0 para los que no tienen), en una sola consulta.
     *
     * @param  Collection<int, TelemedicineCase>  $cases
     * @return array<int, int>
     */
    public static function unreadCountsByCase(User $user, Collection $cases): array
    {
        if ($cases->isEmpty()) {
            return [];
        }

        $caseIds = $cases->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        $counts = TelemedicineCaseMessage::query()
            ->toBase()
            ->leftJoin('telemedicine_case_chat_reads as r', function ($join) use ($user): void {
                $join->on('r.telemedicine_case_id', '=', 'telemedicine_case_messages.telemedicine_case_id')
                    ->where('r.user_id', '=', $user->id);
            })
            ->whereIn('telemedicine_case_messages.telemedicine_case_id', $caseIds)
            ->where('telemedicine_case_messages.user_id', '!=', $user->id)
            ->where(function ($unread): void {
                $unread->whereNull('r.last_read_at')
                    ->orWhereColumn('telemedicine_case_messages.created_at', '>', 'r.last_read_at');
            })
            ->groupBy('telemedicine_case_messages.telemedicine_case_id')
            ->selectRaw('telemedicine_case_messages.telemedicine_case_id as case_id, COUNT(*) as unread')
            ->pluck('unread', 'case_id');

        $result = [];

        foreach ($caseIds as $caseId) {
            $result[$caseId] = (int) ($counts[$caseId] ?? 0);
        }

        return $result;
    }

    /**
     * @param  Collection<int, TelemedicineCase>  $cases
     * @return array<int, array{body: string, created_at: string, created_at_human: string, user_name: string|null}>
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
            $isSummary = $message->isConsultationSummary();

            $previews[(int) $caseId] = [
                'body' => $isSummary ? (string) ($message->meta['title'] ?? 'Resumen de consulta') : (string) $message->body,
                'created_at' => optional($message->created_at)->toIso8601String() ?? '',
                'created_at_human' => $message->created_at?->locale('es')->diffForHumans(short: true) ?? '',
                'user_name' => $isSummary
                    ? \App\Support\Telemedicine\ConsultationChatSummary::AUTHOR_LABEL
                    : ($message->user?->name ?? $message->user?->email),
            ];
        }

        return $previews;
    }

    public static function latestIncomingMessageIdForUser(?User $user = null, string $context = self::CONTEXT_OPERATIONS): ?int
    {
        $user ??= Auth::user();

        if ($user === null) {
            return null;
        }

        $latestId = self::incomingMessagesQuery($user, $context)->max('id');

        return $latestId !== null ? (int) $latestId : null;
    }

    /**
     * @return Collection<int, TelemedicineCaseMessage>
     */
    public static function incomingMessagesAfterId(?User $user, ?int $afterId, string $context = self::CONTEXT_OPERATIONS): Collection
    {
        $user ??= Auth::user();

        if ($user === null) {
            return new Collection;
        }

        return self::incomingMessagesQuery($user, $context)
            ->with(['user:id,name,email'])
            ->when(
                $afterId !== null,
                fn (Builder $query): Builder => $query->where('id', '>', $afterId),
            )
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Builder<TelemedicineCaseMessage>
     */
    private static function incomingMessagesQuery(User $user, string $context = self::CONTEXT_OPERATIONS): Builder
    {
        return TelemedicineCaseMessage::query()
            ->whereIn('telemedicine_case_id', self::followUpCaseIdsSubquery($user, $context))
            ->where('user_id', '!=', $user->id);
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
