<?php

declare(strict_types=1);

namespace App\Support\OrgStructure;

use App\Models\OrgStructureVersion;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Organigrama en PDF, siempre generado en cola.
 *
 * Un árbol ancho no cabe en una hoja con DomPDF, así que se imprime por láminas:
 * una por cada puesto con subordinados (su jefe arriba y su equipo en tarjetas),
 * más un índice completo con sangría por nivel. Los archivos viven 7 días en la
 * carpeta privada de quien los pidió.
 */
final class OrgChartPdfExporter
{
    private const DISK = 'local';

    private const DIRECTORY = 'org-structure/exports';

    public const RETENTION_DAYS = 7;

    public const FILE_PATTERN = 'organigrama-[0-9]{8}-[0-9]{6}-[a-z0-9]{6}\.pdf';

    private const MAX_SHEETS = 400;

    private const MAX_INDEX_ROWS = 3000;

    private const CARDS_PER_ROW = 3;

    public static function newFilename(?CarbonImmutable $now = null): string
    {
        $now ??= CarbonImmutable::now();

        return 'organigrama-'.$now->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.pdf';
    }

    public static function isValidFilename(string $file): bool
    {
        return preg_match('/^'.self::FILE_PATTERN.'$/', $file) === 1;
    }

    /**
     * Genera el PDF y lo deja en la carpeta del usuario. Idempotente por nombre: se
     * escribe a un `.part` y se renombra al final, así nunca se descarga a medias.
     *
     * @param  array{root_key?: string|null, depth?: int|null, show_contact?: bool, show_vacancies?: bool}  $options
     */
    public static function store(OrgStructureVersion $version, array $options, int $userId, string $filename): string
    {
        if (! self::isValidFilename($filename)) {
            throw new OrgStructureException('Nombre de archivo de exportación no válido.');
        }

        $disk = self::disk();
        $directory = self::DIRECTORY.'/'.$userId;
        $disk->makeDirectory($directory);
        $relativePath = $directory.'/'.$filename;
        $absolutePath = $disk->path($relativePath);
        $partialPath = $absolutePath.'.part';

        try {
            file_put_contents($partialPath, self::render($version, $options));
            rename($partialPath, $absolutePath);
        } catch (Throwable $exception) {
            @unlink($partialPath);

            throw $exception;
        }

        self::purgeExpired($userId);

        return $relativePath;
    }

    /**
     * @param  array{root_key?: string|null, depth?: int|null, show_contact?: bool, show_vacancies?: bool}  $options
     */
    public static function render(OrgStructureVersion $version, array $options): string
    {
        return Pdf::loadView('documents.org-chart', self::viewData($version, $options))
            ->setPaper('a4', 'landscape')
            ->output();
    }

    /**
     * @param  array{root_key?: string|null, depth?: int|null, show_contact?: bool, show_vacancies?: bool}  $options
     * @return array<string, mixed>
     */
    public static function viewData(OrgStructureVersion $version, array $options): array
    {
        $payload = OrgChartPayload::build($version);
        $nodes = $payload['nodes'];
        $showVacancies = (bool) ($options['show_vacancies'] ?? true);
        $showContact = (bool) ($options['show_contact'] ?? false);
        $maxDepth = isset($options['depth']) && $options['depth'] !== null ? max(1, (int) $options['depth']) : null;

        $byId = [];
        $children = [];

        foreach ($nodes as $node) {
            $byId[$node['id']] = $node;
            $children[$node['parent'] ?? 0][] = $node['id'];
        }

        foreach ($children as &$list) {
            usort($list, fn (int $a, int $b): int => [$byId[$a]['sort'], $a] <=> [$byId[$b]['sort'], $b]);
        }
        unset($list);

        $root = null;

        if (! empty($options['root_key'])) {
            foreach ($nodes as $node) {
                if ($node['key'] === $options['root_key']) {
                    $root = $node;
                }
            }
        }

        $root ??= isset($children[0][0]) ? $byId[$children[0][0]] : null;

        $sheets = [];
        $index = [];

        if ($root !== null) {
            $visible = fn (array $node): bool => $showVacancies || $node['occupants'] !== [] || ! empty($children[$node['id']]);
            $queue = [[$root['id'], 1, [$root['title']]]];

            while ($queue !== []) {
                [$id, $level, $path] = array_shift($queue);
                $node = $byId[$id];

                if (count($index) < self::MAX_INDEX_ROWS && $visible($node)) {
                    $index[] = ['level' => $level, 'node' => $node, 'reports' => count($children[$id] ?? [])];
                }

                $kids = array_values(array_filter(
                    array_map(fn (int $childId): array => $byId[$childId], $children[$id] ?? []),
                    $visible,
                ));

                if ($kids === [] || ($maxDepth !== null && $level >= $maxDepth)) {
                    continue;
                }

                if (count($sheets) < self::MAX_SHEETS) {
                    $sheets[] = [
                        'path' => $path,
                        'head' => $node,
                        'rows' => array_chunk(array_map(fn (array $kid): array => [
                            'node' => $kid,
                            'reports' => count($children[$kid['id']] ?? []),
                        ], $kids), self::CARDS_PER_ROW),
                    ];
                }

                foreach ($kids as $kid) {
                    $queue[] = [$kid['id'], $level + 1, [...$path, $kid['title']]];
                }
            }
        }

        return [
            'version' => $payload['version'],
            'stats' => $payload['stats'],
            'sheets' => $sheets,
            'index' => $index,
            'showContact' => $showContact,
            'cardsPerRow' => self::CARDS_PER_ROW,
            'truncated' => count($sheets) >= self::MAX_SHEETS,
            'generatedAt' => now()->format('d/m/Y H:i'),
            'rootTitle' => $root['title'] ?? null,
        ];
    }

    public static function absolutePathFor(int $userId, string $file): ?string
    {
        if (! self::isValidFilename($file)) {
            return null;
        }

        $relativePath = self::DIRECTORY.'/'.$userId.'/'.$file;

        return self::disk()->exists($relativePath) ? self::disk()->path($relativePath) : null;
    }

    public static function purgeExpired(int $userId): void
    {
        $disk = self::disk();
        $limit = now()->subDays(self::RETENTION_DAYS)->getTimestamp();

        foreach ($disk->files(self::DIRECTORY.'/'.$userId) as $path) {
            try {
                if ($disk->lastModified($path) < $limit) {
                    $disk->delete($path);
                }
            } catch (Throwable) {
                continue;
            }
        }
    }

    private static function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(self::DISK);

        return $disk;
    }
}
