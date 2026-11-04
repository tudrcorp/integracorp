<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CapitationPlan;
use App\Support\Business\CapitationPlans\CapitationProviders;
use App\Support\Operations\ProviderDeletion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Ficha PDF de uno o varios planes de capitado.
 *
 * El PDF se guarda en el disco privado con el nombre de su huella (planes, fecha de
 * actualización, proveedor y versión de la plantilla). La vista previa, la descarga y los
 * envíos reutilizan la misma copia: un solo render mientras los planes no cambien.
 */
final class CapitationPlanPdfService
{
    /** Tope por documento: la selección masiva de la tabla no puede superar este número. */
    public const MAX_PLANS = 200;

    /** Subir al cambiar la plantilla: invalida todas las copias guardadas. */
    private const TEMPLATE_VERSION = 3;

    private const CACHE_DIRECTORY = 'app/private/capitation-plans/pdf-cache';

    private const PUBLIC_DIRECTORY = 'planes-capitado';

    /** Copias con más de este tiempo se borran al guardar una nueva. */
    private const CACHE_TTL_SECONDS = 172800;

    /**
     * @param  array<int, int|string>  $ids
     * @return Collection<int, CapitationPlan>
     */
    public static function plans(array $ids): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return new Collection;
        }

        return CapitationPlan::query()
            ->whereKey($ids)
            ->with([
                'benefits:id,capitation_plan_id,benefit_id,name,note,sort',
                'coveredPlans:id,description',
                'supplier:id,name,rif,correo_principal,personal_phone,local_phone,deleted_at',
                'doctorNurse:id,name,rif,correo_principal,personal_phone,local_phone,deleted_at',
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Ruta absoluta del PDF; lo genera si no existe una copia vigente.
     *
     * @param  Collection<int, CapitationPlan>  $plans
     */
    public static function path(Collection $plans): string
    {
        if ($plans->isEmpty()) {
            throw new RuntimeException('No hay planes para generar el PDF.');
        }

        $directory = storage_path(self::CACHE_DIRECTORY);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('No se pudo crear el directorio de PDF de planes de capitado.');
        }

        $path = $directory.'/'.self::fingerprint($plans).'.pdf';

        if (is_file($path) && filesize($path) > 100) {
            return $path;
        }

        $output = self::render($plans);

        /** Escritura atómica: una vista previa concurrente nunca lee un PDF a medias. */
        $temporary = $path.'.'.Str::random(8).'.tmp';

        if (file_put_contents($temporary, $output) === false || ! rename($temporary, $path)) {
            @unlink($temporary);

            throw new RuntimeException('No se pudo guardar el PDF de planes de capitado.');
        }

        self::pruneCache($directory);

        return $path;
    }

    /**
     * Copia pública con nombre impredecible, para que UltraMsg pueda descargarla.
     *
     * @param  Collection<int, CapitationPlan>  $plans
     * @return string ruta relativa a `public/storage`
     */
    public static function publishForWhatsApp(Collection $plans): string
    {
        $source = self::path($plans);
        $directory = public_path('storage/'.self::PUBLIC_DIRECTORY);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('No se pudo crear el directorio público de planes de capitado.');
        }

        $relative = self::PUBLIC_DIRECTORY.'/'.Str::lower(Str::random(32)).'-'.self::filename($plans);

        if (! copy($source, public_path('storage/'.$relative))) {
            throw new RuntimeException('No se pudo publicar el PDF para WhatsApp.');
        }

        return $relative;
    }

    /**
     * @param  Collection<int, CapitationPlan>  $plans
     */
    public static function filename(Collection $plans): string
    {
        if ($plans->count() === 1) {
            $slug = Str::slug((string) $plans->first()->name);

            return 'plan-de-capitado-'.($slug !== '' ? $slug : $plans->first()->getKey()).'.pdf';
        }

        return 'planes-de-capitado-'.$plans->count().'-planes-'.now()->format('Ymd').'.pdf';
    }

    /**
     * @param  Collection<int, CapitationPlan>  $plans
     */
    public static function title(Collection $plans): string
    {
        return $plans->count() === 1
            ? 'Plan de capitado «'.$plans->first()->name.'»'
            : 'Reporte de '.$plans->count().' planes de capitado';
    }

    /**
     * @param  Collection<int, CapitationPlan>  $plans
     */
    public static function whatsAppCaption(Collection $plans, ?string $message): string
    {
        $lines = ['*Tu Dr Group — '.self::title($plans).'*', ''];

        if (filled($message)) {
            $lines[] = Str::squish((string) $message);
            $lines[] = '';
        }

        if ($plans->count() === 1) {
            /** @var CapitationPlan $plan */
            $plan = $plans->first();
            $lines[] = 'Proveedor: '.self::providerName($plan);
            $lines[] = 'Costo por afiliado: US$ '.number_format((float) $plan->cost_per_affiliate_usd, 2, ',', '.').' ('.$plan->payment_frequency->label().')';
            $lines[] = 'Beneficios: '.$plan->benefits->count();
        } else {
            $lines[] = 'Planes incluidos: '.$plans->count();
            $lines[] = 'Proveedores: '.$plans->map(fn (CapitationPlan $plan): string => $plan->providerKey())->unique()->count();
        }

        $lines[] = '';
        $lines[] = 'Adjuntamos el documento en PDF con el detalle.';

        return implode("\n", $lines);
    }

    /**
     * Contacto sugerido para el envío: el del proveedor, solo si todos los planes son del mismo.
     *
     * @param  Collection<int, CapitationPlan>  $plans
     * @return array{email: string|null, phone: string|null}
     */
    public static function suggestedRecipient(Collection $plans): array
    {
        if ($plans->isEmpty() || $plans->map(fn (CapitationPlan $plan): string => $plan->providerKey())->unique()->count() !== 1) {
            return ['email' => null, 'phone' => null];
        }

        $provider = $plans->first()->provider();

        if ($provider === null) {
            return ['email' => null, 'phone' => null];
        }

        $email = trim((string) $provider->getAttribute('correo_principal'));
        $phone = trim((string) ($provider->getAttribute('personal_phone') ?: $provider->getAttribute('local_phone')));

        return [
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
        ];
    }

    public static function providerName(CapitationPlan $plan): string
    {
        return (string) ProviderDeletion::displayName($plan->provider());
    }

    /**
     * @param  Collection<int, CapitationPlan>  $plans
     */
    private static function render(Collection $plans): string
    {
        $startedAt = microtime(true);

        $rows = $plans->map(fn (CapitationPlan $plan): array => [
            'plan' => $plan,
            'provider_name' => self::providerName($plan),
            'provider_type' => CapitationProviders::typeLabel($plan->provider_type),
            'provider_rif' => (string) ($plan->provider()?->getAttribute('rif') ?? ''),
            'monthly' => $plan->monthlyEquivalent(),
            'validity' => self::validity($plan),
            'covered_plans' => $plan->coveredPlans->pluck('description')->implode(', '),
            'document_names' => array_values(array_map(
                fn (string $path): string => (string) (((array) $plan->document_names)[$path] ?? basename($path)),
                array_values((array) $plan->documents),
            )),
        ])->all();

        $active = $plans->where('status', CapitationPlan::STATUS_ACTIVE);

        $output = Pdf::loadView('documents.capitation-plans', [
            'rows' => $rows,
            'isSingle' => count($rows) === 1,
            'title' => self::title($plans),
            'issuedAt' => now()->format('d/m/Y'),
            'tdgLogo' => WhiteCompanySalesReportService::tdgLogoDataUri(),
            'summary' => [
                'plans' => count($rows),
                'active' => $active->count(),
                'providers' => $plans->map(fn (CapitationPlan $plan): string => $plan->providerKey())->unique()->count(),
                'average_monthly' => $active->isNotEmpty() ? round($active->avg(fn (CapitationPlan $plan): float => $plan->monthlyEquivalent()), 2) : null,
            ],
        ])
            ->setPaper('a4', 'portrait')
            /** Sin subconjunto, DejaVu se incrusta completa: ~450 KB por documento, pesado para WhatsApp. */
            ->setOption('isFontSubsettingEnabled', true)
            ->output();

        Log::info('CapitationPlanPdfService: PDF generado', [
            'plans' => count($rows),
            'ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'bytes' => strlen($output),
        ]);

        return $output;
    }

    private static function validity(CapitationPlan $plan): string
    {
        return match (true) {
            $plan->valid_from === null && $plan->valid_until === null => 'Sin fecha de fin',
            $plan->valid_until === null => 'Desde '.$plan->valid_from->format('d/m/Y'),
            $plan->valid_from === null => 'Hasta '.$plan->valid_until->format('d/m/Y'),
            default => $plan->valid_from->format('d/m/Y').' – '.$plan->valid_until->format('d/m/Y'),
        };
    }

    /**
     * La huella cambia si cambia cualquier dato que se imprime: el plan (sus beneficios se
     * reescriben al guardar, así que mueven `updated_at`), el nombre del proveedor, el día
     * de emisión o la plantilla.
     *
     * @param  Collection<int, CapitationPlan>  $plans
     */
    private static function fingerprint(Collection $plans): string
    {
        $parts = $plans
            ->map(fn (CapitationPlan $plan): string => implode('|', [
                $plan->getKey(),
                $plan->updated_at?->format('Y-m-d H:i:s'),
                self::providerName($plan),
                $plan->benefits->count(),
                $plan->coveredPlans->pluck('id')->implode(','),
            ]))
            ->sort()
            ->values()
            ->all();

        return sha1(json_encode([self::TEMPLATE_VERSION, now()->toDateString(), $parts]) ?: '');
    }

    private static function pruneCache(string $directory): void
    {
        $limit = time() - self::CACHE_TTL_SECONDS;

        foreach (glob($directory.'/*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $limit) {
                @unlink($file);
            }
        }
    }
}
