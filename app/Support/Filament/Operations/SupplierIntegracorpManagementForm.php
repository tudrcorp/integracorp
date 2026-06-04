<?php

declare(strict_types=1);

namespace App\Support\Filament\Operations;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;

final class SupplierIntegracorpManagementForm
{
    public static function formTab(string $sectionCardClass, string $innerCardClass): Tab
    {
        return Tab::make('Gestion de Procesos en Integracorp')
            ->icon(Heroicon::OutlinedCog6Tooth)
            ->visible(fn (): bool => OperationsSuperAdmin::check())
            ->schema([
                Section::make('Acceso a módulos de Operaciones')
                    ->description('Habilita al proveedor para operar en telemedicina, servicios médicos y órdenes de servicio.')
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->extraAttributes([
                        'class' => $sectionCardClass,
                    ])
                    ->schema([
                        Placeholder::make('integracorp_modules_panel')
                            ->hiddenLabel()
                            ->content(fn (): \Illuminate\Support\HtmlString => SupplierIntegracorpManagement::modulesPanelHtml())
                            ->columnSpanFull(),
                        Grid::make(1)
                            ->extraAttributes([
                                'class' => $innerCardClass,
                            ])
                            ->schema([
                                Toggle::make('gestion_integracorp')
                                    ->label('Habilitar gestión en Integracorp')
                                    ->helperText('Se guardará al crear o actualizar el proveedor.')
                                    ->default(false)
                                    ->live()
                                    ->inline(false)
                                    ->onIcon('heroicon-s-check')
                                    ->onColor('success')
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                        SupplierIntegracorpManagement::portalUsersRepeater($innerCardClass),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function stripUnauthorizedFormData(array $data): array
    {
        if (! OperationsSuperAdmin::check()) {
            unset($data['gestion_integracorp'], $data['integracorpUsers']);
        }

        return $data;
    }
}
