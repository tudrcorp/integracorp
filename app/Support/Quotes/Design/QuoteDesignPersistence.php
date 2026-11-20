<?php

declare(strict_types=1);

namespace App\Support\Quotes\Design;

use App\Models\Plan;
use App\Models\QuoteDesign;
use App\Models\QuoteDesignVersion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Única puerta de escritura de un diseño de cotización.
 *
 * En una transacción: normaliza las páginas fijas a A4, valida las fuentes, asigna los planes
 * (un plan solo puede tener un diseño) y, si algo de lo que se imprime cambió, deja una versión
 * nueva e inmutable. Las propuestas ya emitidas siguen apuntando a su versión.
 */
final class QuoteDesignPersistence
{
    /**
     * @param  array<string, mixed>  $data  estado del formulario
     *
     * @throws ValidationException
     */
    public static function save(?QuoteDesign $design, array $data): QuoteDesign
    {
        $planIds = self::planIds($data['plans'] ?? []);
        $status = ($data['status'] ?? QuoteDesign::STATUS_INACTIVE) === QuoteDesign::STATUS_ACTIVE
            ? QuoteDesign::STATUS_ACTIVE
            : QuoteDesign::STATUS_INACTIVE;

        if ($status === QuoteDesign::STATUS_ACTIVE && $planIds === []) {
            throw ValidationException::withMessages([
                'data.plans' => 'Para activar el diseño, asígnelo al menos a un plan.',
            ]);
        }

        self::assertPlansAreFree($design, $planIds);

        $fonts = [];

        foreach (['font_regular_path', 'font_bold_path', 'font_mono_path'] as $field) {
            $fonts[$field] = self::validatedFont($data[$field] ?? null, $field);
        }

        try {
            $pagesBefore = self::normalizedPages($data['pages_before'] ?? []);
            $pagesAfter = self::normalizedPages($data['pages_after'] ?? []);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['data.pages_before' => $exception->getMessage()]);
        }

        $user = (string) (Auth::user()?->name ?? 'Sistema');
        $rawUploads = self::rawUploads([...(array) ($data['pages_before'] ?? []), ...(array) ($data['pages_after'] ?? [])]);

        $saved = DB::transaction(function () use ($design, $data, $planIds, $status, $fonts, $pagesBefore, $pagesAfter, $user): QuoteDesign {
            $design ??= new QuoteDesign(['created_by' => $user]);

            $design->fill([
                'name' => trim((string) ($data['name'] ?? '')),
                'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
                'status' => $status,
                'theme' => QuoteDesignTheme::sanitizeThemeData((array) ($data['theme'] ?? [])),
                'pages_before' => $pagesBefore,
                'pages_after' => $pagesAfter,
                'logo_path' => self::single($data['logo_path'] ?? null),
                'font_regular_path' => $fonts['font_regular_path'],
                'font_bold_path' => $fonts['font_bold_path'],
                'font_mono_path' => $fonts['font_mono_path'],
                'source_html_path' => self::single($data['source_html_path'] ?? null) ?? $design->source_html_path,
                'updated_by' => $user,
            ]);

            $design->save();
            $design->plans()->sync($planIds);

            self::recordVersion($design, $user);

            return $design->refresh();
        });

        /** Las imágenes tal como se subieron ya tienen su versión A4: se descartan. */
        Storage::disk('public')->delete($rawUploads);

