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
     * @return array{ok: true, draft: array{id: string, control: string, total: string, plan: string, people: int, caption: string}}|array{ok: false, reason: string, detail: string}
     */
    public function preview(CrmHandoffEnvelope $envelope, string $holder, string $ages, string $plan, string $coverage, string $agent): array
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
        $amount = $this->coverage($plan, $coverage);

        if ($holder === null) {
            return $this->fail('invalid', 'Escribe el nombre del titular.');
        }

        if ($parsedAges === null) {
            return $this->fail('invalid', 'Escribe hasta '.self::MAX_PEOPLE.' edades, entre 0 y 99.');
        }

        if (! isset(self::PLANS[$plan])) {
            return $this->fail('invalid', 'Elige Inicial, Ideal o Especial.');
        }

        if ($amount === false) {
            return $this->fail('invalid', 'Elige el monto de cobertura.');
        }

        $payload = [
            'titular' => $holder,
            'edades' => $parsedAges,
            'planes' => [$plan],
            'agente' => $agent !== '' ? $agent : 'SolIA',
        ];

        if (is_int($amount)) {
            $payload['cobertura'] = $amount;
        }

        try {
            $data = $this->quotes->cotizar($payload);
        } catch (TarifaNoDisponibleException $exception) {
            $detail = $exception->motivos();

            return $this->fail('no_tariff', $detail !== '' ? $detail : 'No hay tarifa para esas edades.');
        } catch (QuoteServiceUnavailableException $exception) {
            Log::warning('CRM cotización: el servicio no respondió', [
                'status' => $exception->statusCode,
            ]);

            return $this->fail('unavailable', $this->unavailableDetail($exception));
        }

        $pdf = base64_decode((string) ($data['pdf_base64'] ?? ''), true);
        $control = trim((string) ($data['control'] ?? ''));
        $total = $this->total(is_array($data['planes'] ?? null) ? $data['planes'] : [], $plan, $amount);

        if (! is_string($pdf) || ! str_starts_with($pdf, '%PDF') || strlen($pdf) > 8_000_000 || $control === '' || $total === null) {
            return $this->fail('unavailable', 'El cotizador no devolvió la propuesta.');
        }

        $id = (string) Str::uuid();
        Storage::disk('local')->put(self::path($id), $pdf);

        $currency = trim((string) config('crm-inbox.quote_currency', 'USD'));
        $caption = 'Propuesta '.$control.' · '.($currency !== '' ? $currency.' ' : '').$total.' al año';

        return [
            'ok' => true,
            'draft' => [
                'id' => $id,
                'control' => $control,
                'total' => $total,
                'plan' => self::PLANS[$plan],
                'people' => count($parsedAges),
                'caption' => $caption,
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
     */
    private function total(array $plans, string $plan, int|false|null $amount): ?string
    {
        $row = null;

        foreach ($plans as $candidate) {
            if (is_array($candidate) && strtolower((string) ($candidate['plan'] ?? '')) === $plan) {
                $row = $candidate;
                break;
            }
        }

        if ($row === null && isset($plans[0]) && is_array($plans[0])) {
            $row = $plans[0];
        }

        if ($row === null) {
            return null;
        }

        $value = $row['total_anual'] ?? null;

        if (is_int($amount)) {
            $coverages = is_array($row['coberturas_usd'] ?? null) ? $row['coberturas_usd'] : [];
            $totals = is_array($row['grupal_anual'] ?? null) ? $row['grupal_anual'] : [];
            $index = array_search($amount, array_map(intval(...), $coverages), true);

            if ($index !== false && isset($totals[$index]) && is_numeric($totals[$index])) {
                $value = $totals[$index];
            }
        }

        if (! is_numeric($value)) {
            return null;
        }

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

    private function coverage(string $plan, string $coverage): int|false|null
    {
        if (! isset(self::COVERAGES[$plan])) {
            return null;
        }

        $coverage = trim($coverage);

        if ($coverage === '' || ! ctype_digit($coverage)) {
            return false;
        }

        $amount = (int) $coverage;

        return in_array($amount, self::COVERAGES[$plan], true) ? $amount : false;
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
