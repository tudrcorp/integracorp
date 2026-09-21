<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\QuoteServiceUnavailableException;
use App\Exceptions\TarifaNoDisponibleException;
use App\Http\Requests\QuoteProposalRequest;
use App\Models\CorporateQuote;
use App\Models\IndividualQuote;
use App\Services\TuDr\QuoteApiClient;
use App\Support\TuDrQuote\QuoteControlNumber;
use App\Support\TuDrQuote\QuoteFeeMatrix;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vista previa de la Propuesta Económica para el panel de negocios.
 *
 * El navegador nunca habla con el microservicio: la clave vive solo aquí. El
 * PDF se guarda en disco privado y se sirve por su número de control.
 */
class QuoteProposalController extends Controller
{
    private const PREVIEW_CACHE_PREFIX = 'tudr-quote:preview:';

    private const PREVIEW_TTL_MINUTES = 120;

    private const PREVIEW_DIRECTORY = 'propuestas';

    public function cotizar(QuoteProposalRequest $request, QuoteApiClient $client): JsonResponse
    {
        if (! $client->enabled()) {
            return response()->json([
                'ok' => false,
                'error' => 'El servicio de cotización no está habilitado.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $control = $this->resolveControl($request->validated('code'));

        $payload = [
            'titular' => (string) $request->validated('titular'),
            'edades' => $request->afiliados(),
            'planes' => $request->planes(),
            'agente' => (string) (Auth::user()?->name ?? ''),
            'control' => $control,
            'fecha' => now()->format('d/m/Y'),
            'tarifas' => QuoteFeeMatrix::all(),
        ];

        if ($request->validated('cobertura') !== null) {
            $payload['cobertura'] = (int) $request->validated('cobertura');
        }

        try {
            $result = $client->cotizar($payload);
        } catch (TarifaNoDisponibleException $exception) {
            return response()->json([
                'ok' => false,
                'error' => $exception->getMessage(),
                'faltantes' => $exception->faltantes,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (QuoteServiceUnavailableException $exception) {
            return response()->json([
                'ok' => false,
                'error' => 'El servicio de cotización no está disponible en este momento.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $this->storePreview($control, (string) ($result['pdf_base64'] ?? ''));

        return response()->json([
            'ok' => true,
            'control' => $result['control'] ?? $control,
            'planes' => $result['planes'] ?? [],
            'resumen' => $result['resumen'] ?? null,
            'faltantes' => $result['faltantes'] ?? [],
            'pdf_url' => route('propuestas.pdf', ['control' => $control]),
        ]);
    }

    public function pdf(Request $request, string $control): Response
    {
        $control = QuoteControlNumber::fromCode($control);
        $path = self::PREVIEW_DIRECTORY.'/'.$control.'.pdf';

        abort_unless($this->userCanSee($control), Response::HTTP_FORBIDDEN);
        abort_unless(Storage::disk('local')->exists($path), Response::HTTP_NOT_FOUND);

        return response(Storage::disk('local')->get($path), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Propuesta-'.$control.'.pdf"',
        ]);
    }

    /**
     * Un `code` de cotización existente conserva su consecutivo; sin él, la
     * vista previa usa un control propio para no gastar numeración oficial.
     */
    private function resolveControl(?string $code): string
    {
        if ($code !== null && trim($code) !== '') {
            $quote = IndividualQuote::query()->where('code', trim($code))->first();

            if ($quote !== null) {
                return QuoteControlNumber::fromCode((string) $quote->code);
            }
        }

        do {
            $control = QuoteControlNumber::fromCode((string) random_int(1, 9999999));
        } while (Cache::has(self::PREVIEW_CACHE_PREFIX.$control));

        return $control;
    }

    private function storePreview(string $control, string $base64): void
    {
        if ($base64 === '') {
            return;
        }

        $pdf = base64_decode($base64, true);

        if ($pdf === false) {
            return;
        }

        Storage::disk('local')->put(self::PREVIEW_DIRECTORY.'/'.$control.'.pdf', $pdf);

        Cache::put(
            self::PREVIEW_CACHE_PREFIX.$control,
            (int) Auth::id(),
            now()->addMinutes(self::PREVIEW_TTL_MINUTES),
        );
    }

    /**
     * Solo ve la propuesta quien la generó, o quien es dueño de la cotización
     * a la que pertenece ese control.
     *
     * El código se compara completo —`COT-IND-0004011`—, nunca por sufijo: un
     * `like '%4011'` dejaría que el dueño de la 0014011 abriese la 0004011.
     */
    private function userCanSee(string $control): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        if ((int) Cache::get(self::PREVIEW_CACHE_PREFIX.$control) === (int) $user->id) {
            return true;
        }

        /** `created_by` guarda el nombre del usuario, no su id. */
        $esSuya = static fn (Builder $query): Builder => $query
            ->where('created_by', (string) $user->name)
            ->orWhere('storefront_user_id', $user->id);

        $individual = IndividualQuote::query()
            ->where('code', 'COT-IND-'.$control)
            ->where($esSuya)
            ->exists();

        if ($individual) {
            return true;
        }

        return CorporateQuote::query()
            ->where('code', 'COT-CORP-'.$control)
            ->where('created_by', (string) $user->name)
            ->exists();
    }
}
