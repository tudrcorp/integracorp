<?php

namespace App\Filament\Operations\Resources\OperationServiceOrders\Schemas;

use App\Models\OperationServiceOrder;
use App\Support\Operations\OperationServiceOrderValidity;
use App\Support\Operations\OperationServiceOrderViewActions;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
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
                                        TextEntry::make('validity_highlight')
                                            ->hiddenLabel()
                                            ->state(fn (OperationServiceOrder $record): string => self::renderValidityHighlight($record))
                                            ->html()
                                            ->visible(fn (OperationServiceOrder $record): bool => OperationServiceOrderValidity::shouldHighlightVigencia($record))
                                            ->columnSpanFull(),
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
                                                    ->color(fn (?string $state): string => match (mb_strtoupper(trim((string) $state))) {
                                                        'FINALIZADO' => 'success',
                                                        'CADUCADA' => 'danger',
                                                        'CANCELADA', 'CANCELADO' => 'gray',
                                                        default => 'warning',
                                                    })
                                                    ->placeholder('-'),
                                                TextEntry::make('approved_at')
                                                    ->label('Fecha de aprobación')
                                                    ->dateTime('d/m/Y H:i')
                                                    ->placeholder('-'),
                                                TextEntry::make('service_type')
                                                    ->label('Tipo de servicio')
                                                    ->badge()
                                                    ->placeholder('-'),
                                                TextEntry::make('telemedicinePriority.name')
                                                    ->label('Prioridad')
                                                    ->badge()
                                                    ->placeholder('-'),
                                            ])
                                            ->columns(4),
                                        Fieldset::make('Proveedor')
                                            ->schema([
                                                TextEntry::make('supplier_summary')
                                                    ->label('Proveedor')
                                                    ->state(fn (OperationServiceOrder $record): ?string => self::resolveSupplierName($record))
                                                    ->placeholder('-'),
                                                TextEntry::make('supplier_address_summary')
                                                    ->label('Dirección')
                                                    ->state(fn (OperationServiceOrder $record): ?string => self::resolveSupplierAddress($record))
                                                    ->placeholder('-')
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(2),
                                    ])
                                    ->footerActions([
                                        OperationServiceOrderViewActions::makeCancelAction(),
                                    ])
                                    ->footerActionsAlignment(Alignment::End)
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

    private static function renderValidityHighlight(OperationServiceOrder $record): string
    {
        $tone = OperationServiceOrderValidity::vigenciaTone($record) ?? 'info';
        $remaining = OperationServiceOrderValidity::remainingDays($record);
        $approvedAt = OperationServiceOrderValidity::approvedAt($record);
        $expiresAt = OperationServiceOrderValidity::expiresAt($record);
        $summary = OperationServiceOrderValidity::vigenciaLabel($record);
        $shortLabel = OperationServiceOrderValidity::vigenciaShortLabel($record) ?? $summary;

        $styles = match ($tone) {
            'danger' => [
                'shell' => 'border-red-400/90 bg-gradient-to-r from-red-50 via-rose-50/90 to-red-50 shadow-[0_12px_32px_-12px_rgba(239,68,68,0.45)] dark:border-red-500/50 dark:from-red-950/40 dark:via-rose-950/30 dark:to-red-950/40',
                'badge' => 'bg-red-500/15 text-red-700 dark:text-red-200',
                'title' => 'text-red-800 dark:text-red-200',
                'meta' => 'text-red-700/80 dark:text-red-300/80',
                'figure' => 'bg-red-500 text-white shadow-[0_8px_20px_-6px_rgba(239,68,68,0.65)]',
            ],
            'warning' => [
                'shell' => 'border-amber-400/90 bg-gradient-to-r from-amber-50 via-yellow-50/90 to-amber-50 shadow-[0_12px_32px_-12px_rgba(245,158,11,0.4)] dark:border-amber-500/50 dark:from-amber-950/40 dark:via-yellow-950/25 dark:to-amber-950/40',
                'badge' => 'bg-amber-500/15 text-amber-800 dark:text-amber-200',
                'title' => 'text-amber-900 dark:text-amber-100',
                'meta' => 'text-amber-800/80 dark:text-amber-200/80',
                'figure' => 'bg-amber-500 text-white shadow-[0_8px_20px_-6px_rgba(245,158,11,0.55)]',
            ],
            default => [
                'shell' => 'border-sky-400/80 bg-gradient-to-r from-sky-50 via-cyan-50/80 to-sky-50 shadow-[0_12px_32px_-12px_rgba(14,165,233,0.35)] dark:border-sky-500/45 dark:from-sky-950/35 dark:via-cyan-950/25 dark:to-sky-950/35',
                'badge' => 'bg-sky-500/15 text-sky-800 dark:text-sky-200',
                'title' => 'text-sky-900 dark:text-sky-100',
                'meta' => 'text-sky-800/80 dark:text-sky-200/80',
                'figure' => 'bg-sky-500 text-white shadow-[0_8px_20px_-6px_rgba(14,165,233,0.5)]',
            ],
        };

        $figureLabel = match (true) {
            $tone === 'danger' => '!',
            $remaining !== null && $remaining > 0 => (string) $remaining,
            default => '0',
        };

        $figureCaption = match (true) {
            $tone === 'danger' => 'Vencida',
            $remaining === 0 => 'Hoy',
            $remaining === 1 => 'día',
            default => 'días',
        };

        $datesLine = ($approvedAt !== null && $expiresAt !== null)
            ? sprintf(
                'Aprobada el %s · fecha límite %s · vigencia de %d días',
                $approvedAt->format('d/m/Y'),
                $expiresAt->format('d/m/Y'),
                OperationServiceOrderValidity::VALIDITY_DAYS
            )
            : sprintf('Vigencia de %d días desde la fecha de aprobación', OperationServiceOrderValidity::VALIDITY_DAYS);

        return '<div class="rounded-2xl border-2 p-4 '.$styles['shell'].'">'
            .'<div class="flex flex-wrap items-center gap-4">'
            .'<div class="flex h-[4.5rem] w-[4.5rem] shrink-0 flex-col items-center justify-center rounded-2xl '.$styles['figure'].'">'
            .'<span class="text-2xl font-black leading-none">'.e($figureLabel).'</span>'
            .'<span class="mt-0.5 text-[10px] font-bold uppercase tracking-wide opacity-90">'.e($figureCaption).'</span>'
            .'</div>'
            .'<div class="min-w-0 flex-1">'
            .'<span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider '.$styles['badge'].'">'
            .'Vigencia de la orden'
            .'</span>'
            .'<p class="mt-2 text-lg font-bold '.$styles['title'].'">'.e($shortLabel).'</p>'
            .'<p class="mt-1 text-sm font-medium '.$styles['meta'].'">'.e($summary).'</p>'
            .'<p class="mt-2 text-xs '.$styles['meta'].'">'.e($datesLine).'</p>'
            .'</div>'
            .'</div>'
            .'</div>';
    }

    private static function resolveSupplierName(OperationServiceOrder $record): ?string
    {
        $record->loadMissing(['supplier', 'doctorNurse']);

        if (filled($record->supplier?->name)) {
            return (string) $record->supplier->name;
        }

        if (filled($record->doctorNurse?->name)) {
            return (string) $record->doctorNurse->name;
        }

        if (filled($record->supplier_external)) {
            return (string) $record->supplier_external;
        }

        return null;
    }

    private static function resolveSupplierAddress(OperationServiceOrder $record): ?string
    {
        $record->loadMissing(['supplier', 'doctorNurse', 'approvedOperationQuote']);

        if (filled($record->approvedOperationQuote?->supplier_address)) {
            return trim((string) $record->approvedOperationQuote->supplier_address);
        }

        if (filled($record->supplier?->ubicacion_principal)) {
            return trim((string) $record->supplier->ubicacion_principal);
        }

        if (filled($record->doctorNurse?->ubicacion_principal)) {
            return trim((string) $record->doctorNurse->ubicacion_principal);
        }

        return null;
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
