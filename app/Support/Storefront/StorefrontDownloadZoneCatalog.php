<?php

declare(strict_types=1);

namespace App\Support\Storefront;

use App\Models\DownloadZone;
use App\Models\DownloadZoneLike;
use App\Models\User;
use App\Models\Zone;
use App\Support\SecurityAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Carpetas y documentos activos de la zona de descarga para la PWA.
 *
 * @phpstan-type ContainerCard array{
 *     id: int,
 *     label: string,
 *     count: int,
 *     image_url: string|null,
 *     url: string
 * }
 * @phpstan-type DocumentPost array{
 *     id: int,
 *     description: string,
 *     zone: string,
 *     image_url: string|null,
 *     download_url: string,
 *     filename: string,
 *     liked: bool,
 *     likes: int
 * }
 */
final class StorefrontDownloadZoneCatalog
{
    /**
     * @return list<ContainerCard>
     */
    public static function containers(): array
    {
        $documents = DownloadZone::query()
            ->tap(fn (Builder $query) => self::constrainActive($query))
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'zone_id', 'image_icon', 'document', 'position']);

        if ($documents->isEmpty()) {
            return [];
        }

        $grouped = $documents->groupBy(
            fn (DownloadZone $document): string => (string) ((int) $document->zone_id),
        );
        $zoneIds = $grouped->keys()->map(fn (mixed $id): int => (int) $id)->all();

        $zones = Zone::query()
            ->whereIn('id', $zoneIds)
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'zone', 'code', 'position']);

        $containers = [];

        foreach ($zones as $zone) {
            $items = $grouped->get((string) ((int) $zone->id));

            if ($items === null || $items->isEmpty()) {
                continue;
            }

            $cover = $items->first(
                fn (DownloadZone $document): bool => self::resolveImageAbsolutePath($document) !== null,
            );

            $containers[] = [
                'id' => (int) $zone->id,
                'label' => self::zoneLabel($zone),
                'count' => $items->count(),
                'image_url' => $cover instanceof DownloadZone ? self::imageUrl($cover) : null,
                'url' => route('storefront.download-zone.feed', ['zone' => (int) $zone->id]),
            ];
        }

        return $containers;
    }

    /**
     * @return ContainerCard|null
     */
    public static function container(int $zoneId): ?array
    {
        if ($zoneId <= 0) {
            return null;
        }

        $count = DownloadZone::query()
            ->tap(fn (Builder $query) => self::constrainActive($query))
            ->where('zone_id', $zoneId)
            ->count();

        if ($count === 0) {
            return null;
        }

        $zone = Zone::query()->find($zoneId);

        if (! $zone instanceof Zone) {
            return null;
        }

        return [
            'id' => (int) $zone->id,
            'label' => self::zoneLabel($zone),
            'count' => $count,
            'image_url' => null,
            'url' => route('storefront.download-zone.feed', ['zone' => (int) $zone->id]),
        ];
    }

    /**
     * @return list<DocumentPost>
     */
    public static function posts(int $zoneId, ?User $user = null): array
    {
        if ($zoneId <= 0) {
            return [];
        }

        $records = DownloadZone::query()
            ->with(['zone:id,zone,code'])
            ->tap(fn (Builder $query) => self::constrainActive($query))
            ->where('zone_id', $zoneId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        if ($records->isEmpty()) {
            return [];
        }

        $ids = $records->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        $likedIds = [];

        if ($user instanceof User) {
            $likedIds = DownloadZoneLike::query()
                ->where('user_id', (int) $user->id)
                ->whereIn('download_zone_id', $ids)
                ->pluck('download_zone_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
        }

        $likedLookup = array_flip($likedIds);

        $likeCounts = DownloadZoneLike::query()
            ->whereIn('download_zone_id', $ids)
            ->selectRaw('download_zone_id, COUNT(*) as aggregate')
            ->groupBy('download_zone_id')
            ->pluck('aggregate', 'download_zone_id')
            ->mapWithKeys(fn (mixed $count, mixed $id): array => [(int) $id => (int) $count]);

        return $records
            ->map(fn (DownloadZone $record): array => self::post(
                $record,
                isset($likedLookup[(int) $record->id]),
                (int) $likeCounts->get((int) $record->id, 0),
            ))
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, liked: bool, likes: int}|null
     */
    public static function toggleLike(User $user, int $downloadZoneId): ?array
    {
        $record = self::findDownloadable($downloadZoneId);

        if (! $record instanceof DownloadZone) {
            return null;
        }

        return DB::transaction(function () use ($user, $record): array {
            $existing = DownloadZoneLike::query()
                ->where('user_id', (int) $user->id)
                ->where('download_zone_id', (int) $record->id)
                ->lockForUpdate()
                ->first();

            $liked = false;

            if ($existing instanceof DownloadZoneLike) {
                $existing->delete();
            } else {
                try {
                    DownloadZoneLike::query()->create([
                        'user_id' => (int) $user->id,
                        'download_zone_id' => (int) $record->id,
                    ]);
                    $liked = true;
                } catch (UniqueConstraintViolationException) {
                    $liked = true;
                }
            }

            $likes = DownloadZoneLike::query()
                ->where('download_zone_id', (int) $record->id)
                ->count();

            return [
                'id' => (int) $record->id,
                'liked' => $liked,
                'likes' => $likes,
            ];
        });
    }

    public static function findDownloadable(int $id): ?DownloadZone
    {
        $record = DownloadZone::query()->find($id);

        if (! $record instanceof DownloadZone) {
            return null;
        }

        if (! self::isActiveStatus($record->status)) {
            return null;
        }

        return $record;
    }

    public static function download(DownloadZone $record): ?StreamedResponse
    {
        $relative = self::relativePath($record->document);

        if ($relative === '') {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($relative)) {
            SecurityAudit::log('AUDIT_DOWNLOAD_ZONE_DOCUMENT_NOT_FOUND', 'storefront.download-zones.download', [
                'download_zone_id' => $record->id,
                'zone_id' => $record->zone_id,
                'document_path' => $relative,
            ]);

            Log::warning('Zona de descarga PWA: archivo solicitado no existe en disco.', [
                'download_zone_id' => $record->id,
                'zone_id' => $record->zone_id,
                'document_path' => $relative,
                'user_id' => auth()->id(),
            ]);

            return null;
        }

        $absolutePath = $disk->path($relative);

        SecurityAudit::log('AUDIT_DOWNLOAD_ZONE_DOCUMENT_DOWNLOADED', 'storefront.download-zones.download', [
            'download_zone_id' => $record->id,
            'zone_id' => $record->zone_id,
            'description' => $record->description,
            'document_path' => $relative,
            'file_size_bytes' => @filesize($absolutePath) ?: null,
        ]);

        Log::info('Zona de descarga PWA: documento descargado por el usuario.', [
            'download_zone_id' => $record->id,
            'zone_id' => $record->zone_id,
            'document_path' => $relative,
            'user_id' => auth()->id(),
        ]);

        return $disk->download($relative, basename($relative));
    }

    public static function imageUrl(DownloadZone $record): ?string
    {
        if (self::resolveImageAbsolutePath($record) === null) {
            return null;
        }

        return route('storefront.documents.download-zone-image', ['downloadZone' => (int) $record->id]);
    }

    public static function resolveImageAbsolutePath(DownloadZone $record): ?string
    {
        $candidates = [];

        foreach ([$record->image_icon, $record->document] as $index => $value) {
            $relative = self::relativePath($value);

            if ($relative === '') {
                continue;
            }

            if ($index === 1 && ! self::isImagePath($relative)) {
                continue;
            }

            $base = basename($relative);
            $candidates[] = $relative;
            $candidates[] = $base;
            $candidates[] = 'download-zone/'.$base;
        }

        $candidates = array_values(array_unique($candidates));

        foreach (['public', 'local'] as $diskName) {
            try {
                $disk = Storage::disk($diskName);
            } catch (Throwable) {
                continue;
            }

            foreach ($candidates as $path) {
                try {
                    if (! $disk->exists($path)) {
                        continue;
                    }

                    $absolute = $disk->path($path);
                } catch (Throwable) {
                    continue;
                }

                if (is_string($absolute) && is_file($absolute)) {
                    return $absolute;
                }
            }
        }

        return null;
    }

    private static function isImagePath(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg'], true);
    }

    /**
     * @param  Builder<DownloadZone>  $query
     * @return Builder<DownloadZone>
     */
    private static function constrainActive(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->whereNull('status')
                ->orWhere('status', '')
                ->orWhereRaw('UPPER(TRIM(status)) = ?', ['ACTIVO']);
        });
    }

    private static function isActiveStatus(mixed $status): bool
    {
        $normalized = strtoupper(trim((string) ($status ?? '')));

        return $normalized === '' || $normalized === 'ACTIVO';
    }

    /**
     * @return DocumentPost
     */
    private static function post(DownloadZone $record, bool $liked, int $likes): array
    {
        $relative = self::relativePath($record->document);
        $description = trim((string) $record->description);

        return [
            'id' => (int) $record->id,
            'description' => $description !== '' ? $description : 'Documento',
            'zone' => self::zoneLabel($record->zone),
            'image_url' => self::imageUrl($record),
            'download_url' => route('storefront.documents.download-zone', ['downloadZone' => (int) $record->id]),
            'filename' => $relative !== '' ? basename($relative) : 'documento',
            'liked' => $liked,
            'likes' => max(0, $likes),
        ];
    }

    private static function zoneLabel(?Zone $zone): string
    {
        if (! $zone instanceof Zone) {
            return 'Sin zona';
        }

        $name = trim((string) $zone->zone);

        if ($name !== '') {
            return $name;
        }

        $code = trim((string) $zone->code);

        return $code !== '' ? $code : 'Zona #'.$zone->id;
    }

    private static function relativePath(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value[0] ?? '';
        }

        return ltrim((string) $value, '/');
    }
}
