<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineCaseMessage;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

final class CaseMessagingAuditLog
{
    /** @var array<int, string> */
    private const AUTHOR_TONES = ['sky', 'violet', 'emerald', 'amber', 'rose'];

    /**
     * @return array<string, mixed>
     */
    public static function viewContext(TelemedicineCase $case): array
    {
        $messages = self::messagesForCase($case);
        $viewerUserId = Auth::id();

        return [
            'caseCode' => filled($case->code) ? (string) $case->code : 'Caso #'.$case->id,
            'caseStatus' => (string) ($case->status ?? '—'),
            'managedBy' => filled($case->managed_by) ? mb_strtoupper((string) $case->managed_by) : '—',
            'patientName' => (string) ($case->patient_name ?: $case->telemedicinePatient?->full_name ?: 'Paciente'),
            'viewerUserId' => is_numeric($viewerUserId) ? (int) $viewerUserId : null,
            'stats' => self::stats($messages),
            'participants' => self::participants($messages),
            'messages' => self::messageEntries(
                $messages,
                is_numeric($viewerUserId) ? (int) $viewerUserId : null,
            ),
        ];
    }

    /**
     * @return Collection<int, TelemedicineCaseMessage>
     */
    public static function messagesForCase(TelemedicineCase $case): Collection
    {
        if ($case->relationLoaded('caseMessages')) {
            return $case->caseMessages
                ->sortBy('created_at')
                ->values();
        }

        return CaseFollowUpChatManager::messagesForCase($case->id, limit: 1000);
    }

    /**
     * @param  Collection<int, TelemedicineCaseMessage>  $messages
     * @return array<string, int|string|null>
     */
    private static function stats(Collection $messages): array
    {
        $first = $messages->first();
        $last = $messages->last();

        return [
            'total' => $messages->count(),
            'participants' => $messages->pluck('user_id')->filter()->unique()->count(),
            'first_message_at' => self::formatTimestamp($first?->created_at),
            'first_message_human' => self::humanTimestamp($first?->created_at),
            'last_message_at' => self::formatTimestamp($last?->created_at),
            'last_message_human' => self::humanTimestamp($last?->created_at),
            'last_author' => self::authorLabel($last?->user, $last?->user_id),
        ];
    }

