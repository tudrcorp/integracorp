<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Exceptions\QuoteServiceUnavailableException;
use App\Exceptions\TarifaNoDisponibleException;
use App\Models\CrmHandoffEnvelope;
use App\Models\CrmHandoffMessage;
use App\Services\TuDr\QuoteApiClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Cotización corta dentro del chat. El microservicio calcula y dibuja el PDF;
 * aquí solo se guarda el archivo para enviarlo después por WhatsApp.
 */
final class CrmInboxQuote
{
    public const MAX_PEOPLE = 8;

    /**
     * @var array<string, string>
     */
    public const PLANS = [
        'inicial' => 'Inicial',
        'ideal' => 'Ideal',
        'especial' => 'Especial',
    ];

    /**
     * @var array<string, list<int>>
     */
    public const COVERAGES = [
        'ideal' => [1000, 2000, 3000, 5000, 10000],
        'especial' => [5000, 10000, 20000, 30000, 40000, 50000],
    ];

    public function __construct(private readonly QuoteApiClient $quotes) {}

    /**
     * Calcula la propuesta con las coberturas que pidió el cliente. Si son todas las del plan, el
     * microservicio la dibuja de una vez; si es una parte, primero se calcula sin PDF y luego se pide
     * el documento solo con esas columnas, para no mostrarle al cliente montos que no pidió.
     *
     * @param  list<int|string>  $coverages
     * @return array{ok: true, draft: array{id: string, control: string, total: string, plan: string, people: int, caption: string, coverages: list<int>}}|array{ok: false, reason: string, detail: string}
     */
    public function preview(CrmHandoffEnvelope $envelope, string $holder, string $ages, string $plan, array $coverages, string $agent): array
    {
        if ($envelope->taken_at === null || $envelope->released_at !== null || $envelope->closed_at !== null) {
            return $this->fail('not_taken', 'Toma el caso antes de cotizar.');
        }

        if (! $this->quotes->enabled()) {
            return $this->fail('unavailable', 'El cotizador no está disponible.');
        }

        $holder = $this->holder($holder);
        $parsedAges = $this->ages($ages);
        $plan = strtolower(trim($plan));
        $amounts = $this->coverages($plan, $coverages);

        if ($holder === null) {
            return $this->fail('invalid', 'Escribe el nombre del titular.');
        }

        if ($parsedAges === null) {
            return $this->fail('invalid', 'Escribe hasta '.self::MAX_PEOPLE.' edades, entre 0 y 99.');
        }

        if (! isset(self::PLANS[$plan])) {
            return $this->fail('invalid', 'Elige Inicial, Ideal o Especial.');
        }

        if ($amounts === false) {
            return $this->fail('invalid', 'Elige al menos una cobertura.');
        }

        $partial = $amounts !== [] && $amounts !== self::COVERAGES[$plan];

        $payload = [
            'titular' => $holder,
            'edades' => $parsedAges,
            'planes' => [$plan],
            'agente' => $agent !== '' ? $agent : 'SolIA',
        ];

        if ($amounts !== []) {
            $payload['cobertura'] = $amounts[0];
        }

        if ($partial) {
            $payload['formato'] = 'json_sin_pdf';
        }

        try {
            $data = $this->quotes->cotizar($payload);
            $control = trim((string) ($data['control'] ?? ''));
            $row = $this->planRow(is_array($data['planes'] ?? null) ? $data['planes'] : [], $plan);

            if ($partial) {
                $row = $row !== null ? $this->onlyCoverages([...$row, 'plan' => $plan], $amounts) : null;

                if ($row === null || $control === '') {
                    return $this->fail('unavailable', 'El cotizador no devolvió las coberturas elegidas. Intenta de nuevo.');
                }

                $pdf = $this->quotes->render([
                    'control' => $control,
                    'titular' => (string) ($data['titular'] ?? $holder),
                    'agente' => (string) ($data['agente'] ?? $payload['agente']),
                    'fecha' => (string) ($data['fecha'] ?? now()->format('d/m/Y')),
                    'planes' => [$row],
                ]);
            } else {
                $pdf = base64_decode((string) ($data['pdf_base64'] ?? ''), true);
            }
        } catch (TarifaNoDisponibleException $exception) {
            $detail = $exception->motivos();

            return $this->fail('no_tariff', $detail !== '' ? $detail : 'No hay tarifa para esas edades.');
        } catch (QuoteServiceUnavailableException $exception) {
            Log::warning('CRM cotización: el servicio no respondió', [
                'status' => $exception->statusCode,
            ]);

            return $this->fail('unavailable', $this->unavailableDetail($exception));
        }

        $total = $row !== null ? $this->total($row, $amounts) : null;

        if (! is_string($pdf) || ! str_starts_with($pdf, '%PDF') || strlen($pdf) > 8_000_000 || $control === '' || $total === null) {
            return $this->fail('unavailable', 'El cotizador no devolvió la propuesta.');
        }

        $id = (string) Str::uuid();
        Storage::disk('local')->put(self::path($id), $pdf);

        $currency = trim((string) config('crm-inbox.quote_currency', 'USD'));
        $prefix = $currency !== '' ? $currency.' ' : '';
        $caption = count($amounts) > 1
            ? 'Propuesta '.$control.' · '.count($amounts).' coberturas · desde '.$prefix.$total['from'].' hasta '.$prefix.$total['to'].' al año'
            : 'Propuesta '.$control.' · '.$prefix.$total['from'].' al año';

        return [
            'ok' => true,
            'draft' => [
                'id' => $id,
                'control' => $control,
                'total' => count($amounts) > 1 ? $total['from'].' – '.$total['to'] : $total['from'],
                'plan' => self::PLANS[$plan],
                'people' => count($parsedAges),
                'caption' => $caption,
                'coverages' => $amounts,
            ],
        ];
    }

