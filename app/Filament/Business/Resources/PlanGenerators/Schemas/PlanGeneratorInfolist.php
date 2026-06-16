<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGenerators\Schemas;

use App\Models\PlanGenerator;
use App\Support\PlanGenerators\PlanGeneratorPreviewBuilder;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PlanGeneratorInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('planGeneratorInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Identificación')
                            ->icon(Heroicon::OutlinedIdentification)
                            ->schema([
                                Section::make('Identificación del plan')
                                    ->icon(Heroicon::OutlinedIdentification)
                                    ->description('Nombre, estatus y datos de auditoría del plan.')
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make()
                                                    ->columns(['default' => 1, 'lg' => 3])
                                                    ->schema([
                                                        TextEntry::make('name')
                                                            ->label('Nombre del plan')
                                                            ->weight('semibold')
                                                            ->columnSpan(['default' => 1, 'lg' => 2]),
                                                        TextEntry::make('status')
                                                            ->label('Estatus')
                                                            ->badge()
                                                            ->color(fn (string $state): string => $state === 'ACTIVO' ? 'success' : 'gray'),
                                                        TextEntry::make('created_by')
                                                            ->label('Creado por')
                                                            ->icon(Heroicon::OutlinedUser)
                                                            ->placeholder('—'),
                                                        TextEntry::make('created_at')
                                                            ->label('Creado')
                                                            ->icon(Heroicon::OutlinedClock)
                                                            ->dateTime('d/m/Y H:i')
                                                            ->placeholder('—'),
                                                        TextEntry::make('updated_at')
                                                            ->label('Actualizado')
                                                            ->icon(Heroicon::OutlinedArrowPath)
                                                            ->dateTime('d/m/Y H:i')
                                                            ->placeholder('—'),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Propuesta comercial')
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->schema([
                                Section::make('Propuesta comercial')
                                    ->icon(Heroicon::OutlinedDocumentText)
                                    ->description('Datos del cliente y contexto comercial del documento.')
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make()
                                                    ->columns(['default' => 1, 'lg' => 2])
                                                    ->schema([
                                                        TextEntry::make('control_number')
                                                            ->label('Nro. Control')
                                                            ->icon(Heroicon::OutlinedHashtag)
                                                            ->badge()
                                                            ->color('gray')
                                                            ->columnSpan(['default' => 1, 'lg' => 2]),
                                                        TextEntry::make('client_data')
                                                            ->label('Datos del cliente')
                                                            ->icon(Heroicon::OutlinedBuildingOffice2)
                                                            ->weight('semibold')
                                                            ->columnSpan(['default' => 1, 'lg' => 2]),
                                                        TextEntry::make('issued_at')
                                                            ->label('Fecha de emisión')
                                                            ->icon(Heroicon::OutlinedCalendarDays)
                                                            ->date('d/m/Y')
                                                            ->placeholder('—'),
                                                        TextEntry::make('agent_name')
                                                            ->label('Agente')
                                                            ->icon(Heroicon::OutlinedUser)
                                                            ->placeholder('—'),
                                                        TextEntry::make('population_summary')
                                                            ->label('Población')
                                                            ->icon(Heroicon::OutlinedUsers)
                                                            ->badge()
                                                            ->color('info')
                                                            ->placeholder('—')
                                                            ->columnSpan(['default' => 1, 'lg' => 2]),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Cuerpo de la cotización')
                            ->icon(Heroicon::OutlinedDocumentDuplicate)
                            ->schema([
                                Section::make('Cuerpo de la cotización')
                                    ->description('Configuración de páginas e imágenes que componen el PDF descargable.')
                                    ->icon(Heroicon::OutlinedPhoto)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make()
                                                    ->columns(['default' => 1, 'lg' => 3])
                                                    ->schema([
                                                        TextEntry::make('quotation_page_count')
                                                            ->label('Número de páginas')
                                                            ->badge()
                                                            ->color('info')
                                                            ->placeholder('—'),
                                                        TextEntry::make('plan_page_number')
                                                            ->label('Página del plan generado')
                                                            ->formatStateUsing(fn (?int $state): string => filled($state) ? "Página {$state}" : '—')
                                                            ->badge()
                                                            ->color('amber')
                                                            ->placeholder('—'),
                                                        TextEntry::make('quotationPages')
                                                            ->label('Imágenes cargadas')
                                                            ->state(fn (PlanGenerator $record): string => (string) $record->quotationPages()->count())
                                                            ->badge()
                                                            ->color('gray'),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Vista previa PDF')
                            ->icon(Heroicon::OutlinedDocumentArrowDown)
                            ->schema([
                                Section::make('Vista previa PDF')
                                    ->description('Genere el documento PDF con las matrices alineadas del plan.')
                                    ->icon(Heroicon::OutlinedDocumentText)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                View::make('filament.business.plan-generators.plan-pdf-trigger')
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Matrices del plan')
                            ->icon(Heroicon::OutlinedTableCells)
                            ->schema([
                                Section::make('Matrices del plan')
                                    ->description('Beneficios y tarifas con las mismas columnas del plan, en el mismo orden.')
                                    ->icon(Heroicon::OutlinedTableCells)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                View::make('filament.business.plan-generators.stacked-matrices-preview')
                                                    ->viewData(fn (PlanGenerator $record): array => [
                                                        'columns' => PlanGeneratorPreviewBuilder::columns($record),
                                                        'rows' => PlanGeneratorPreviewBuilder::rows($record),
                                                        'rateRows' => PlanGeneratorPreviewBuilder::rateRows($record),
                                                    ])
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
