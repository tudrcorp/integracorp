<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Models\CrmDocument;
use App\Models\CrmQuickReply;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Respuestas rápidas y documentos aprobados que ve el analista. Lectura acotada: solo activos,
 * del área del caso o sin área, en el orden que dejó el supervisor.
 */
final class CrmInboxLibrary
{
    public const DISK = 'local';

    public const DIRECTORY = 'crm-documents';

    public const MAX_BYTES = 8_000_000;

    public const MAX_ITEMS = 60;

    /**
     * @var array<string, bool>
     */
    private static array $tables = [];

    /**
     * Variables que una respuesta puede usar. El texto final lo arma el servidor.
     *
     * @var array<string, string>
     */
    public const VARIABLES = [
        '{cliente}' => 'Primer nombre del cliente',
        '{analista}' => 'Tu primer nombre',
        '{propuesta}' => 'Número y monto de la última propuesta',
    ];

    /**
     * @param  array{cliente: string, analista: string, propuesta: string}  $values
     * @return list<array{id: ?int, label: string, text: string}>
     */
    public static function quickReplies(?string $area, array $values): array
    {
        $rows = self::storedReplies($area);

        if ($rows === null) {
            $rows = array_map(
                static fn (array $reply): array => ['id' => null, 'label' => (string) ($reply['label'] ?? ''), 'body' => (string) ($reply['text'] ?? '')],
                array_values(array_filter((array) config('crm-inbox.quick_replies', []), 'is_array')),
            );
        }

        $replies = [];

        foreach ($rows as $row) {
            $label = trim($row['label']);
            $body = trim($row['body']);

            if ($label === '' || $body === '') {
                continue;
            }

            if (str_contains($body, '{propuesta}') && $values['propuesta'] === '') {
                continue;
            }

            $replies[] = [
                'id' => $row['id'],
                'label' => $label,
                'text' => self::fill($body, $values),
            ];
        }

        return array_slice($replies, 0, self::MAX_ITEMS);
    }

    /**
     * @param  array{cliente: string, analista: string, propuesta: string}  $values
     */
    public static function fill(string $body, array $values): string
    {
        return trim(str_replace(
            ['{cliente}', '{analista}', '{propuesta}'],
            [$values['cliente'], $values['analista'], $values['propuesta']],
            $body,
        ));
    }

    /**
     * @return list<array{id: int, title: string, description: string, size: int}>
     */
    public static function documents(?string $area): array
    {
        if (! self::ready('crm_documents')) {
            return [];
        }

        return CrmDocument::query()
            ->where('active', true)
            ->where(fn ($query) => $query->whereNull('area')->when($area !== null, fn ($query) => $query->orWhere('area', $area)))
            ->orderBy('sort')
            ->orderByDesc('uses')
            ->orderBy('title')
            ->limit(self::MAX_ITEMS)
            ->get(['id', 'title', 'description', 'size'])
            ->map(fn (CrmDocument $document): array => [
                'id' => (int) $document->id,
                'title' => (string) $document->title,
                'description' => (string) ($document->description ?? ''),
                'size' => (int) $document->size,
            ])
            ->all();
    }

    /**
     * Documento que el analista puede enviar en este caso: activo y de su área o general.
     */
    public static function documentFor(int $id, ?string $area): ?CrmDocument
    {
        if ($id <= 0 || ! self::ready('crm_documents')) {
            return null;
        }

        $document = CrmDocument::query()->where('active', true)->find($id);

        if ($document === null || ($document->area !== null && $document->area !== $area)) {
            return null;
        }

        return $document;
    }

    public static function read(CrmDocument $document): ?string
    {
        $path = (string) $document->path;

        if (! self::safePath($path)) {
            return null;
        }

        try {
            $disk = Storage::disk(self::DISK);

            if (! $disk->exists($path) || $disk->size($path) > self::MAX_BYTES) {
                return null;
            }

            $pdf = $disk->get($path);
        } catch (Throwable) {
            return null;
        }

        return is_string($pdf) && str_starts_with($pdf, '%PDF') ? $pdf : null;
    }

    public static function filename(CrmDocument $document): string
    {
        $base = preg_replace('/[^A-Za-z0-9._ -]/', '', (string) $document->filename) ?? '';
        $base = trim(preg_replace('/\.pdf$/i', '', $base) ?? '');

        if ($base === '') {
            $base = preg_replace('/[^A-Za-z0-9 -]/', '', (string) $document->title) ?? '';
            $base = trim($base) !== '' ? trim($base) : 'Documento';
        }

        return mb_substr(str_replace(' ', '-', $base), 0, 100).'.pdf';
    }

    public static function safePath(string $path): bool
    {
        return str_starts_with($path, self::DIRECTORY.'/')
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\')
            && str_ends_with(strtolower($path), '.pdf');
    }

    public static function countReply(int $id): void
    {
        if ($id > 0 && self::ready('crm_quick_replies')) {
            CrmQuickReply::query()->whereKey($id)->increment('uses');
        }
    }

    public static function countDocument(int $id): void
    {
        if ($id > 0 && self::ready('crm_documents')) {
            CrmDocument::query()->whereKey($id)->increment('uses');
        }
    }

    /**
     * @return list<array{id: int, label: string, body: string}>|null null si no hay ninguna guardada: se usan las de configuración.
     */
    private static function storedReplies(?string $area): ?array
    {
        if (! self::ready('crm_quick_replies')) {
            return null;
        }

        $rows = CrmQuickReply::query()
            ->where('active', true)
            ->where(fn ($query) => $query->whereNull('area')->when($area !== null, fn ($query) => $query->orWhere('area', $area)))
            ->orderBy('sort')
            ->orderByDesc('uses')
            ->orderBy('label')
            ->limit(self::MAX_ITEMS)
            ->get(['id', 'label', 'body']);

        if ($rows->isEmpty()) {
            return null;
        }

        return $rows->map(fn (CrmQuickReply $reply): array => [
            'id' => (int) $reply->id,
            'label' => (string) $reply->label,
            'body' => (string) $reply->body,
        ])->all();
    }

    /**
     * La tabla puede faltar en un entorno que aún no migró: la bandeja sigue con lo de configuración.
     */
    private static function ready(string $table): bool
    {
        return self::$tables[$table] ??= Schema::hasTable($table);
    }

    public static function forgetSchema(): void
    {
        self::$tables = [];
    }
}