    public static function path(string $id): string
    {
        return 'crm-quotes/'.$id.'.pdf';
    }

    public static function forget(string $id): void
    {
        if (! self::validId($id)) {
            return;
        }

        Storage::disk('local')->delete(self::path($id));
    }

    public static function forgetUnsent(string $id): void
    {
        if (! self::validId($id) || CrmHandoffMessage::query()->where('message_id', $id)->exists()) {
            return;
        }

        Storage::disk('local')->delete(self::path($id));
    }

    private static function validId(string $id): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id) === 1;
    }

    /**
     * @param  list<mixed>  $plans
     * @return array<string, mixed>|null
     */
    private function planRow(array $plans, string $plan): ?array
    {
        foreach ($plans as $candidate) {
            if (is_array($candidate) && strtolower((string) ($candidate['plan'] ?? '')) === $plan) {
                return $candidate;
            }
        }

        return isset($plans[0]) && is_array($plans[0]) ? $plans[0] : null;
    }

    /**
     * Deja en la fila del plan solo las columnas de las coberturas elegidas, en el formato de `/render`.
     *
     * @param  array<string, mixed>  $row
     * @param  list<int>  $amounts
     * @return array<string, mixed>|null
     */
    private function onlyCoverages(array $row, array $amounts): ?array
    {
        $offered = array_map(intval(...), is_array($row['coberturas_usd'] ?? null) ? $row['coberturas_usd'] : []);
        $labels = is_array($row['coberturas'] ?? null) ? $row['coberturas'] : [];
        $indexes = [];

        foreach ($amounts as $amount) {
            $index = array_search($amount, $offered, true);

            if ($index === false || ! isset($labels[$index])) {
                return null;
            }

            $indexes[] = $index;
        }

        $pick = static function (mixed $values) use ($indexes): ?array {
            if (! is_array($values)) {
                return null;
            }

            $picked = [];

            foreach ($indexes as $index) {
                if (! array_key_exists($index, $values) || ! is_numeric($values[$index])) {
                    return null;
                }

                $picked[] = $values[$index];
            }

            return $picked;
        };

        $rows = [];

        foreach (is_array($row['filas'] ?? null) ? $row['filas'] : [] as $fila) {
            $tarifas = is_array($fila) ? $pick($fila['tarifas'] ?? null) : null;

            if ($tarifas === null) {
                return null;
            }

            $rows[] = [
                'rango' => (string) ($fila['rango'] ?? ''),
                'poblacion' => (int) ($fila['poblacion'] ?? 1),
                'tarifas' => $tarifas,
            ];
        }

        $annual = $pick($row['grupal_anual'] ?? null);
        $semiannual = $pick($row['grupal_semestral'] ?? null);
        $quarterly = $pick($row['grupal_trimestral'] ?? null);

        if ($rows === [] || $annual === null || $semiannual === null || $quarterly === null) {
            return null;
        }

        return [
            'plan' => (string) $row['plan'],
            'coberturas_usd' => $amounts,
            'coberturas' => array_map(static fn (int $index): string => (string) $labels[$index], $indexes),
            'filas' => $rows,
            'grupal_anual' => $annual,
            'grupal_semestral' => $semiannual,
            'grupal_trimestral' => $quarterly,
        ];
    }

    /**
     * Total anual de la cobertura más baja y de la más alta elegidas (iguales si es una sola).
     *
     * @param  array<string, mixed>  $row
     * @param  list<int>  $amounts
     * @return array{from: string, to: string}|null
     */
    private function total(array $row, array $amounts): ?array
    {
        if ($amounts === []) {
            $value = $row['total_anual'] ?? null;

            return is_numeric($value) ? ['from' => $this->money($value), 'to' => $this->money($value)] : null;
        }

        $coverages = array_map(intval(...), is_array($row['coberturas_usd'] ?? null) ? $row['coberturas_usd'] : []);
        $totals = is_array($row['grupal_anual'] ?? null) ? $row['grupal_anual'] : [];
        $values = [];

        foreach ($amounts as $amount) {
            $index = array_search($amount, $coverages, true);

            if ($index === false || ! isset($totals[$index]) || ! is_numeric($totals[$index])) {
                return null;
            }

            $values[] = (float) $totals[$index];
        }

        return ['from' => $this->money(min($values)), 'to' => $this->money(max($values))];
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }

    private function holder(string $holder): ?string
    {
        $holder = trim(preg_replace('/\s+/u', ' ', $holder) ?? '');

        if (mb_strlen($holder) < 2 || mb_strlen($holder) > 80) {
            return null;
        }

        return $holder;
    }

    /**
     * @return list<int>|null
     */
    private function ages(string $raw): ?array
    {
        $parts = preg_split('/\D+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === false || $parts === [] || count($parts) > self::MAX_PEOPLE) {
            return null;
        }

        $ages = [];

        foreach ($parts as $part) {
            if (! ctype_digit($part) || (int) $part > 99) {
                return null;
            }

            $ages[] = (int) $part;
        }

        return $ages;
    }

    /**
     * Valida las coberturas contra las del plan y las devuelve sin repetir, en el orden del plan.
     * Lista vacía: el plan no tiene montos. `false`: falta elegir o llegó un monto ajeno al plan.
     *
     * @param  list<int|string>  $coverages
     * @return list<int>|false
     */
    private function coverages(string $plan, array $coverages): array|false
    {
        if (! isset(self::COVERAGES[$plan])) {
            return [];
        }

        $chosen = [];

        foreach ($coverages as $coverage) {
            $coverage = trim((string) $coverage);

            if ($coverage === '' || ! ctype_digit($coverage) || ! in_array((int) $coverage, self::COVERAGES[$plan], true)) {
                return false;
            }

            $chosen[(int) $coverage] = true;
        }

        $ordered = array_values(array_filter(self::COVERAGES[$plan], static fn (int $amount): bool => isset($chosen[$amount])));

        return $ordered === [] ? false : $ordered;
    }

    private function unavailableDetail(QuoteServiceUnavailableException $exception): string
    {
        return match ($exception->statusCode) {
            401 => 'El cotizador rechazó la clave. Avísale a sistemas.',
            null => 'No hay conexión con el cotizador. Intenta de nuevo.',
            default => 'El cotizador no respondió. Intenta de nuevo.',
        };
    }

    /**
     * @return array{ok: false, reason: string, detail: string}
     */
    private function fail(string $reason, string $detail): array
    {
        return ['ok' => false, 'reason' => $reason, 'detail' => $detail];
    }
}
