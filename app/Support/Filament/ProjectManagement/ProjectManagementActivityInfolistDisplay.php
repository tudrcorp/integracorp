<?php

declare(strict_types=1);

namespace App\Support\Filament\ProjectManagement;

use App\Models\ProjectManagement\Activity;
use App\Models\ProjectManagement\Document;
use App\Models\ProjectManagement\NotesLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ProjectManagementActivityInfolistDisplay
{
    /**
     * @return array{
     *     activity_title: string,
     *     activity_color: string,
     *     stats: array{total: int, latest_at: string, latest_author: string},
     *     notes: list<array{
     *         id: int,
     *         content: string,
     *         author_name: string,
     *         author_email: ?string,
     *         author_initials: string,
     *         created_at: string,
     *         created_time: string,
     *         created_at_human: string
     *     }>
     * }
     */
    public static function notesJournalPayload(Activity $record): array
    {
        $notes = self::resolveNotes($record);

        $latest = $notes->first();

        return [
            'activity_title' => (string) $record->title,
            'activity_color' => ProjectManagementActivityTable::resolveColor($record),
            'stats' => [
                'total' => $notes->count(),
                'latest_at' => $latest?->created_at?->format('d/m/Y H:i') ?? '—',
                'latest_author' => $latest?->author?->name ?? '—',
            ],
            'notes' => $notes->map(function (NotesLog $note): array {
                $authorName = (string) ($note->author?->name ?? 'Usuario del sistema');

                return [
                    'id' => (int) $note->id,
                    'content' => trim((string) $note->content),
                    'author_name' => $authorName,
                    'author_email' => $note->author?->email,
                    'author_initials' => self::initials($authorName),
                    'created_at' => $note->created_at?->format('d/m/Y') ?? '—',
                    'created_time' => $note->created_at?->format('H:i') ?? '—',
                    'created_at_human' => $note->created_at?->diffForHumans() ?? '—',
                    'search_blob' => Str::lower(implode(' ', array_filter([
                        $authorName,
                        $note->author?->email,
                        $note->content,
                        $note->created_at?->format('d/m/Y H:i'),
                    ]))),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @return array{
     *     activity_title: string,
     *     activity_color: string,
     *     description: string,
     *     has_description: bool
     * }
     */
    public static function descriptionPayload(Activity $record): array
    {
        $description = self::normalizeDescriptionText((string) $record->description);

        return [
            'activity_title' => (string) $record->title,
            'activity_color' => ProjectManagementActivityTable::resolveColor($record),
            'description' => $description,
            'has_description' => $description !== '',
        ];
    }

    /**
     * @return array{
     *     documents: list<array{
     *         id: int,
     *         name: string,
     *         extension: string,
     *         file_type: ?string,
     *         file_size_label: string,
     *         uploader_name: string,
     *         uploaded_at: string,
     *         uploaded_at_human: string,
     *         download_url: ?string,
     *         exists: bool,
     *         tone: string,
     *         search_blob: string
     *     }>
     * }
     */
    public static function documentsPayload(Activity $record): array
    {
        $documents = self::resolveDocuments($record);

        return [
            'documents' => $documents->map(function (Document $document): array {
                $path = (string) $document->file_path;
                $extension = strtoupper((string) pathinfo($path, PATHINFO_EXTENSION));
                $exists = $path !== '' && Storage::disk('public')->exists($path);
                $name = (string) $document->name;

                return [
                    'id' => (int) $document->id,
                    'name' => $name,
                    'extension' => $extension !== '' ? $extension : 'FILE',
                    'file_type' => $document->file_type,
                    'file_size_label' => self::formatFileSize($document->file_size),
                    'uploader_name' => (string) ($document->uploader?->name ?? 'Usuario del sistema'),
                    'uploaded_at' => $document->created_at?->format('d/m/Y H:i') ?? '—',
                    'uploaded_at_human' => $document->created_at?->diffForHumans() ?? '—',
                    'download_url' => $exists ? Storage::disk('public')->url($path) : null,
                    'exists' => $exists,
                    'tone' => self::extensionTone($extension),
                    'search_blob' => Str::lower(implode(' ', array_filter([
                        $name,
                        $extension,
                        $document->file_type,
                        $document->uploader?->name,
                    ]))),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @return Collection<int, NotesLog>
     */
    private static function resolveNotes(Activity $record): Collection
    {
        if ($record->relationLoaded('notesLogs')) {
            return $record->notesLogs->sortByDesc('created_at')->values();
        }

        return $record->notesLogs()
            ->with('author:id,name,email')
            ->latest()
            ->get();
    }

    /**
     * @return Collection<int, Document>
     */
    private static function resolveDocuments(Activity $record): Collection
    {
        if ($record->relationLoaded('documents')) {
            return $record->documents->sortByDesc('created_at')->values();
        }

        return $record->documents()
            ->with('uploader:id,name,email')
            ->latest()
            ->get();
    }

    public static function normalizeDescriptionText(string $description): string
    {
        $description = trim($description);

        if ($description === '') {
            return '';
        }

        $lines = preg_split("/\r\n|\r|\n/", $description) ?: [];

        return collect($lines)
            ->map(fn (string $line): string => ltrim($line))
            ->implode("\n");
    }

    public static function formatFileSize(?int $bytes): string
    {
        if ($bytes === null || $bytes <= 0) {
            return '—';
        }

        if ($bytes >= 1_048_576) {
            return number_format($bytes / 1_048_576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    private static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return Str::upper(collect($parts)
            ->filter(fn (string $part): bool => $part !== '')
            ->take(2)
            ->map(fn (string $part): string => Str::substr($part, 0, 1))
            ->implode(''));
    }

    private static function extensionTone(string $extension): string
    {
        return match ($extension) {
            'PDF' => 'danger',
            'DOC', 'DOCX' => 'info',
            'XLS', 'XLSX', 'CSV' => 'success',
            'PNG', 'JPG', 'JPEG', 'WEBP', 'GIF' => 'warning',
            'ZIP', 'RAR', '7Z' => 'gray',
            default => 'primary',
        };
    }
}
