<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationQuoteGenerator;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class CoordinationServiceQuoteEditAction
{
    public static function make(): Action
    {
        return Action::make('editPendingQuote')
            ->label('Editar')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('warning')
            ->modalIcon(Heroicon::OutlinedPencilSquare)
            ->modalHeading(fn (array $arguments): string => 'Editar cotización #'.self::quoteIdFromArguments($arguments))
            ->modalDescription('Ajuste proveedor, precios y observaciones antes de aprobar la cotización.')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Guardar cotización')
            ->modalCancelActionLabel('Cancelar')
            ->modalSubmitAction(
                fn (Action $action): Action => $action->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                ])
            )
            ->modalCancelAction(
                fn (Action $action): Action => $action->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                ])
            )
            ->fillForm(fn (array $arguments): array => CoordinationServiceQuoteEditForm::defaults(
                self::resolvePendingQuote($arguments)
            ))
            ->form(CoordinationServiceQuoteEditForm::schema())
            ->successNotification(
                Notification::make()
                    ->success()
                    ->title('Cotización actualizada')
                    ->body('Los cambios se guardaron y el PDF se regeneró.')
            );
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public static function quoteIdFromArguments(array $arguments): int
    {
        return (int) ($arguments['quoteId'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public static function resolvePendingQuote(array $arguments): OperationQuoteGenerator
    {
        $quoteId = self::quoteIdFromArguments($arguments);

        if ($quoteId <= 0) {
            throw (new ModelNotFoundException)->setModel(OperationQuoteGenerator::class, [$quoteId]);
        }

        $quote = OperationQuoteGenerator::query()->find($quoteId);

        if (! $quote instanceof OperationQuoteGenerator || ! CoordinationServiceQuoteManager::isQuotePendingForApproval($quote)) {
            throw (new ModelNotFoundException)->setModel(OperationQuoteGenerator::class, [$quoteId]);
        }

        return $quote;
    }
}
