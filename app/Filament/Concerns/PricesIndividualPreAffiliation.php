<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Models\Affiliation;
use App\Support\Affiliations\IndividualPreAffiliationPricing;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Pre-afiliación individual de la red comercial (agentes, master y general): montos calculados en
 * el servidor por edad ({@see IndividualPreAffiliationPricing}) y, si algo falla, nada queda a
 * medias: se revierte la transacción del formulario y el agente ve el motivo.
 */
trait PricesIndividualPreAffiliation
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function priceIndividualPreAffiliationData(array $data): array
    {
        try {
            return IndividualPreAffiliationPricing::make()->applyToFormData($data);
        } catch (ValidationException $exception) {
            $this->haltIndividualPreAffiliation(collect($exception->errors())->flatten()->implode(' '));
        }
    }

    protected function priceIndividualPreAffiliation(Affiliation $affiliation): void
    {
        try {
            IndividualPreAffiliationPricing::make()->applyToAffiliation($affiliation);
        } catch (ValidationException $exception) {
            $this->haltIndividualPreAffiliation(collect($exception->errors())->flatten()->implode(' '));
        }
    }

    /**
     * Error inesperado al crear: se registra, se avisa y se revierte todo.
     *
     * @throws Halt
     */
    protected function failIndividualPreAffiliation(Throwable $exception): never
    {
        Log::error('Pre-afiliación individual: falla al crear', [
            'panel' => filament()->getCurrentPanel()?->getId(),
            'user_id' => auth()->id(),
            'exception' => $exception,
        ]);

        $this->haltIndividualPreAffiliation('Ocurrió un error inesperado y no se guardó nada. Intenta de nuevo; si se repite, contacta a soporte.');
    }

    /**
     * @throws Halt
     */
    protected function haltIndividualPreAffiliation(string $message): never
    {
        Notification::make()
            ->title('No se guardó la pre-afiliación')
            ->body($message)
            ->danger()
            ->persistent()
            ->send();

        throw (new Halt)->rollBackDatabaseTransaction();
    }
}
