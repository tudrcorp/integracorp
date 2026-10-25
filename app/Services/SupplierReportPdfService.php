<?php

declare(strict_types=1);

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SupplierReportPdfService
{
    public const FILENAME = 'reporte-proveedores.pdf';

    public const HEADER_BANNER_PATH = 'image/suppliers-report-header.png';

    public const FOOTER_BANNER_PATH = 'image/suppliers-report-footer.png';

    /**
     * Estatus de sistema que entran al reporte: solo proveedores activos en la red.
     *
     * @var list<string>
     */
    public const ELIGIBLE_SYSTEM_STATUSES = ['AFILIADO', 'ACTIVO AFILIADO'];

    private const LOGO_CACHE_PREFIX = 'supplier_report_logo_uri:';

    private const IMAGE_CACHE_PREFIX = 'supplier_report_img_uri:';

    /**
     * Versión del diseño/filtro del PDF: forma parte de la versión del archivo, así un cambio de
     * plantilla o de criterio nunca sirve un PDF guardado con el formato anterior.
     *
     * v5: PDF guardado en disco y generado en cola; pie con clave que se ajusta al ancho.
     * v4: solo generales/preferenciales con carta de aceptación cargada y estatus activo.
     * v3: banners de cabecera/pie y fecha encriptada en el pie.
     */
    private const LAYOUT_VERSION = 'v5';

    public const STORAGE_DISK = 'local';

    public const STORAGE_DIRECTORY = 'supplier-report';

    private const GENERATION_LOCK_KEY = 'supplier_report_pdf:generate';

    /**
     * Vida máxima del candado: si el proceso que genera muere, otro puede retomar.
     */
    private const GENERATION_LOCK_SECONDS = 300;

    /**
     * Cuánto espera una petición web a que otra termine de generar el mismo PDF.
     */
    private const GENERATION_LOCK_WAIT_SECONDS = 120;

    /**
     * Versión de los datos del reporte: cambia al crear/editar/borrar proveedores o al cambiar el diseño.
     */
    public static function pdfCacheVersion(): string
    {
        $count = (int) DB::table('suppliers')->whereNull('deleted_at')->count();
        $maxUpdated = DB::table('suppliers')->max('updated_at');

        return hash('sha256', self::LAYOUT_VERSION.'|'.$count.'|'.((string) ($maxUpdated ?? '')));
    }

    public static function outputBinary(): string
    {
        return self::make()->output();
    }

    /**
     * PDF vigente para vista previa, descarga, correo y zona de descarga.
     *
     * Lo normal es que el job {@see \App\Jobs\RegenerateSupplierReportPdfJob} ya lo haya dejado en disco
     * y aquí solo se lea. Si no está (primer uso o un cambio que el job aún no procesa), se genera una
     * sola vez bajo candado: las peticiones simultáneas esperan a la primera en vez de repetir el render.
     */
    public static function outputBinaryCached(): string
    {
        $version = self::pdfCacheVersion();

        $stored = self::readStoredFile($version);

        if ($stored !== null) {
            return $stored;
        }

        try {
            return Cache::lock(self::GENERATION_LOCK_KEY, self::GENERATION_LOCK_SECONDS)
                ->block(self::GENERATION_LOCK_WAIT_SECONDS, static fn (): string => self::readStoredFile($version)
                    ?? self::generateAndStore($version));
        } catch (LockTimeoutException) {
            return self::outputBinary();
        }
    }

    /**
     * Deja en disco el PDF de la versión vigente si aún no existe. Pensado para la cola: si otro
     * proceso ya lo está generando, no repite el trabajo.
     *
     * @return bool true si generó un archivo nuevo
     */
    public static function regenerateStoredFile(): bool
    {
        $lock = Cache::lock(self::GENERATION_LOCK_KEY, self::GENERATION_LOCK_SECONDS);

        if (! $lock->get()) {
            return false;
        }

        try {
            $version = self::pdfCacheVersion();

            if (self::readStoredFile($version) !== null) {
                return false;
            }

            self::generateAndStore($version);

            return true;
        } finally {
            $lock->release();
        }
    }

    public static function storedFilePath(string $version): string
    {
        return self::STORAGE_DIRECTORY.'/reporte-proveedores-'.$version.'.pdf';
    }

    private static function readStoredFile(string $version): ?string
    {
        $disk = Storage::disk(self::STORAGE_DISK);
        $path = self::storedFilePath($version);

        if (! $disk->exists($path)) {
            return null;
        }

        $binary = $disk->get($path);

        return is_string($binary) && str_starts_with($binary, '%PDF') ? $binary : null;
    }

    /**
     * Escribe en un temporal y renombra: quien lee nunca ve un PDF a medio escribir.
     * Después borra las versiones anteriores.
     */
    private static function generateAndStore(string $version): string
    {
        $binary = self::outputBinary();

        $disk = Storage::disk(self::STORAGE_DISK);
        $path = self::storedFilePath($version);
        $temporaryPath = $path.'.'.Str::random(8).'.tmp';

        $disk->put($temporaryPath, $binary);

        if (! @rename($disk->path($temporaryPath), $disk->path($path))) {
            $disk->delete($temporaryPath);

            return $binary;
        }

        foreach ($disk->files(self::STORAGE_DIRECTORY) as $file) {
            if ($file !== $path && ! str_ends_with($file, '.tmp')) {
                $disk->delete($file);
            }
        }

        return $binary;
    }

    /**
     * Filas listas para el PDF: una sola consulta, sin hidratar modelos Eloquent ni relaciones.
     *
     * @return list<array{state: string, city: string, name: string, clasificacion: string}>
     */
    public static function reportRows(): array
    {
        $rows = DB::table('suppliers')
            ->whereNull('suppliers.deleted_at')
            ->leftJoin('states', 'suppliers.state_id', '=', 'states.id')
            ->leftJoin('cities', 'suppliers.city_id', '=', 'cities.id')
            ->leftJoin('supplier_clasificacions', 'suppliers.supplier_clasificacion_id', '=', 'supplier_clasificacions.id')
            ->tap(static fn (Builder $query): Builder => self::applyEligibilityFilter($query))
            ->orderByRaw('CASE WHEN states.definition IS NULL OR states.definition = \'\' THEN 1 ELSE 0 END')
            ->orderBy('states.definition')
            ->orderByRaw('CASE WHEN cities.definition IS NULL OR cities.definition = \'\' THEN 1 ELSE 0 END')
            ->orderBy('cities.definition')
            ->orderBy('suppliers.name')
            ->select([
                'states.definition as state_definition',
                'cities.definition as city_definition',
                'suppliers.name as supplier_name',
                'supplier_clasificacions.description as clasificacion_description',
            ])
            ->get();

        $out = [];

        foreach ($rows as $r) {
            $out[] = [
                'state' => self::normalizeCell($r->state_definition ?? null),
                'city' => self::normalizeCell($r->city_definition ?? null),
                'name' => self::normalizeCell($r->supplier_name ?? null),
                'clasificacion' => self::normalizeCell($r->clasificacion_description ?? null),
            ];
        }

        return $out;
    }

    /**
     * Limita el reporte a proveedores con convenio GENERAL o PREFERENCIAL, carta de aceptación
     * cargada y estatus de sistema activo (AFILIADO o ACTIVO AFILIADO).
     */
    public static function applyEligibilityFilter(Builder $query): Builder
    {
        return $query
            ->where(static function (Builder $convenio): void {
                $convenio->where('suppliers.status_convenio', 'GENERAL')
                    ->orWhere('suppliers.status_convenio', 'like', '%PREFERENCIAL%');
            })
            ->whereNotNull('suppliers.carta_acceptance')
            ->where('suppliers.carta_acceptance', '<>', '')
            ->whereIn('suppliers.status_sistema', self::ELIGIBLE_SYSTEM_STATUSES);
    }

    /**
     * Data URI del logo en caché (clave incluye mtime del archivo; sin releer disco en cada petición).
     */
    public static function cachedLogoDataUri(): string
    {
        $path = public_path('image/logoNewPdf.png');

        if (! is_file($path)) {
            return '';
        }

        $mtime = @filemtime($path) ?: 0;

        return Cache::remember(
            self::LOGO_CACHE_PREFIX.$mtime,
            60 * 60 * 24 * 30,
            static function () use ($path): string {
                $raw = @file_get_contents($path);

                if ($raw === false || $raw === '') {
                    return '';
                }

                return 'data:image/png;base64,'.base64_encode($raw);
            }
        );
    }

    public static function cachedHeaderBannerDataUri(): string
    {
        return self::cachedImageDataUri(self::HEADER_BANNER_PATH);
    }

    public static function cachedFooterBannerDataUri(): string
    {
        return self::cachedImageDataUri(self::FOOTER_BANNER_PATH);
    }

    public static function generatedAtLabel(Carbon $generatedAt): string
    {
        return $generatedAt->timezone(config('app.timezone'))->format('d/m/Y H:i');
    }

    public static function encryptGeneratedAt(Carbon $generatedAt): string
    {
        return Crypt::encryptString(self::generatedAtLabel($generatedAt));
    }

    public static function make(): PdfDocument
    {
        $reportRows = self::reportRows();
        $generatedAt = now();

        $pdf = Pdf::loadView('documents.suppliers-report', [
            'reportRows' => $reportRows,
            'generatedAt' => $generatedAt,
            'generatedAtLabel' => self::generatedAtLabel($generatedAt),
            'encryptedGeneratedAt' => self::encryptGeneratedAt($generatedAt),
            'logoDataUri' => self::cachedLogoDataUri(),
            'headerBannerDataUri' => self::cachedHeaderBannerDataUri(),
            'footerBannerDataUri' => self::cachedFooterBannerDataUri(),
        ])
            ->setPaper('a4', 'portrait');

        $pdf->setOptions([
            'isRemoteEnabled' => false,
            'isJavascriptEnabled' => false,
            'isPhpEnabled' => false,
            'dpi' => 72,
            'isFontSubsettingEnabled' => false,
            'defaultFont' => 'DejaVu Sans',
        ], mergeWithDefaults: true);

        return $pdf;
    }

    private static function normalizeCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $s = trim((string) $value);

        return $s;
    }

    private static function cachedImageDataUri(string $relativePublicPath): string
    {
        $path = public_path($relativePublicPath);

        if (! is_file($path)) {
            return '';
        }

        $mtime = @filemtime($path) ?: 0;

        return Cache::remember(
            self::IMAGE_CACHE_PREFIX.hash('xxh128', $relativePublicPath).':'.$mtime,
            60 * 60 * 24 * 30,
            static function () use ($path): string {
                $raw = @file_get_contents($path);

                if ($raw === false || $raw === '') {
                    return '';
                }

                $mime = match (strtolower((string) pathinfo($path, PATHINFO_EXTENSION))) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'webp' => 'image/webp',
                    'gif' => 'image/gif',
                    default => 'image/png',
                };

                return 'data:'.$mime.';base64,'.base64_encode($raw);
            }
        );
    }
}
