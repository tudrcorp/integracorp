<?php

declare(strict_types=1);

namespace App\Support\Plans;

use App\Enums\PlanQuotableScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

/**
 * Campos que solo ve el superadmin, y solo en planes Dress Tylor.
 */
final class PlanQuotabilityFormSchema
{
    /**
     * @return list<Section>
     */
    public static function section(): array
    {
        return [
            Section::make('Cotización Dress Tylor')
                ->description('Solo el superadmin decide si este plan aparece en cotización individual, corporativa o en ambas. Si está apagado, el plan no se ofrece en ningún cotizador.')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->visible(fn (Get $get): bool => PlanQuotability::currentUserCanConfigure()
                    && PlanQuotability::isDressTylor($get('type')))
                ->schema([
                    Toggle::make('is_quotable')
                        ->label('Plan cotizable')
                        ->helperText('Actívelo para que el plan pueda elegirse al armar una cotización.')
                        ->default(false)
                        ->live()
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            if ($state) {
                                $set('quotable_in', PlanQuotableScope::Both->value);

                                return;
                            }

                            $set('quotable_in', null);
                        }),
                    Select::make('quotable_in')
                        ->label('Dónde se puede cotizar')
                        ->options(PlanQuotableScope::options())
                        ->default(PlanQuotableScope::Both->value)
                        ->required(fn (Get $get): bool => (bool) $get('is_quotable'))
                        ->visible(fn (Get $get): bool => (bool) $get('is_quotable'))
                        ->helperText('Individual, corporativo o ambos canales.'),
                ])
                ->columnSpanFull(),
        ];
    }

    public static function syncTypeChange(Set $set, mixed $type): void
    {
        if (PlanQuotability::isDressTylor($type)) {
            return;
        }

        $set('is_quotable', false);
        $set('quotable_in', null);
    }
}
