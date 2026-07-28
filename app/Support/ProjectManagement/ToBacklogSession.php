<?php

declare(strict_types=1);

namespace App\Support\ProjectManagement;

use App\Models\HelpDesk;
use App\Support\HelpdeskTaskStatusOptions;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class ToBacklogSession
{
    public const SESSION_KEY = 'to_backlog';

    public const TTL_HOURS = 8;

    /**
     * @param  Collection<int, HelpDesk>  $records
     * @return array{added: int, skipped: int}
     */
    public static function addFromRecords(Collection $records): array
    {
        self::pruneIfExpired();

        /** @var array{expires_at: int|null, tickets: array<int, array{id: int, description: string}>} $payload */
        $payload = self::payload();
        $tickets = $payload['tickets'];
        $added = 0;
        $skipped = 0;

        foreach ($records as $record) {
            if (! $record instanceof HelpDesk) {
                $skipped++;

                continue;
            }

            if ($record->status !== HelpdeskTaskStatusOptions::STATUS_IN_PROGRESS) {
                $skipped++;

                continue;
            }

            $id = (int) $record->getKey();
            $tickets[$id] = [
                'id' => $id,
                'description' => self::normalizeDescription((string) $record->description),
            ];
            $added++;
        }

        if ($added > 0) {
            session([
                self::SESSION_KEY => [
                    'expires_at' => now()->addHours(self::TTL_HOURS)->getTimestamp(),
                    'tickets' => $tickets,
                ],
            ]);
        }

        return [
            'added' => $added,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return list<array{id: int, description: string}>
     */
    public static function tickets(): array
    {
        self::pruneIfExpired();

        return array_values(self::payload()['tickets']);
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::tickets() as $ticket) {
            $options[$ticket['id']] = self::label($ticket['id'], $ticket['description']);
        }

        return $options;
    }

    public static function remove(int $id): void
    {
        self::pruneIfExpired();

        $payload = self::payload();

        if (! array_key_exists($id, $payload['tickets'])) {
            return;
        }

        unset($payload['tickets'][$id]);

        if ($payload['tickets'] === []) {
            self::clear();

            return;
        }

        session([
            self::SESSION_KEY => [
                'expires_at' => $payload['expires_at'],
                'tickets' => $payload['tickets'],
            ],
        ]);
    }

    public static function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public static function label(int $id, string $description): string
    {
        $description = trim($description);

        if ($description === '') {
            return '#'.$id;
        }

        return '#'.$id.' — '.Str::limit($description, 100);
    }

    public static function pruneIfExpired(): void
    {
        $payload = session(self::SESSION_KEY);

        if (! is_array($payload)) {
            return;
        }

        $expiresAt = $payload['expires_at'] ?? null;

        if (! is_int($expiresAt) || $expiresAt <= now()->getTimestamp()) {
            self::clear();
        }
    }

    /**
     * @return array{expires_at: int|null, tickets: array<int, array{id: int, description: string}>}
     */
    private static function payload(): array
    {
        $payload = session(self::SESSION_KEY);

        if (! is_array($payload)) {
            return [
                'expires_at' => null,
                'tickets' => [],
            ];
        }

        $tickets = [];

        foreach (($payload['tickets'] ?? []) as $ticket) {
            if (! is_array($ticket) || ! isset($ticket['id'])) {
                continue;
            }

            $id = (int) $ticket['id'];
            $tickets[$id] = [
                'id' => $id,
                'description' => self::normalizeDescription((string) ($ticket['description'] ?? '')),
            ];
        }

        return [
            'expires_at' => isset($payload['expires_at']) ? (int) $payload['expires_at'] : null,
            'tickets' => $tickets,
        ];
    }

    private static function normalizeDescription(string $description): string
    {
        return Str::limit(trim(strip_tags($description)), 255, '');
    }
}
