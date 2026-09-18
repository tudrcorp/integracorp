<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

use App\Models\PlanGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Deriva una cotización nueva usando un registro de «Planes Generados» como
 * plantilla.
 *
 * El registro seleccionado nunca se modifica: el analista ajusta la matriz en
 * la modal y de ahí sale otro registro, colgado del mismo base. Así una misma
 * configuración sirve para varias cotizaciones (mismo plan, clientes o
 * poblaciones distintas) sin volver a armar la matriz.
 */
final class PlanGeneratorTemplateCloner
{
    /**
     * Campos comerciales que el analista puede cambiar en la modal.
     *
     * @var list<string>
     */
    private const EDITABLE_FIELDS = [
        'name',
        'status',
        'control_number',
        'client_data',
        'issued_at',
        'agent_name',
        'population_unit',
        'population_summary',
        'brand_color',
        'include_monthly_total',
    ];

    private const QUOTATION_IMAGE_DIRECTORY = 'plan-generator-quotation';

    /**
     * Registro base de la familia. Derivar de una derivada cuelga la nueva
     * cotización del mismo base, no de su hermana: la familia es de un nivel.
     */
    public static function baseFor(PlanGenerator $record): PlanGenerator
    {
        return $record->templateBase();
    }

    /**
     * Sugiere el Nro. Control de la derivada: «2045-1», «2045-2»…
     *
     * Recorre hasta encontrar uno libre en toda la tabla, no solo dentro de la
     * familia, porque el número identifica la cotización de cara al cliente.
     */
    public static function suggestControlNumber(PlanGenerator $base): string
    {
        $prefix = trim((string) $base->control_number);

        if ($prefix === '') {
            $prefix = 'COT-'.$base->getKey();
        }

        $taken = PlanGenerator::query()
            ->where('control_number', 'like', $prefix.'-%')
            ->pluck('control_number')
            ->map(fn (mixed $value): string => (string) $value)
            ->all();

        for ($suffix = 1; $suffix <= 999; $suffix++) {
            $candidate = $prefix.'-'.$suffix;

            if (! in_array($candidate, $taken, true)) {
                return $candidate;
            }
        }

        return $prefix.'-'.Str::upper(Str::random(4));
    }

    /**
     * Estado inicial de la modal: datos comerciales de la plantilla más su
     * matriz completa (columnas, beneficios, coberturas y tarifas).
     *
     * El Nro. Control viene ya sugerido y la fecha de emisión se pone en hoy:
     * son los dos datos que siempre cambian entre cotizaciones.
     *
     * @return array<string, mixed>
     */
    public static function formStateFromTemplate(PlanGenerator $template): array
    {
        $base = self::baseFor($template);
        $matrix = PlanGeneratorPersistence::formStateFromModel($template);

        return [
            'template_id' => $template->getKey(),
            'base_id' => $base->getKey(),
            'name' => (string) $template->name,
            'status' => (string) ($template->status ?? 'PRE-APROBADO'),
            'control_number' => self::suggestControlNumber($base),
            'client_data' => (string) $template->client_data,
            'issued_at' => now()->toDateString(),
            'agent_name' => (string) $template->agent_name,
            'population_unit' => (string) ($template->population_unit ?? 'poblacion'),
            'population_summary' => (string) $template->population_summary,
            'brand_color' => (string) ($template->brand_color ?? PlanGeneratorBrandColor::DEFAULT),
            'include_monthly_total' => (bool) $template->include_monthly_total,
            'columns' => $matrix['columns'],
            'rows' => $matrix['rows'],
            'rate_rows' => $matrix['rate_rows'],
        ];
    }

    /**
     * Crea la cotización derivada.
     *
     * Las imágenes del cuerpo del PDF se copian a archivos propios antes de
     * abrir la transacción: si compartieran ruta con la plantilla, reemplazar
     * una imagen en la derivada borraría del disco la página del registro base.
     *
     * @param  array<string, mixed>  $formState  estado normalizado de la modal
     */
    public static function create(PlanGenerator $template, array $formState, ?string $createdBy = null): PlanGenerator
    {
        $base = self::baseFor($template);
        $quotationPages = self::copyQuotationPages($template);

        $attributes = [];

        foreach (self::EDITABLE_FIELDS as $field) {
            $attributes[$field] = $formState[$field] ?? null;
        }

        $attributes['include_monthly_total'] = (bool) ($formState['include_monthly_total'] ?? false);
        $attributes['parent_id'] = $base->getKey();
        $attributes['plan_id'] = $template->plan_id;
        $attributes['quotation_page_count'] = $template->quotation_page_count;
        $attributes['plan_page_number'] = $template->plan_page_number;
        $attributes['created_by'] = $createdBy;

        return DB::transaction(function () use ($attributes, $formState, $quotationPages): PlanGenerator {
            $derived = PlanGenerator::query()->create($attributes);

            PlanGeneratorPersistence::syncFromFormState($derived, [
                'columns' => (array) ($formState['columns'] ?? []),
                'rows' => (array) ($formState['rows'] ?? []),
                'rate_rows' => (array) ($formState['rate_rows'] ?? []),
                'quotation_pages' => $quotationPages,
            ]);

            return $derived;
        });
    }

    /**
     * Duplica en disco las imágenes del cuerpo de la cotización plantilla.
     *
     * Si un archivo ya no está en disco se conserva la ruta original: la
     * derivada queda igual de incompleta que su plantilla, que es el estado
     * real, en vez de perder la página sin aviso.
     *
     * @return array<int, array{page_number: int, image: string}>
     */
    private static function copyQuotationPages(PlanGenerator $template): array
    {
        $template->loadMissing('quotationPages');

        $disk = Storage::disk('public');
        $pages = [];

        foreach ($template->quotationPages as $page) {
            $sourcePath = (string) $page->image_path;

            if ($sourcePath === '') {
                continue;
            }

            $pages[] = [
                'page_number' => (int) $page->page_number,
                'image' => self::copyImage($disk, $sourcePath),
            ];
        }

        return $pages;
    }

    private static function copyImage(\Illuminate\Contracts\Filesystem\Filesystem $disk, string $sourcePath): string
    {
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
        $targetPath = self::QUOTATION_IMAGE_DIRECTORY.'/'.Str::uuid().($extension !== '' ? '.'.$extension : '');

        try {
            if (! $disk->exists($sourcePath)) {
                Log::warning('Imagen de cotización plantilla ausente en disco; la derivada reutiliza la ruta.', [
                    'source_path' => $sourcePath,
                ]);

                return $sourcePath;
            }

            if ($disk->copy($sourcePath, $targetPath)) {
                return $targetPath;
            }
        } catch (\Throwable $exception) {
            Log::warning('No se pudo copiar la imagen de la cotización plantilla.', [
                'source_path' => $sourcePath,
                'target_path' => $targetPath,
                'exception' => $exception->getMessage(),
            ]);
        }

        return $sourcePath;
    }
}