    /**
     * @param  Collection<int, TelemedicineCaseMessage>  $messages
     * @return array<int, array<string, mixed>>
     */
    private static function participants(Collection $messages): array
    {
        /** @var array<int, array<string, mixed>> $participants */
        $participants = [];

        foreach ($messages as $message) {
            $userId = (int) ($message->user_id ?? 0);

            if ($userId === 0) {
                continue;
            }

            if (! array_key_exists($userId, $participants)) {
                $laneIndex = count($participants);

                $participants[$userId] = [
                    'id' => $userId,
                    'name' => self::authorLabel($message->user, $userId),
                    'email' => (string) ($message->user?->email ?? ''),
                    'initials' => self::initialsFromName($message->user?->name ?? $message->user?->email),
                    'message_count' => 0,
                    'lane' => $laneIndex % 2 === 0 ? 'left' : 'right',
                    'tone' => self::AUTHOR_TONES[$laneIndex % count(self::AUTHOR_TONES)],
                ];
            }

            $participants[$userId]['message_count']++;
        }

        return collect($participants)
            ->sortByDesc('message_count')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, TelemedicineCaseMessage>  $messages
     * @return array<int, array<string, mixed>>
     */
    private static function messageEntries(Collection $messages, ?int $viewerUserId = null): array
    {
        $entries = [];
        $previousDateLabel = null;
        /** @var array<int, int> $authorLanes */
        $authorLanes = [];
        $messageList = $messages->values()->all();
        $total = count($messageList);

        for ($index = 0; $index < $total; $index++) {
            $message = $messageList[$index];
            $previousMessage = $index > 0 ? $messageList[$index - 1] : null;
            $nextMessage = $index < ($total - 1) ? $messageList[$index + 1] : null;

            $createdAt = $message->created_at;
            $dateLabel = self::dateDividerLabel($createdAt);
            $showDateDivider = $dateLabel !== null && $dateLabel !== $previousDateLabel;
            $previousDateLabel = $dateLabel;

            $authorId = (int) ($message->user_id ?? 0);
            $previousAuthorId = (int) ($previousMessage?->user_id ?? 0);
            $nextAuthorId = (int) ($nextMessage?->user_id ?? 0);

            if ($authorId !== 0 && ! array_key_exists($authorId, $authorLanes)) {
                $authorLanes[$authorId] = count($authorLanes);
            }

            $laneIndex = $authorLanes[$authorId] ?? 0;
            $sameAuthorAsPrevious = $previousMessage !== null && $previousAuthorId === $authorId;
            $sameAuthorAsNext = $nextMessage !== null && $nextAuthorId === $authorId;

            $threadPosition = match (true) {
                $sameAuthorAsPrevious && $sameAuthorAsNext => 'middle',
                $sameAuthorAsPrevious && ! $sameAuthorAsNext => 'end',
                ! $sameAuthorAsPrevious && $sameAuthorAsNext => 'start',
                default => 'single',
            };

            $authorName = self::authorLabel($message->user, $message->user_id);
            $authorEmail = (string) ($message->user?->email ?? '');
            $body = trim((string) $message->body);
            $tone = self::AUTHOR_TONES[$laneIndex % count(self::AUTHOR_TONES)];

            $entries[] = [
                'id' => (int) $message->id,
                'body' => $body,
                'created_at' => self::formatTimestamp($createdAt),
                'created_at_time' => $createdAt instanceof Carbon ? $createdAt->format('H:i') : '—',
                'created_at_human' => self::humanTimestamp($createdAt),
                'date_label' => $dateLabel,
                'show_date_divider' => $showDateDivider,
                'author_id' => $authorId,
                'author_name' => $authorName,
                'author_email' => $authorEmail,
                'author_initials' => self::initialsFromName($message->user?->name ?? $message->user?->email),
                'align' => $laneIndex % 2 === 0 ? 'left' : 'right',
                'tone' => $tone,
                'thread_position' => $threadPosition,
                'show_author_header' => ! $sameAuthorAsPrevious,
                'show_avatar' => ! $sameAuthorAsPrevious,
                'is_viewer' => $viewerUserId !== null && $authorId === $viewerUserId,
                'search_blob' => mb_strtolower(implode(' ', array_filter([
                    (string) $message->id,
                    $authorName,
                    $authorEmail,
                    $body,
                    self::formatTimestamp($createdAt),
                ]))),
            ];
        }

        return $entries;
    }

    private static function authorLabel(?User $user, mixed $userId): string
    {
        if ($user !== null) {
            if (filled($user->name)) {
                return (string) $user->name;
            }

            if (filled($user->email)) {
                return (string) $user->email;
            }
        }

        return filled($userId) ? 'Usuario #'.$userId : 'Analista desconocido';
    }

    private static function initialsFromName(?string $name): string
    {
        if (blank($name)) {
            return '?';
        }

        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return '?';
        }

        if (count($parts) === 1) {
            return mb_strtoupper(mb_substr($parts[0], 0, 2));
        }

        return mb_strtoupper(mb_substr($parts[0], 0, 1).mb_substr($parts[array_key_last($parts)], 0, 1));
    }

    private static function formatTimestamp(mixed $value): ?string
    {
        if (! $value instanceof Carbon) {
            return null;
        }

        return $value->timezone(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    private static function humanTimestamp(mixed $value): ?string
    {
        if (! $value instanceof Carbon) {
            return null;
        }

        return $value->timezone(config('app.timezone'))->diffForHumans();
    }

    private static function dateDividerLabel(mixed $value): ?string
    {
        if (! $value instanceof Carbon) {
            return null;
        }

        $date = $value->timezone(config('app.timezone'));

        if ($date->isToday()) {
            return 'Hoy';
        }

        if ($date->isYesterday()) {
            return 'Ayer';
        }

        return $date->translatedFormat('d M Y');
    }
}
