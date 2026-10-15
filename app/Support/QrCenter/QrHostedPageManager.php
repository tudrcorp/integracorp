<?php

declare(strict_types=1);

namespace App\Support\QrCenter;

use App\Models\QrDestination;
use App\Models\QrHostedPage;
use App\Models\QrHostedPageVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Publica páginas HTML en `/p/{slug}` y les agrega versiones.
 *
 * Una página nunca se borra (algún QR impreso puede llevar a ella): solo se
 * reemplaza por una versión nueva.
 */
final class QrHostedPageManager
{
    public const SLUG_MAX = 60;

    public static function suggestSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name), self::SLUG_MAX, '');
        $base = trim($base, '-');

        return $base !== '' ? $base : 'pagina';
    }

    public static function normalizeSlug(string $slug): string
    {
        return trim(Str::limit(Str::slug($slug), self::SLUG_MAX, ''), '-');
    }

    public static function slugIsTaken(string $slug, ?int $ignorePageId = null): bool
    {
        return QrHostedPage::query()
            ->where('slug', $slug)
            ->when($ignorePageId !== null, fn ($query) => $query->whereKeyNot($ignorePageId))
            ->exists();
    }

    /**
     * @throws ValidationException
     */
    public function create(string $name, string $slug, string $html, ?string $originalName, User $user): QrHostedPage
    {
        $name = trim($name);
        $slug = self::normalizeSlug($slug);

        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['pageName' => 'Escribe un nombre de hasta 120 caracteres.']);
        }

        if ($slug === '') {
            throw ValidationException::withMessages(['pageSlug' => 'El enlace necesita al menos una letra o un número.']);
        }

        $review = $this->reviewOrFail($html);

        return $this->transaction(function () use ($name, $slug, $html, $originalName, $user, $review): QrHostedPage {
            if (self::slugIsTaken($slug)) {
                throw ValidationException::withMessages(['pageSlug' => 'Ya hay una página con el enlace «'.$slug.'». Elige otro.']);
            }

            $page = QrHostedPage::query()->create([
                'name' => $name,
                'slug' => $slug,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            return $this->storeVersion($page, 1, $html, $originalName, $user, $review);
        });
    }

    /**
     * @throws ValidationException
     */
    public function addVersion(QrHostedPage $page, string $html, ?string $originalName, User $user): QrHostedPage
    {
        $review = $this->reviewOrFail($html);

        return $this->transaction(function () use ($page, $html, $originalName, $user, $review): QrHostedPage {
            $locked = QrHostedPage::query()->lockForUpdate()->findOrFail($page->id);
            $next = (int) QrHostedPageVersion::query()->where('qr_hosted_page_id', $locked->id)->max('version') + 1;

            return $this->storeVersion($locked, $next, $html, $originalName, $user, $review);
        });
    }

    /**
     * Destinos cuya versión vigente lleva a cada página, por id de página.
     *
     * @return array<int, int>
     */
    public static function usageCounts(): array
    {
        return QrDestination::query()
            ->join('qr_destination_versions', 'qr_destination_versions.id', '=', 'qr_destinations.current_version_id')
            ->where('qr_destinations.target_type', QrDestination::TARGET_HOSTED_PAGE)
            ->whereNotNull('qr_destination_versions.qr_hosted_page_id')
            ->groupBy('qr_destination_versions.qr_hosted_page_id')
            ->selectRaw('qr_destination_versions.qr_hosted_page_id as page_id, count(*) as total')
            ->pluck('total', 'page_id')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * @return list<array{level: string, title: string, detail: string}>
     *
     * @throws ValidationException
     */
    private function reviewOrFail(string $html): array
    {
        $review = QrHostedPageReview::review($html);
        $error = QrHostedPageReview::firstError($review);

        if ($error !== null) {
            throw ValidationException::withMessages(['pageFile' => $error]);
        }

        return $review;
    }

    /**
     * @param  list<array{level: string, title: string, detail: string}>  $review
     */
    private function storeVersion(QrHostedPage $page, int $version, string $html, ?string $originalName, User $user, array $review): QrHostedPage
    {
        $path = QrCenterStorage::hostedPagePath($page->id, $version);
        QrCenterStorage::put($path, $html);

        try {
            $record = QrHostedPageVersion::query()->create([
                'qr_hosted_page_id' => $page->id,
                'version' => $version,
                'file_path' => $path,
                'original_name' => $originalName !== null ? Str::limit($originalName, 250, '') : null,
                'size_bytes' => strlen($html),
                'sha256' => hash('sha256', $html),
                'review' => $review,
                'created_by' => $user->id,
                'created_by_name' => $user->name,
            ]);

            $page->forceFill(['current_version_id' => $record->id, 'updated_by' => $user->id])->save();
        } catch (Throwable $exception) {
            QrCenterStorage::delete($path);

            throw $exception;
        }

        return $page->refresh();
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function transaction(callable $callback): mixed
    {
        return DB::transaction($callback, 3);
    }
}
