<?php

declare(strict_types=1);

namespace App\Support\Filament\Operations;

use App\Models\Supplier;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

final class SupplierIntegracorpManagementTab
{
    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const INNER_CARD = 'rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function make(): Tab
    {
        return Tab::make('Gestion de Procesos en Integracorp')
            ->icon(Heroicon::OutlinedCog6Tooth)
            ->visible(fn (): bool => OperationsSuperAdmin::check())
            ->schema([
                Section::make('Acceso a módulos de Operaciones')
                    ->description('Habilita al proveedor para operar en telemedicina, servicios médicos y órdenes de servicio.')
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->extraAttributes([
                        'class' => self::SECTION_CARD,
                    ])
                    ->schema([
                        TextEntry::make('_integracorp_management_toggle')
                            ->hiddenLabel()
                            ->html()
                            ->state(fn (): string => '')
                            ->formatStateUsing(fn (Supplier $record): HtmlString => new HtmlString(
                                view('filament.operations.suppliers.gestion-integracorp-tab', [
                                    'supplier' => $record,
                                ])->render()
                            ))
                            ->columnSpanFull(),
                        RepeatableEntry::make('integracorpUsers')
                            ->label('Usuarios de acceso')
                            ->visible(fn (Supplier $record): bool => (bool) $record->gestion_integracorp)
                            ->placeholder('No hay usuarios de acceso registrados.')
                            ->extraAttributes([
                                'class' => self::INNER_CARD,
                            ])
                            ->table([
                                TableColumn::make('Nombre'),
                                TableColumn::make('Correo'),
                                TableColumn::make('Estatus'),
                            ])
                            ->schema([
                                TextEntry::make('name'),
                                TextEntry::make('email'),
                                TextEntry::make('status')
                                    ->badge(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
