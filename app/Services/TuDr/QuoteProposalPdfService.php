<?php

declare(strict_types=1);

namespace App\Services\TuDr;

use App\Exceptions\IncompleteQuoteDetailException;
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
 * tome el relevo: el usuario nunca se queda sin propuesta. Todo descarte se
 * registra —diagnosticar un fallback silencioso cuesta horas.
 */
class QuoteProposalPdfService
{
    public function __construct(private readonly QuoteApiClient $client) {}

    /**
     * Planes que el microservicio sabe dibujar. Los paquetes armados con el
     * asistente siguen por el generador local.
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
     * Cotización de un solo plan.
     *
     * @param  array<string, mixed>  $details  payload que ya usa el generador local
     *
     * @throws TarifaNoDisponibleException cuando falta una tarifa: el motivo se muestra al usuario
     */
    public function generate(int $quoteId, string $scope, array $details): bool
    {
        return $this->renderDocument($quoteId, $scope, $details, [(int) ($details['plan'] ?? 0)]);
    }

    /**
     * Cotización multiplan: el servicio dibuja una página de cálculos por plan
     * y las deja en el mismo documento, con su portada y su contraportada.
     *
     * @param  list<array<string, mixed>>  $groupDetails  un elemento por plan, ya ordenados
     *
     * @throws TarifaNoDisponibleException
     */
    public function generateMultiple(int $quoteId, string $scope, array $groupDetails): bool
    {
        if ($groupDetails === []) {
            return false;
        }

        $planIds = array_values(array_unique(array_map(
            static fn (array $details): int => (int) ($details['plan'] ?? 0),
            $groupDetails,
        )));

        sort($planIds);

        return $this->renderDocument($quoteId, $scope, $groupDetails[0], $planIds);
    }

    /**
     * @param  array<string, mixed>  $base  datos comunes del documento
     * @param  list<int>  $planIds
     *
     * @throws TarifaNoDisponibleException
     */
    private function renderDocument(int $quoteId, string $scope, array $base, array $planIds): bool
    {
        if (! $this->enabled()) {
            Log::debug('quote-pdf: integración desactivada, se usa el generador local', [
                'code' => $base['code'] ?? null,
            ]);

            return false;
        }

        $code = trim((string) ($base['code'] ?? ''));

        if ($code === '') {
            Log::warning('quote-pdf: la cotización no tiene código, se usa el generador local');

            return false;
        }

        /**
         * Todos los planes deben ser dibujables. Si uno solo no lo es, el
         * documento saldría incompleto: mejor que lo arme entero el generador
         * local que entregar al cliente una propuesta a la que le falta un plan.
         */
        $noSoportados = array_values(array_filter(
            $planIds,
            static fn (int $planId): bool => ! self::supportsPlan($planId),
        ));

        if ($noSoportados !== []) {
            Log::warning('quote-pdf: hay planes que el servicio no dibuja, se usa el generador local', [
                'code' => $code,
                'plan_ids' => $planIds,
                'no_soportados' => $noSoportados,
            ]);

            return false;
        }

        try {
            $payload = $this->buildPayload($quoteId, $scope, $code, $base, $planIds);
        } catch (IncompleteQuoteDetailException $exception) {
            Log::warning('quote-pdf: detalle incompleto, se usa el generador local', [
                'code' => $code,
                'scope' => $scope,
                'plan_id' => $exception->planId,
                'rango' => $exception->range,
                'coberturas_esperadas' => $exception->expected,
                'coberturas_encontradas' => $exception->found,
            ]);

            return false;
        }

        if ($payload === null) {
            Log::warning('quote-pdf: la cotización no tiene líneas de detalle, se usa el generador local', [
                'code' => $code,
                'scope' => $scope,
                'plan_ids' => $planIds,
            ]);

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

        return $this->store($code, $document, $planIds);
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  list<int>  $planIds
     * @return array<string, mixed>|null
     */
    private function buildPayload(int $quoteId, string $scope, string $code, array $base, array $planIds): ?array
    {
        $titular = (string) ($base['name'] ?? '');
        $agente = (string) ($base['agent_name'] ?? '');
        $fecha = $this->formatDate((string) ($base['date'] ?? ''));

        return $scope === QuoteDocumentLayout::SCOPE_CORPORATE
            ? QuoteRenderPayload::forCorporateQuote($quoteId, $code, $titular, $agente, $fecha, $planIds)
            : QuoteRenderPayload::forIndividualQuote($quoteId, $code, $titular, $agente, $fecha, $planIds);
    }

    /**
     * @param  list<int>  $planIds
     */
    private function store(string $code, string $document, array $planIds): bool
    {
        $directory = public_path('storage/quotes');

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            Log::warning('quote-pdf: no se pudo preparar el directorio de propuestas', [
                'directory' => $directory,
            ]);

            return false;
        }

        $written = file_put_contents($directory.'/'.$code.'.pdf', $document);

        if ($written === false) {
            Log::warning('quote-pdf: no se pudo escribir la propuesta en disco', [
                'code' => $code,
            ]);

            return false;
        }

        Log::info('quote-pdf: propuesta generada', [
            'code' => $code,
            'control' => QuoteControlNumber::fromCode($code),
            'plan_ids' => $planIds,
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
