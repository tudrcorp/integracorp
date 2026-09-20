<?php

declare(strict_types=1);

namespace App\Services\TuDr;

use App\Exceptions\QuoteServiceUnavailableException;
use App\Exceptions\TarifaNoDisponibleException;
use App\Support\TuDrQuote\QuoteControlNumber;
use App\Support\TuDrQuote\QuoteDocumentAssembler;
use App\Support\TuDrQuote\QuoteDocumentLayout;
use App\Support\TuDrQuote\QuoteFeeMatrix;
use App\Support\TuDrQuote\QuoteRenderPayload;
use Illuminate\Support\Facades\Log;

/**
 * Genera la Propuesta Económica con el microservicio quote-pdf.
 *
 * Devuelve `true` solo si el documento quedó escrito donde el portal lo
 * espera. Cualquier otro caso devuelve `false` para que el generador local
 * tome el relevo: el usuario nunca se queda sin propuesta.
 */
class QuoteProposalPdfService
{
    public function __construct(private readonly QuoteApiClient $client) {}

    /**
     * Planes que el microservicio sabe dibujar. El resto —paquetes nuevos,
     * cotizaciones multiplan— sigue por el generador local.
     *
     * @var list<int>
     */
    public const SUPPORTED_PLAN_IDS = [1, 2, 3];

    public function enabled(): bool
    {
        return $this->client->enabled();
    }

    public static function supportsPlan(int|string|null $plan): bool
    {
        return is_numeric($plan) && in_array((int) $plan, self::SUPPORTED_PLAN_IDS, true);
    }

    /**
     * @param  array<string, mixed>  $details  payload que ya usa el generador local
     *
     * @throws TarifaNoDisponibleException cuando falta una tarifa: el motivo se muestra al usuario
     */
    public function generate(
        int $quoteId,
        string $scope,
        array $details,
    ): bool {
        if (! $this->enabled() || ! self::supportsPlan($details['plan'] ?? null)) {
            return false;
        }

        $code = (string) ($details['code'] ?? '');

        if ($code === '') {
            return false;
        }

        $payload = $scope === QuoteDocumentLayout::SCOPE_CORPORATE
            ? QuoteRenderPayload::forCorporateQuote(
                $quoteId,
                $code,
                (string) ($details['name'] ?? ''),
                (string) ($details['agent_name'] ?? ''),
                $this->formatDate((string) ($details['date'] ?? '')),
                [(int) $details['plan']],
            )
            : QuoteRenderPayload::forIndividualQuote(
                $quoteId,
                $code,
                (string) ($details['name'] ?? ''),
                (string) ($details['agent_name'] ?? ''),
                $this->formatDate((string) ($details['date'] ?? '')),
                [(int) $details['plan']],
            );

        if ($payload === null) {
            return false;
        }

        /** El portal manda su matriz: el servicio no debe usar tarifas propias. */
        $payload['tarifas'] = QuoteFeeMatrix::all();

        try {
            $calculations = $this->client->render($payload);
        } catch (TarifaNoDisponibleException $exception) {
            throw $exception;
        } catch (QuoteServiceUnavailableException $exception) {
            Log::warning('quote-pdf: no disponible, se usa el generador local', [
                'code' => $code,
                'scope' => $scope,
                'status' => $exception->statusCode,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        /** Una página de cálculos por plan cotizado. */
        $document = QuoteDocumentAssembler::apply(
            $calculations,
            count($payload['planes']),
            $scope,
        );

        return $this->store($code, $document);
    }

    private function store(string $code, string $document): bool
    {
        $directory = public_path('storage/quotes');

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            return false;
        }

        $written = file_put_contents($directory.'/'.$code.'.pdf', $document);

        if ($written === false) {
            return false;
        }

        Log::info('quote-pdf: propuesta generada', [
            'code' => $code,
            'control' => QuoteControlNumber::fromCode($code),
            'bytes' => $written,
        ]);

        return true;
    }

    /**
     * El portal guarda `d-m-Y`; el servicio espera `dd/mm/aaaa`.
     */
    private function formatDate(string $date): string
    {
        $date = trim($date);

        if ($date === '') {
            return now()->format('d/m/Y');
        }

        return str_replace('-', '/', $date);
    }
}
