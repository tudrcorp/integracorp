<?php

declare(strict_types=1);

namespace App\Support\Collections;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Collection;
use App\Models\CollectionNoticeRegeneration;
use App\Models\User;
use App\Support\Affiliation\AffiliationDocumentAffiliatesCount;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Aviso de cobro regenerado desde «Gestión de Cobranza».
 *
 * - La fecha de emisión es la del día en que se regenera (editable).
 * - El período de cobro sale de la cuota: desde la fecha de la cuota hasta esa fecha más
 *   su frecuencia (1, 3, 6 o 12 meses) menos un día (editable).
 * - Lo que el analista edita vale solo para el PDF; la cuota no se toca. Cada regeneración
 *   queda registrada y precarga los datos del cliente la vez siguiente.
 */
final class CollectionNotice
{
    public const DATE_FORMAT = 'd/m/Y';

    public const PDF_DIRECTORY = 'avisoDeCobro';

    /** Campos del cliente que el analista puede corregir en el aviso. */
    public const CLIENT_FIELDS = ['full_name', 'document', 'address', 'phone', 'email'];

    private const FREQUENCY_MONTHS = [
        'MENSUAL' => 1,
        'TRIMESTRAL' => 3,
        'SEMESTRAL' => 6,
        'ANUAL' => 12,
    ];

    /**
     * Datos con que se abre el formulario: los de la cuota, corregidos por la última
     * regeneración si la hubo. La emisión siempre propone hoy.
     *
     * @return array{full_name: string, document: string, address: string, phone: string, email: string, emission_date: string, period_from: string, period_to: string, frequency: string, last_regenerated: ?string}
     */
    public static function formDefaults(Collection $collection): array
    {
        $fromCollection = self::clientFromCollection($collection);
        $last = CollectionNoticeRegeneration::query()
            ->where('collection_id', $collection->id)
            ->latest('id')
            ->first();

        $client = $last !== null ? array_replace($fromCollection, array_intersect_key((array) $last->client, $fromCollection)) : $fromCollection;
        [$from, $to] = $last?->period_from !== null && $last->period_to !== null
            ? [$last->period_from, $last->period_to]
            : self::defaultPeriod($collection);

        return $client + [
            'emission_date' => CarbonImmutable::today()->format(self::DATE_FORMAT),
            'period_from' => $from?->format(self::DATE_FORMAT) ?? '',
            'period_to' => $to?->format(self::DATE_FORMAT) ?? '',
            'frequency' => (string) ($collection->payment_frequency ?? ''),
            'last_regenerated' => $last !== null
                ? $last->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i').' · '.($last->user_name ?? 'Sistema')
                : null,
        ];
    }

    /**
     * Desde la fecha de la cuota hasta esa fecha más su frecuencia, menos un día.
     *
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    public static function defaultPeriod(Collection $collection): array
    {
        $from = self::parseDate((string) ($collection->next_payment_date ?? ''))
            ?? self::parseIsoDate((string) ($collection->filter_next_payment_date ?? ''));
        $months = self::FREQUENCY_MONTHS[strtoupper(trim((string) ($collection->payment_frequency ?? '')))] ?? null;

        if ($from === null || $months === null) {
            return [$from, null];
        }

        return [$from, $from->addMonthsNoOverflow($months)->subDay()];
    }

    /**
     * Regenera el PDF con lo editado (o con los datos por defecto si no llega nada) y deja la bitácora.
     *
     * @param  array<string, mixed>|null  $edits
     *
     * @throws ValidationException si las fechas no son válidas o el período está al revés
     */
    public function regenerate(Collection $collection, ?array $edits, ?User $user): CollectionNoticeRegeneration
    {
        $defaults = self::formDefaults($collection);
        $input = $edits === null ? $defaults : array_replace($defaults, array_intersect_key($edits, $defaults));

        $emission = self::requiredDate($input['emission_date'] ?? '', 'emission_date', 'la fecha de emisión');

        // Sin formulario (rutas antiguas) y con una frecuencia desconocida no hay período que calcular:
        // el aviso sale sin esa línea antes que fallar. Desde el formulario es obligatorio.
        $hasPeriod = $edits !== null || (trim((string) $input['period_from']) !== '' && trim((string) $input['period_to']) !== '');
        $periodFrom = $hasPeriod ? self::requiredDate($input['period_from'] ?? '', 'period_from', 'el inicio del período de cobro') : null;
        $periodTo = $hasPeriod ? self::requiredDate($input['period_to'] ?? '', 'period_to', 'el fin del período de cobro') : null;

        if ($periodFrom !== null && $periodTo !== null && $periodTo->lt($periodFrom)) {
            throw ValidationException::withMessages(['period_to' => 'El fin del período de cobro debe ser igual o posterior al inicio.']);
        }

        $client = [];

        foreach (self::CLIENT_FIELDS as $field) {
            $client[$field] = trim((string) ($input[$field] ?? ''));
        }

        $data = $this->documentData($collection, $client, $emission, $periodFrom, $periodTo);
        $lock = Cache::lock('collection-notice:'.$collection->id, 30);

        if (! $lock->get()) {
            throw ValidationException::withMessages(['notice' => 'Otro analista está regenerando este aviso en este momento. Intente de nuevo en unos segundos.']);
        }

        try {
            $this->writePdf($collection, $data);

            return DB::transaction(fn (): CollectionNoticeRegeneration => CollectionNoticeRegeneration::query()->create([
                'collection_id' => $collection->id,
                'invoice_number' => $collection->collection_invoice_number,
                'client' => $client,
                'emission_date' => $emission->toDateString(),
                'period_from' => $periodFrom?->toDateString(),
                'period_to' => $periodTo?->toDateString(),
                'changed_fields' => array_keys(array_diff_assoc($client, self::clientFromCollection($collection))) ?: null,
                'user_id' => $user?->id,
                'user_name' => $user?->name,
            ]));
        } finally {
            $lock->release();
        }
    }

