<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\DoctorNurse;
use App\Models\Supplier;
use Closure;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

final class OperationServiceOrderProviderFormFields
{
    /**
     * @return array<int, mixed>
     */
    public static function gridSchema(): array
    {
        return self::selectionComponents();
    }

    /**
     * Selección de proveedor existente o activación de registro (sin formulario inline).
     *
     * @return array<int, mixed>
     */
    public static function selectionComponents(?Closure $toggleAfterStateUpdated = null): array
    {
        return [
            Placeholder::make('provider_selection_hint')
                ->hiddenLabel()
                ->content(new HtmlString(
                    '<p class="text-sm text-gray-600 dark:text-gray-300">'
                    .'Seleccione <strong class="text-gray-900 dark:text-white">un solo proveedor</strong> '
                    .'por orden, o active <strong class="text-gray-900 dark:text-white">Proveedor No Convenido</strong> '
                    .'para registrar uno nuevo en el siguiente paso del asistente.'
                    .'</p>'
                ))
                ->columnSpanFull(),

            Grid::make(2)
                ->schema([
                    Select::make('doctor_nurse_id')
                        ->label('Proveedor natural')
                        ->options(fn (): array => DoctorNurse::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->disabled(fn (Get $get): bool => (bool) $get('register_unregistered_provider'))
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            if (! filled($state)) {
                                return;
                            }

                            $set('supplier_id', null);
                            $set('register_unregistered_provider', false);
                        })
                        ->prefixIcon(Heroicon::OutlinedUser)
                        ->native(false),

                    Select::make('supplier_id')
                        ->label('Proveedor jurídico')
                        ->options(fn (): array => Supplier::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->disabled(fn (Get $get): bool => (bool) $get('register_unregistered_provider'))
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            if (! filled($state)) {
                                return;
                            }

                            $set('doctor_nurse_id', null);
                            $set('register_unregistered_provider', false);
                        })
                        ->prefixIcon(Heroicon::OutlinedBuildingOffice2)
                        ->native(false),
                ])
                ->columnSpanFull(),

            OperationServiceOrderUnregisteredProviderFormFields::registerToggle($toggleAfterStateUpdated),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function components(?Closure $toggleAfterStateUpdated = null): array
    {
        return self::selectionComponents($toggleAfterStateUpdated);
    }
}
