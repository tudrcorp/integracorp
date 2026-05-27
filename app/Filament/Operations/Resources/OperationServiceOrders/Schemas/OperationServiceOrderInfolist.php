<?php

namespace App\Filament\Operations\Resources\OperationServiceOrders\Schemas;

use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class OperationServiceOrderInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('operationServiceOrderInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Resumen')
                            ->icon(Heroicon::OutlinedClipboardDocumentList)
                            ->schema([
                                Section::make('Resumen de la orden')
                                    ->description('Información general y trazabilidad de la orden de servicio.')
                                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Datos principales')
                                            ->schema([
                                                TextEntry::make('order_number')
                                                    ->label('Nº de orden')
                                                    ->badge()
                                                    ->placeholder('-'),
                                                TextEntry::make('approvedOperationQuote.id')
                                                    ->label('Código cotización origen')
                                                    ->badge()
                                                    ->formatStateUsing(fn (mixed $state): string => filled($state)
                                                        ? 'COT-'.str_pad((string) ((int) $state), 6, '0', STR_PAD_LEFT)
                                                        : '—')
                                                    ->placeholder('-'),
                                                TextEntry::make('status')
                                                    ->label('Estado')
                                                    ->badge()
                                                    ->placeholder('-'),
                                                TextEntry::make('service_type')
                                                    ->label('Tipo de servicio')
                                                    ->badge()
                                                    ->placeholder('-'),
                                                TextEntry::make('operation_coordination_service_id')
                                                    ->label('ID coordinación')
                                                    ->numeric()
                                                    ->placeholder('-'),
                                                TextEntry::make('telemedicinePriority.name')
                                                    ->label('Prioridad')
                                                    ->badge()
                                                    ->placeholder('-'),
                                                TextEntry::make('operationInventoryUbication.name')
                                                    ->label('Ubicación de despacho')
                                                    ->placeholder('-'),
                                                TextEntry::make('total_items')
                                                    ->label('Total ítems')
                                                    ->numeric()
                                                    ->badge()
                                                    ->placeholder('-'),
                                                TextEntry::make('total_items_unit')
                                                    ->label('Total unidades')
                                                    ->numeric()
                                                    ->badge()
                                                    ->placeholder('-'),
                                            ])
                                            ->columns(4),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Comercial')
                            ->icon(Heroicon::OutlinedBuildingStorefront)
                            ->schema([
                                Section::make('Proveedor y descripción')
                                    ->description('Datos del proveedor interno/externo y detalle general del requerimiento.')
                                    ->icon(Heroicon::OutlinedBuildingStorefront)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Información comercial')
                                            ->schema([
                                                TextEntry::make('doctorNurse.name')
                                                    ->label('Proveedor natural')
                                                    ->placeholder('-'),
                                                TextEntry::make('supplier.name')
                                                    ->label('Proveedor jurídico')
                                                    ->placeholder('-'),
                                                TextEntry::make('supplier_external')
                                                    ->label('Proveedor No Convenido')
                                                    ->placeholder('-'),
                                                TextEntry::make('description')
                                                    ->label('Descripción')
                                                    ->placeholder('-')
                                                    ->columnSpanFull(),
                                                TextEntry::make('observations')
                                                    ->label('Observaciones')
                                                    ->placeholder('-')
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(2),
                                    ])
                                    ->columnSpanFull(),
                                Section::make('Montos y método de pago')
                                    ->description('Resumen financiero de la orden.')
                                    ->icon(Heroicon::OutlinedCurrencyDollar)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Totales')
                                            ->schema([
                                                TextEntry::make('currency')
                                                    ->label('Moneda')
                                                    ->badge()
                                                    ->placeholder('-'),
                                                TextEntry::make('payment_method')
                                                    ->label('Método de pago')
                                                    ->placeholder('-'),
                                                TextEntry::make('tasa_bcv')
                                                    ->label('Tasa BCV')
                                                    ->numeric()
                                                    ->placeholder('-'),
                                                TextEntry::make('total_amount_usd')
                                                    ->label('Total USD')
                                                    ->money('USD')
                                                    ->placeholder('-'),
                                                TextEntry::make('total_amount_ves')
                                                    ->label('Total VES')
                                                    ->money('VES')
                                                    ->placeholder('-'),
                                            ])
                                            ->columns(3),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Ítems')
                            ->icon(Heroicon::OutlinedQueueList)
                            ->schema([
                                Section::make('Ítems asociados')
                                    ->description('Detalle de medicamentos/servicios asociados a esta orden.')
                                    ->icon(Heroicon::OutlinedQueueList)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        RepeatableEntry::make('operationServiceOrderItems')
                                            ->label('Detalle de ítems')
                                            ->placeholder('La orden no posee ítems asociados.')
                                            ->table([
                                                TableColumn::make('Ítem'),
                                                TableColumn::make('Categoría'),
                                                TableColumn::make('Unidad'),
                                                TableColumn::make('Cantidad'),
                                                TableColumn::make('Indicaciones / dosis'),
                                            ])
                                            ->schema([
                                                TextEntry::make('item_name')
                                                    ->label('Ítem')
                                                    ->placeholder('-'),
                                                TextEntry::make('category')
                                                    ->label('Categoría')
                                                    ->badge()
                                                    ->placeholder('-'),
                                                TextEntry::make('item_unit')
                                                    ->label('Unidad')
                                                    ->placeholder('-'),
                                                TextEntry::make('quantity')
                                                    ->label('Cantidad')
                                                    ->numeric()
                                                    ->placeholder('-'),
                                                TextEntry::make('dosage_instruction')
                                                    ->label('Indicaciones / dosis')
                                                    ->placeholder('-'),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Auditoría')
                            ->icon(Heroicon::OutlinedClock)
                            ->schema([
                                Section::make('Auditoría')
                                    ->description('Control de creación y última actualización.')
                                    ->icon(Heroicon::OutlinedClock)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Trazabilidad')
                                            ->schema([
                                                TextEntry::make('created_by')
                                                    ->label('Creado por')
                                                    ->placeholder('-'),
                                                TextEntry::make('updated_by')
                                                    ->label('Actualizado por')
                                                    ->placeholder('-'),
                                                TextEntry::make('created_at')
                                                    ->label('Fecha de creación')
                                                    ->dateTime('d/m/Y H:i')
                                                    ->placeholder('-'),
                                                TextEntry::make('updated_at')
                                                    ->label('Última actualización')
                                                    ->dateTime('d/m/Y H:i')
                                                    ->placeholder('-'),
                                            ])
                                            ->columns(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Documentos')
                            ->icon(Heroicon::OutlinedFolderOpen)
                            ->schema([
                                Section::make('Documentos cargados')
                                    ->description('Validación en tiempo real de documentos y tipos asociados por archivo.')
                                    ->icon(Heroicon::OutlinedFolderOpen)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        RepeatableEntry::make('uploaded_documents')
                                            ->label('Listado de documentos')
                                            ->placeholder('Aún no hay documentos cargados en esta orden.')
                                            ->table([
                                                TableColumn::make('Documento')->width('30%'),
                                                TableColumn::make('Tipo(s)')->width('28%'),
                                                TableColumn::make('Archivo')->width('20%'),
                                                TableColumn::make('Fecha')->width('12%'),
                                            ])
                                            ->schema([
                                                TextEntry::make('document_name')
                                                    ->label('Documento')
                                                    ->html()
                                                    ->formatStateUsing(function (TextEntry $component, mixed $state): string {
                                                        $row = $component->getConstantState();

                                                        return self::renderDocumentNameCell(
                                                            is_array($row) ? ($row['document_name'] ?? $state) : $state,
                                                            is_array($row) ? $row : null,
                                                        );
                                                    })
                                                    ->placeholder('—'),
                                                TextEntry::make('document_types')
                                                    ->label('Tipo(s)')
                                                    ->badge()
                                                    ->color('success')
                                                    ->formatStateUsing(fn (mixed $state): ?string => filled($state)
                                                        ? trim((string) $state)
                                                        : null)
                                                    ->placeholder('Sin tipo asociado'),
                                                TextEntry::make('file_path')
                                                    ->label('Archivo')
                                                    ->formatStateUsing(fn (mixed $state): string => filled($state)
                                                        ? basename((string) $state)
                                                        : '—')
                                                    ->url(fn (mixed $state): ?string => filled($state)
                                                        ? URL::to(Storage::url((string) $state))
                                                        : null)
                                                    ->openUrlInNewTab()
                                                    ->placeholder('—'),
                                                TextEntry::make('uploaded_at')
                                                    ->label('Fecha')
                                                    ->formatStateUsing(fn (mixed $state): string => self::formatUploadedAt($state))
                                                    ->placeholder('—'),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    private static function renderDocumentNameCell(mixed $state, mixed $record): string
    {
        $name = trim((string) $state);
        $filePath = is_array($record) ? trim((string) ($record['file_path'] ?? '')) : '';
        $extension = strtoupper((string) pathinfo($filePath, PATHINFO_EXTENSION));

        if ($name === '') {
            $name = 'Documento sin nombre';
        }

        $meta = $extension !== '' ? $extension : 'Archivo';

        return '<div class="flex items-center gap-2">'
            .'<span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-cyan-500/10 text-cyan-500 dark:text-cyan-300">📄</span>'
            .'<div class="min-w-0">'
            .'<p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">'.e($name).'</p>'
            .'<p class="text-[11px] text-gray-500 dark:text-gray-400">'.e($meta).'</p>'
            .'</div>'
            .'</div>';
    }

    private static function renderDocumentTypesBadges(mixed $state): string
    {
        if (! is_array($state) || $state === []) {
            return '<span class="text-xs text-gray-400">Sin tipo asociado</span>';
        }

        $badges = collect($state)
            ->map(static fn (mixed $item): string => trim((string) $item))
            ->filter(static fn (string $value): bool => $value !== '')
            ->map(static fn (string $value): string => '<span class="inline-flex items-center rounded-full border border-emerald-400/30 bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-300">'.e($value).'</span>')
            ->values()
            ->all();

        if ($badges === []) {
            return '<span class="text-xs text-gray-400">Sin tipo asociado</span>';
        }

        return '<div class="flex flex-wrap gap-1">'.implode('', $badges).'</div>';
    }

    /**
     * Filament descompone arrays de estado en ítems individuales; normalizamos array o string.
     *
     * @return array<int, string>
     */
    private static function normalizeDocumentTypesState(mixed $state): array
    {
        if (is_array($state)) {
            return collect($state)
                ->map(static fn (mixed $item): string => trim((string) $item))
                ->filter(static fn (string $value): bool => $value !== '')
                ->values()
                ->all();
        }

        if (is_string($state) && trim($state) !== '') {
            return [trim($state)];
        }

        return [];
    }

    private static function formatUploadedAt(mixed $state): string
    {
        if (! filled($state)) {
            return '—';
        }

        try {
            return Carbon::parse((string) $state)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return (string) $state;
        }
    }

    private static function renderDownloadButton(mixed $record): string
    {
        if (! is_array($record)) {
            return '<span class="text-gray-400">No disponible</span>';
        }

        $filePath = trim((string) ($record['file_path'] ?? ''));

        if ($filePath === '' || ! Storage::disk('public')->exists($filePath)) {
            return '<span class="text-gray-400">No disponible</span>';
        }

        $downloadUrl = URL::to(Storage::url($filePath));
        $fileName = Str::limit(basename($filePath), 22);

        return '<a href="'.e($downloadUrl).'" title="Descargar '.e($fileName).'" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 rounded-full border border-cyan-300/40 bg-cyan-500/10 px-3 py-1 text-xs font-semibold text-cyan-600 transition hover:bg-cyan-500/20 dark:text-cyan-300">⬇ Descargar</a>';
    }
}