    public static function pdfFilename(Collection $collection): string
    {
        return 'ADP-'.$collection->collection_invoice_number.'.pdf';
    }

    public static function pdfPath(Collection $collection): string
    {
        return Storage::disk('public')->path(self::PDF_DIRECTORY.'/'.self::pdfFilename($collection));
    }

    public static function isCorporate(Collection $collection): bool
    {
        return $collection->type === 'AFILIACION CORPORATIVA';
    }

    public static function periodLabel(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return $from->format(self::DATE_FORMAT).' AL '.$to->format(self::DATE_FORMAT);
    }

    /**
     * @return array{full_name: string, document: string, address: string, phone: string, email: string}
     */
    private static function clientFromCollection(Collection $collection): array
    {
        // Las corporativas guardan la dirección en `address` (no existe `adress_ti`): antes salía vacía.
        $address = self::isCorporate($collection)
            ? AffiliationCorporate::query()->where('code', $collection->affiliation_code)->value('address')
            : Affiliation::query()->where('code', $collection->affiliation_code)->value('adress_ti');

        return [
            'full_name' => trim((string) ($collection->affiliate_full_name ?? '')),
            'document' => trim((string) ($collection->affiliate_ci_rif ?? '')),
            'address' => trim((string) ($address ?? '')),
            'phone' => trim((string) ($collection->affiliate_phone ?? '')),
            'email' => trim((string) ($collection->affiliate_email ?? '')),
        ];
    }

    /**
     * Los mismos datos que siempre recibió la plantilla, con el cliente editado, la emisión y el período.
     *
     * @param  array{full_name: string, document: string, address: string, phone: string, email: string}  $client
     * @return array<string, mixed>
     */
    private function documentData(Collection $collection, array $client, CarbonImmutable $emission, ?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        $corporate = self::isCorporate($collection);

        $data = [
            'invoice_number' => $collection->collection_invoice_number,
            'emission_date' => $emission->format(self::DATE_FORMAT),
            'full_name_ti' => $client['full_name'],
            'ci_rif_ti' => $client['document'],
            'address_ti' => $client['address'],
            'phone_ti' => $client['phone'],
            'email_ti' => $client['email'],
            'total_amount' => $collection->total_amount,
            'coverage' => $collection->coverage?->price,
            'frequency' => $collection->payment_frequency,
            'affiliates_count' => AffiliationDocumentAffiliatesCount::forAffiliationCode($collection->affiliation_code, $corporate),
            'period_label' => $from !== null && $to !== null ? self::periodLabel($from, $to) : null,
        ];

        if ($corporate) {
            $affiliation = AffiliationCorporate::query()
                ->where('code', $collection->affiliation_code)
                ->with('affiliationCorporatePlans')
                ->first();

            $data['plan'] = $affiliation?->affiliationCorporatePlans?->toArray() ?? [];
        } else {
            $data['plan'] = $collection->plan?->description;
        }

        return $data;
    }

    /**
     * Escribe a un temporal y lo mueve: si el dibujo falla, el PDF anterior queda intacto y nadie
     * descarga un archivo a medias. El disco `public` es el mismo directorio que `public/storage`.
     *
     * @param  array<string, mixed>  $data
     */
    private function writePdf(Collection $collection, array $data): void
    {
        $disk = Storage::disk('public');
        $relative = self::PDF_DIRECTORY.'/'.self::pdfFilename($collection);
        $temporary = $relative.'.'.bin2hex(random_bytes(4)).'.tmp';

        try {
            $view = self::isCorporate($collection) ? 'documents.aviso-de-cobro-corporativo' : 'documents.aviso-de-cobro';
            $binary = Pdf::loadView($view, ['data' => $data])->output();

            if ($binary === '' || $disk->put($temporary, $binary) !== true || $disk->move($temporary, $relative) !== true) {
                throw new RuntimeException('No se pudo guardar el aviso de cobro '.self::pdfFilename($collection).'.');
            }
        } catch (Throwable $exception) {
            $disk->delete($temporary);

            throw $exception;
        }
    }

    private static function requiredDate(mixed $value, string $field, string $label): CarbonImmutable
    {
        $date = self::parseDate((string) $value);

        if ($date === null) {
            throw ValidationException::withMessages([$field => 'Escriba '.$label.' con el formato dd/mm/aaaa.']);
        }

        return $date;
    }

    private static function parseDate(string $value): ?CarbonImmutable
    {
        $value = trim($value);

        if (! preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!'.self::DATE_FORMAT, $value);

        return $date !== false && $date->format(self::DATE_FORMAT) === $value ? $date : null;
    }

    private static function parseIsoDate(string $value): ?CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value))) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', trim($value));

        return $date !== false ? $date : null;
    }
}
