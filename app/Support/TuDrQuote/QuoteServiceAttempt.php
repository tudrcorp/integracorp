<?php

declare(strict_types=1);

namespace App\Support\TuDrQuote;

use App\Exceptions\TarifaNoDisponibleException;
use App\Models\User;
use App\Services\TuDr\QuoteProposalPdfService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Intento de generar la propuesta con el microservicio.
 *
 * Devuelve `true` solo si el PDF quedó escrito. Ante cualquier otra cosa
 * devuelve `false` y el generador local sigue su curso, de modo que el flujo
 * de cotización se comporta como siempre aunque el servicio no esté.
 */
final class QuoteServiceAttempt
{
    /**
     * Cotización de un solo plan.
     *
     * @param  array<string, mixed>  $details
     */
    public static function generate(int $quoteId, string $scope, array $details): bool
    {
        return self::run(
            $quoteId,
            $scope,
            $details,
            static fn (QuoteProposalPdfService $service): bool => $service->generate($quoteId, $scope, $details),
        );
    }

    /**
     * Cotización multiplan: un solo documento con la página de cálculos de
     * cada plan, en vez de la propuesta múltiple que armaba DomPDF.
     *
     * @param  list<array<string, mixed>>  $groupDetails
     */
    public static function generateMultiple(int $quoteId, string $scope, array $groupDetails): bool
    {
        if ($groupDetails === []) {
            return false;
        }

        return self::run(
            $quoteId,
            $scope,
            $groupDetails[0],
            static fn (QuoteProposalPdfService $service): bool => $service->generateMultiple($quoteId, $scope, $groupDetails),
        );
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  callable(QuoteProposalPdfService): bool  $generator
     */
    private static function run(int $quoteId, string $scope, array $details, callable $generator): bool
    {
        $service = app(QuoteProposalPdfService::class);

        if (! $service->enabled()) {
            return false;
        }

        try {
            $generated = $generator($service);
        } catch (TarifaNoDisponibleException $exception) {
            /** No es un fallo del sistema: el usuario debe leer el motivo. */
            self::notify(
                Notification::make()
                    ->title('Faltan tarifas para esta propuesta')
                    ->body($exception->motivos())
                    ->warning()
            );

            return false;
        } catch (Throwable $exception) {
            Log::error('quote-pdf: fallo inesperado, se usa el generador local', [
                'code' => $details['code'] ?? null,
                'scope' => $scope,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        if (! $generated) {
            return false;
        }

        $code = (string) ($details['code'] ?? '');

        self::notify(
            Notification::make()
                ->title('¡TAREA COMPLETADA!')
                ->body('📎 '.$code.'.pdf ya se encuentra disponible para su descarga.')
                ->success()
                ->actions([
                    Action::make('download')
                        ->label('Descargar archivo')
                        ->url('/storage/quotes/'.$code.'.pdf'),
                ])
        );

        return true;
    }

    private static function notify(Notification $notification): void
    {
        $user = Auth::user();

        if ($user instanceof User) {
            $notification->sendToDatabase($user);

            return;
        }

        $notification->send();
    }
}