        return $saved;
    }

    /**
     * Imágenes recién subidas que aún no pasaron por el normalizador.
     *
     * @param  array<int|string, mixed>  $paths
     * @return list<string>
     */
    private static function rawUploads(array $paths): array
    {
        return array_values(array_filter(
            $paths,
            static fn (mixed $path): bool => is_string($path)
                && str_starts_with($path, QuoteDesign::DIRECTORY.'/')
                && ! QuoteDesignImageNormalizer::isNormalized($path),
        ));
    }

    /**
     * Activa o inactiva sin pasar por el formulario (acción de la tabla). Activar exige planes.
     *
     * @throws ValidationException
     */
    public static function setStatus(QuoteDesign $design, string $status): void
    {
        if ($status === QuoteDesign::STATUS_ACTIVE && ! $design->plans()->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Para activar el diseño, asígnelo al menos a un plan.',
            ]);
        }

        $design->forceFill([
            'status' => $status === QuoteDesign::STATUS_ACTIVE ? QuoteDesign::STATUS_ACTIVE : QuoteDesign::STATUS_INACTIVE,
            'updated_by' => (string) (Auth::user()?->name ?? 'Sistema'),
        ])->save();

        QuoteDesignResolver::flush();
    }

    /**
     * Snapshot de lo que se imprime con el diseño tal como está ahora.
     *
     * @return array<string, mixed>
     */
    public static function snapshotOf(QuoteDesign $design): array
    {
        return QuoteDesignTheme::snapshotFrom(
            $design->theme,
            $design->pages_before,
            $design->pages_after,
            $design->logo_path,
            $design->font_regular_path,
            $design->font_bold_path,
            $design->font_mono_path,
        );
    }

    /**
     * Versión nueva solo si cambió algo de lo que se imprime: cambiar el nombre o los planes no
     * crea versiones.
     */
    private static function recordVersion(QuoteDesign $design, string $user): void
    {
        $snapshot = self::snapshotOf($design);
        $fingerprint = sha1((string) json_encode($snapshot));

        $latest = $design->versions()->orderByDesc('version')->lockForUpdate()->first();

        if ($latest !== null && $latest->fingerprint === $fingerprint) {
            QuoteDesignResolver::flush();

            return;
        }

        $number = (int) ($latest?->version ?? 0) + 1;

        QuoteDesignVersion::query()->create([
            'quote_design_id' => $design->getKey(),
            'version' => $number,
            'snapshot' => $snapshot,
            'fingerprint' => $fingerprint,
            'created_by' => $user,
        ]);

        $design->forceFill(['version' => $number])->save();

        QuoteDesignResolver::flush();
    }

    /**
     * @param  list<int>  $planIds
     *
     * @throws ValidationException
     */
    private static function assertPlansAreFree(?QuoteDesign $design, array $planIds): void
    {
        if ($planIds === []) {
            return;
        }

        $taken = DB::table('quote_design_plan')
            ->join('quote_designs', 'quote_designs.id', '=', 'quote_design_plan.quote_design_id')
            ->whereIn('quote_design_plan.plan_id', $planIds)
            ->when($design?->exists, fn ($query) => $query->where('quote_design_plan.quote_design_id', '!=', $design->getKey()))
            ->get(['quote_design_plan.plan_id', 'quote_designs.name']);

        if ($taken->isEmpty()) {
            return;
        }

        $plans = Plan::query()->whereIn('id', $taken->pluck('plan_id'))->pluck('description', 'id');

        $detail = $taken
            ->map(fn (object $row): string => '«'.($plans[$row->plan_id] ?? 'Plan '.$row->plan_id).'» ya usa el diseño «'.$row->name.'»')
            ->implode('; ');

        throw ValidationException::withMessages([
            'data.plans' => 'Un plan solo puede tener un diseño. '.$detail.'. Quítelo de ese diseño primero.',
        ]);
    }

    /**
     * @return list<int>
     */
    private static function planIds(mixed $plans): array
    {
        if (! is_array($plans)) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $plans), static fn (int $id): bool => $id > 0)));

        return $ids === [] ? [] : array_map('intval', Plan::query()->whereIn('id', $ids)->pluck('id')->all());
    }

    /**
     * @return list<string>
     *
     * @throws RuntimeException
     */
    private static function normalizedPages(mixed $pages): array
    {
        if (! is_array($pages)) {
            return [];
        }

        $normalized = [];

        foreach (array_values($pages) as $path) {
            if (! is_string($path) || trim($path) === '') {
                continue;
            }

            $normalized[] = QuoteDesignImageNormalizer::normalize($path);
        }

        return array_slice($normalized, 0, QuoteDesignTheme::MAX_FIXED_PAGES);
    }

    /**
     * Una fuente TrueType empieza con 00 01 00 00 o con «true». Cualquier otra cosa (un .ttf
     * renombrado, un OTF con CFF, un archivo dañado) haría fallar el PDF entero, así que se rechaza.
     *
     * @throws ValidationException
     */
    public static function validatedFont(mixed $path, string $field): ?string
    {
        $path = self::single($path);

        if ($path === null) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            throw ValidationException::withMessages(["data.{$field}" => 'No se encontró la fuente subida. Vuelva a cargarla.']);
        }

        $header = substr((string) $disk->get($path), 0, 4);

        if ($header !== "\x00\x01\x00\x00" && $header !== 'true') {
            throw ValidationException::withMessages(["data.{$field}" => 'La fuente debe ser un archivo TrueType (.ttf) válido. Las fuentes .otf o .woff no sirven para el PDF.']);
        }

        return $path;
    }

    private static function single(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
