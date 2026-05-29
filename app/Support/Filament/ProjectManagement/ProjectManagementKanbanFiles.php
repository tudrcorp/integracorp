<?php

declare(strict_types=1);

namespace App\Support\Filament\ProjectManagement;

use App\Filament\Projects\Resources\ProjectManagement\Activities\ActivityResource;
use App\Models\ProjectManagement\Activity;
use App\Models\ProjectManagement\Document;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

final class ProjectManagementKanbanFiles
{
    /**
     * @param  Collection<int, Activity>  $activities
     * @return array{
     *     total_count: int,
     *     counts: array{all: int, images: int, documents: int},
     *     files: array<int, array{
     *         id: int,
     *         name: string,
     *         extension: string,
     *         size_label: string,
     *         category: string,
     *         download_url: string,
     *         activity_view_url: string,
     *         uploaded_at: string,
     *         activity_title: string,
     *         project_name: string,
     *         project_color: string,
     *         uploader_name: string,
     *         assignees: array{
     *             visible_members: array<int, array{id: int, name: string, initials: string, avatar_url: string|null}>,
     *             overflow_count: int,
     *             total_count: int
     *         }
     *     }>
     * }
     */
    public static function build(
        Collection $activities,
        string $category = 'all',
        string $sort = 'newest',
        string $search = '',
    ): array {
        if ($activities->isEmpty()) {
            return self::emptyPayload();
        }

        $activityIds = $activities->pluck('id')->all();
        $activitiesById = $activities->keyBy('id');

        $documents = Document::query()
            ->where('documentable_type', Activity::class)
            ->whereIn('documentable_id', $activityIds)
            ->with(['uploader:id,name'])
            ->get();

        $files = $documents
            ->map(function (Document $document) use ($activitiesById): ?array {
                /** @var Activity|null $activity */
                $activity = $activitiesById->get($document->documentable_id);

                if ($activity === null) {
                    return null;
                }

                $assignment = ProjectManagementActivityAssignmentDisplay::for($activity);
                $project = $activity->project;
                $fileCategory = self::resolveCategory($document->file_type, $document->name);

                return [
                    'id' => $document->id,
                    'name' => $document->name,
                    'extension' => self::resolveExtension($document->file_type, $document->name),
                    'size_label' => self::formatFileSize($document->file_size),
                    'category' => $fileCategory,
                    'download_url' => Storage::disk('public')->url($document->file_path),
                    'activity_view_url' => ActivityResource::getUrl('view', ['record' => $activity], panel: 'projects'),
                    'uploaded_at' => $document->created_at instanceof Carbon
                        ? $document->created_at->translatedFormat('M j, Y, H:i')
                        : '—',
                    'uploaded_at_sort' => $document->created_at?->format('Y-m-d H:i:s') ?? '',
                    'activity_title' => $activity->title,
                    'project_name' => $project?->name ?? 'Sin proyecto',
                    'project_color' => $project !== null
                        ? ProjectManagementProjectTable::resolveColor($project)
                        : '#6366f1',
                    'uploader_name' => $document->uploader?->name ?? 'Usuario',
                    'assignees' => [
                        'visible_members' => $assignment['visible_members'],
                        'overflow_count' => $assignment['overflow_count'],
                        'total_count' => $assignment['total_count'],
                    ],
                ];
            })
            ->filter()
            ->values();

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $files = $files->filter(function (array $file) use ($needle): bool {
                return str_contains(mb_strtolower($file['name']), $needle)
                    || str_contains(mb_strtolower($file['activity_title']), $needle)
                    || str_contains(mb_strtolower($file['project_name']), $needle);
            })->values();
        }

        $counts = [
            'all' => $files->count(),
            'images' => $files->where('category', 'image')->count(),
            'documents' => $files->where('category', 'document')->count(),
        ];

        if ($category !== 'all') {
            $files = $files->filter(fn (array $file): bool => $file['category'] === $category)->values();
        }

        $files = self::sortFiles($files, $sort)
            ->map(fn (array $file): array => collect($file)->except(['uploaded_at_sort'])->all())
            ->values()
            ->all();

        return [
            'total_count' => count($files),
            'counts' => $counts,
            'files' => $files,
        ];
    }

    /**
     * @return array{
     *     total_count: int,
     *     counts: array{all: int, images: int, documents: int},
     *     files: array<int, mixed>
     * }
     */
    private static function emptyPayload(): array
    {
        return [
            'total_count' => 0,
            'counts' => ['all' => 0, 'images' => 0, 'documents' => 0],
            'files' => [],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $files
     * @return Collection<int, array<string, mixed>>
     */
    private static function sortFiles(Collection $files, string $sort): Collection
    {
        return match ($sort) {
            'oldest' => $files->sortBy('uploaded_at_sort')->values(),
            'name' => $files->sortBy(fn (array $file): string => mb_strtolower($file['name']))->values(),
            'size' => $files->sortByDesc(fn (array $file): int => self::sizeSortValue($file['size_label']))->values(),
            default => $files->sortByDesc('uploaded_at_sort')->values(),
        };
    }

    private static function sizeSortValue(string $sizeLabel): int
    {
        if (preg_match('/^([\d.]+)\s*(B|KB|MB|GB)$/i', $sizeLabel, $matches) !== 1) {
            return 0;
        }

        $value = (float) $matches[1];
        $unit = strtoupper($matches[2]);

        return (int) match ($unit) {
            'GB' => $value * 1024 * 1024 * 1024,
            'MB' => $value * 1024 * 1024,
            'KB' => $value * 1024,
            default => $value,
        };
    }

    private static function resolveCategory(?string $mimeType, string $name): string
    {
        if ($mimeType !== null && str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'heic'], true)) {
            return 'image';
        }

        if (in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'rtf', 'odt'], true)) {
            return 'document';
        }

        if ($mimeType !== null && (
            str_contains($mimeType, 'pdf')
            || str_contains($mimeType, 'word')
            || str_contains($mimeType, 'sheet')
            || str_contains($mimeType, 'presentation')
            || str_contains($mimeType, 'text')
        )) {
            return 'document';
        }

        return 'other';
    }

    private static function resolveExtension(?string $mimeType, string $name): string
    {
        $extension = strtoupper(pathinfo($name, PATHINFO_EXTENSION));

        if ($extension !== '') {
            return $extension;
        }

        return match (true) {
            $mimeType !== null && str_starts_with($mimeType, 'image/') => 'IMG',
            $mimeType === 'application/pdf' => 'PDF',
            default => 'FILE',
        };
    }

    private static function formatFileSize(?int $bytes): string
    {
        if ($bytes === null || $bytes <= 0) {
            return '—';
        }

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 1).' MB';
        }

        return round($bytes / 1073741824, 1).' GB';
    }
}
