<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Affiliations\Schemas;

use App\Filament\Shared\Affiliations\AffiliationRenovationHistoryInfolist;
use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\AffiliationDocument;
use App\Models\AffiliationObservation;
use Filament\Actions\Action;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class AffiliationInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private static function statusColor(?string $state): string
    {
        return match ($state) {
            'ACTIVA', 'PRE-APROBADA' => 'success',
            'PENDIENTE' => 'warning',
            'EXCLUIDO' => 'danger',
            default => 'gray',
        };
    }

    private static function affiliateStatusColor(?string $state): string
    {
        return match (strtoupper(trim((string) $state))) {
            'ACTIVO', 'ACTIVA' => 'success',
            'INACTIVO', 'INACTIVA' => 'warning',
            'EXCLUIDO', 'EXCLUIDA' => 'danger',
            default => 'gray',
        };
    }

    private static function affiliateBusinessContextColor(mixed $affiliateValue, mixed $ownerValue): string
    {
        if (blank($affiliateValue)) {
            return 'gray';
        }

        if (blank($ownerValue)) {
            return 'info';
        }

        return (int) $affiliateValue === (int) $ownerValue ? 'success' : 'warning';
    }

    private static function formatStoredFileName(mixed $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        return basename((string) $state);
    }

    private static function documentIsImage(mixed $path): bool
    {
        if (blank($path)) {
            return false;
        }

        $extension = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'heic', 'heif'], true);
    }

    private static function formatFileSize(?int $size): string
    {
        if ($size === null || $size <= 0) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        $value = (float) $size;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        return number_format($value, $unitIndex === 0 ? 0 : 2, ',', '.').' '.$units[$unitIndex];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('affiliationInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Resumen')
                            ->icon(Heroicon::OutlinedClipboardDocumentList)
                            ->schema([
                                Section::make('Resumen')
                                    ->description(fn (Affiliation $record): string => 'Generada el '.$record->created_at->format('d/m/Y').' a las '.$record->created_at->format('H:i').' · Creada por: '.($record->created_by ?? '—'))
                                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                                                    ->schema([
                                                        TextEntry::make('code')
                                                            ->label('Nº solicitud')
                                                            ->icon(Heroicon::OutlinedHashtag)
                                                            ->badge()
                                                            ->color('success'),
                                                        TextEntry::make('individual_quote.code')
                                                            ->label('Nº cotización')
                                                            ->icon(Heroicon::OutlinedDocumentDuplicate)
                                                            ->badge()
                                                            ->color('success'),
                                                        TextEntry::make('agent_id')
                                                            ->label('Código agente')
                                                            ->icon(Heroicon::OutlinedHashtag)
                                                            ->formatStateUsing(fn ($state): string => 'AGT-000'.(string) $state),
                                                        TextEntry::make('agent.name')
                                                            ->label('Nombre del agente')
                                                            ->icon(Heroicon::OutlinedUser)
                                                            ->placeholder('—'),
                                                        TextEntry::make('created_by')
                                                            ->label('Usuario')
                                                            ->icon(Heroicon::OutlinedUserCircle)
                                                            ->placeholder('—'),
                                                        TextEntry::make('created_at')
                                                            ->label('Fecha')
                                                            ->icon(Heroicon::OutlinedCalendarDays)
                                                            ->dateTime('d/m/Y H:i'),
                                                        TextEntry::make('status')
                                                            ->label('Estatus')
                                                            ->icon(Heroicon::OutlinedSignal)
                                                            ->badge()
                                                            ->color(fn (?string $state): string => self::statusColor($state)),
                                                        TextEntry::make('activation_date')
                                                            ->label('Fecha de activación')
                                                            ->icon(Heroicon::OutlinedInformationCircle)
                                                            ->badge()
                                                            ->color('success')
                                                            ->placeholder('—'),
                                                    ]),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Titular del plan')
                            ->icon(Heroicon::OutlinedUser)
                            ->schema([
                                Section::make('Titular del plan')
                                    ->icon(Heroicon::OutlinedUser)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('full_name_ti')
                                                    ->label('Nombre y apellido')
                                                    ->icon(Heroicon::OutlinedUser)
                                                    ->weight('medium')
                                                    ->placeholder('—'),
                                                TextEntry::make('nro_identificacion_ti')
                                                    ->label('Identificación')
                                                    ->icon(Heroicon::OutlinedIdentification)
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('phone_ti')
                                                    ->label('Teléfono')
                                                    ->icon(Heroicon::OutlinedPhone)
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('email_ti')
                                                    ->label('Correo')
                                                    ->icon(Heroicon::OutlinedEnvelope)
                                                    ->copyable()
                                                    ->placeholder('—')
                                                    ->wrap(),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Documento del titular')
                            ->icon(Heroicon::OutlinedDocumentArrowDown)
                            ->schema([
                                Section::make('Documento del titular')
                                    ->description('Documento de identidad cargado para el titular de la afiliación.')
                                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                ImageEntry::make('document')
                                                    ->hiddenLabel()
                                                    ->disk('public')
                                                    ->visibility('public')
                                                    ->imageHeight(260)
                                                    ->extraImgAttributes([
                                                        'class' => 'rounded-2xl border border-slate-200/80 shadow-md object-contain bg-white',
                                                        'loading' => 'lazy',
                                                    ])
                                                    ->visible(fn (Affiliation $record): bool => self::documentIsImage($record->document))
                                                    ->columnSpanFull(),
                                                TextEntry::make('document')
                                                    ->label('Documento del titular')
                                                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                                                    ->color('primary')
                                                    ->weight('medium')
                                                    ->formatStateUsing(fn (mixed $state): ?string => self::formatStoredFileName($state))
                                                    ->url(fn (Affiliation $record): ?string => filled($record->document)
                                                        ? asset('storage/'.$record->document)
                                                        : null)
                                                    ->openUrlInNewTab()
                                                    ->placeholder('No hay documento cargado para el titular.')
                                                    ->columnSpanFull(),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Pagador')
                            ->icon(Heroicon::OutlinedBanknotes)
                            ->schema([
                                Section::make('Responsable de pago (pagador)')
                                    ->icon(Heroicon::OutlinedBanknotes)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('full_name_payer')
                                                    ->label('Nombre y apellido')
                                                    ->icon(Heroicon::OutlinedUser)
                                                    ->weight('medium')
                                                    ->placeholder('—'),
                                                TextEntry::make('nro_identificacion_payer')
                                                    ->label('Identificación')
                                                    ->icon(Heroicon::OutlinedIdentification)
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('email_payer')
                                                    ->label('Correo')
                                                    ->icon(Heroicon::OutlinedEnvelope)
                                                    ->copyable()
                                                    ->placeholder('—')
                                                    ->wrap(),
                                                TextEntry::make('phone_payer')
                                                    ->label('Teléfono')
                                                    ->icon(Heroicon::OutlinedPhone)
                                                    ->copyable()
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Plan y pagos')
                            ->icon(Heroicon::OutlinedRectangleStack)
                            ->schema([
                                Section::make('Plan y frecuencia de pago')
                                    ->icon(Heroicon::OutlinedRectangleStack)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('plan.description')
                                                    ->label('Plan')
                                                    ->badge()
                                                    ->color('primary')
                                                    ->placeholder('—')
                                                    ->wrap(),
                                                TextEntry::make('coverage.price')
                                                    ->label('Cobertura')
                                                    ->money('USD')
                                                    ->placeholder('—'),
                                                TextEntry::make('fee_anual')
                                                    ->label('Tarifa anual')
                                                    ->money('USD')
                                                    ->placeholder('—'),
                                                TextEntry::make('payment_frequency')
                                                    ->label('Frecuencia de pago')
                                                    ->badge()
                                                    ->color('primary'),
                                                TextEntry::make('total_amount')
                                                    ->label('Monto total')
                                                    ->money('USD')
                                                    ->weight('semibold')
                                                    ->color('success'),
                                                TextEntry::make('family_members')
                                                    ->label('Miembros de la familia')
                                                    ->suffix(' pers.')
                                                    ->badge()
                                                    ->color('primary'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Afiliados asociados')
                            ->icon(Heroicon::OutlinedUsers)
                            ->schema([
                                Section::make('Afiliados asociados')
                                    ->description('Resumen principal de afiliados vinculados. La unidad y línea en verde coinciden con la afiliación; en ámbar están pendientes de sincronizar.')
                                    ->icon(Heroicon::OutlinedUsers)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                RepeatableEntry::make('affiliates')
                                                    ->label('')
                                                    ->placeholder('No hay afiliados asociados a esta afiliación.')
                                                    ->table([
                                                        TableColumn::make('Nombre'),
                                                        TableColumn::make('Identificación'),
                                                        TableColumn::make('Parentesco'),
                                                        TableColumn::make('Unidad de negocio'),
                                                        TableColumn::make('Línea de servicio'),
                                                        TableColumn::make('Teléfono'),
                                                        TableColumn::make('Correo'),
                                                        TableColumn::make('Estatus'),
                                                        TableColumn::make('Documento'),
                                                        TableColumn::make('Documento ILS'),
                                                    ])
                                                    ->schema([
                                                        TextEntry::make('full_name')
                                                            ->label('Nombre')
                                                            ->icon(Heroicon::OutlinedUser)
                                                            ->weight('medium')
                                                            ->placeholder('—'),
                                                        TextEntry::make('nro_identificacion')
                                                            ->label('Identificación')
                                                            ->icon(Heroicon::OutlinedIdentification)
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                        TextEntry::make('relationship')
                                                            ->label('Parentesco')
                                                            ->badge()
                                                            ->color('gray')
                                                            ->placeholder('—'),
                                                        TextEntry::make('businessUnit.definition')
                                                            ->label('Unidad de negocio')
                                                            ->icon(Heroicon::OutlinedBuildingOffice2)
                                                            ->badge()
                                                            ->weight('semibold')
                                                            ->color(fn (?string $state, Affiliate $record, ViewRecord $livewire): string => self::affiliateBusinessContextColor(
                                                                $record->business_unit_id,
                                                                $livewire->getRecord()->business_unit_id,
                                                            ))
                                                            ->placeholder('—'),
                                                        TextEntry::make('businessLine.definition')
                                                            ->label('Línea de servicio')
                                                            ->icon(Heroicon::OutlinedQueueList)
                                                            ->badge()
                                                            ->weight('semibold')
                                                            ->color(fn (?string $state, Affiliate $record, ViewRecord $livewire): string => self::affiliateBusinessContextColor(
                                                                $record->business_line_id,
                                                                $livewire->getRecord()->business_line_id,
                                                            ))
                                                            ->placeholder('—'),
                                                        TextEntry::make('phone')
                                                            ->label('Teléfono')
                                                            ->icon(Heroicon::OutlinedPhone)
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                        TextEntry::make('email')
                                                            ->label('Correo')
                                                            ->icon(Heroicon::OutlinedEnvelope)
                                                            ->copyable()
                                                            ->placeholder('—')
                                                            ->wrap(),
                                                        TextEntry::make('status')
                                                            ->label('Estatus')
                                                            ->badge()
                                                            ->color(fn (?string $state): string => self::affiliateStatusColor($state))
                                                            ->placeholder('—'),
                                                        ImageEntry::make('document')
                                                            ->label('Documento')
                                                            ->disk('public')
                                                            ->visibility('public')
                                                            ->imageHeight(56)
                                                            ->extraImgAttributes([
                                                                'class' => 'rounded-lg border border-slate-200/80 shadow-sm object-cover bg-white',
                                                                'loading' => 'lazy',
                                                            ])
                                                            ->url(fn (Affiliate $record): ?string => filled($record->document)
                                                                ? asset('storage/'.$record->document)
                                                                : null)
                                                            ->openUrlInNewTab()
                                                            ->visible(fn (Affiliate $record): bool => self::documentIsImage($record->document))
                                                            ->placeholder('—'),
                                                        ImageEntry::make('document_ils')
                                                            ->label('Documento ILS')
                                                            ->disk('public')
                                                            ->visibility('public')
                                                            ->imageHeight(56)
                                                            ->extraImgAttributes([
                                                                'class' => 'rounded-lg border border-slate-200/80 shadow-sm object-cover bg-white',
                                                                'loading' => 'lazy',
                                                            ])
                                                            ->url(fn (Affiliate $record): ?string => filled($record->document_ils)
                                                                ? asset('storage/'.$record->document_ils)
                                                                : null)
                                                            ->openUrlInNewTab()
                                                            ->visible(fn (Affiliate $record): bool => self::documentIsImage($record->document_ils))
                                                            ->placeholder('—'),
                                                    ])
                                                    ->columnSpanFull(),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Aliado nivel 1')
                            ->icon(Heroicon::OutlinedBuildingStorefront)
                            ->schema([
                                Section::make('Aliado de servicio nivel 1')
                                    ->icon(Heroicon::OutlinedBuildingStorefront)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('aliado_1_name')
                                                    ->label('Nombre del aliado')
                                                    ->icon(Heroicon::OutlinedDocumentText)
                                                    ->badge()
                                                    ->color('primary')
                                                    ->placeholder('—'),
                                                TextEntry::make('date_init_aliado_1')
                                                    ->label('Inicio')
                                                    ->icon(Heroicon::OutlinedCalendarDays)
                                                    ->badge()
                                                    ->color('primary')
                                                    ->placeholder('—'),
                                                TextEntry::make('date_end_aliado_1')
                                                    ->label('Vencimiento')
                                                    ->icon(Heroicon::OutlinedCalendarDays)
                                                    ->badge()
                                                    ->color('primary')
                                                    ->placeholder('—'),
                                                IconEntry::make('vaucher_aliado_1')
                                                    ->label('Voucher')
                                                    ->icon(fn (Affiliation $record): string => filled($record->vaucher_aliado_1)
                                                        ? 'heroicon-o-check-circle'
                                                        : 'heroicon-o-x-circle')
                                                    ->color(fn (Affiliation $record): string => filled($record->vaucher_aliado_1) ? 'success' : 'danger')
                                                    ->url(fn (Affiliation $record): ?string => filled($record->vaucher_aliado_1)
                                                        ? asset('storage/'.$record->vaucher_aliado_1)
                                                        : null)
                                                    ->openUrlInNewTab(),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Aliado nivel 2')
                            ->icon(Heroicon::OutlinedBuildingStorefront)
                            ->schema([
                                Section::make('Aliado de servicio nivel 2')
                                    ->icon(Heroicon::OutlinedBuildingStorefront)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('affiliates.vaucherIls')
                                                    ->label('Número de voucher')
                                                    ->icon(Heroicon::OutlinedDocumentText)
                                                    ->badge()
                                                    ->color('primary')
                                                    ->placeholder('—'),
                                                TextEntry::make('affiliates.dateInit')
                                                    ->label('Inicio')
                                                    ->icon(Heroicon::OutlinedCalendarDays)
                                                    ->badge()
                                                    ->color('primary')
                                                    ->placeholder('—'),
                                                TextEntry::make('affiliates.dateEnd')
                                                    ->label('Vencimiento')
                                                    ->icon(Heroicon::OutlinedCalendarDays)
                                                    ->badge()
                                                    ->color('primary')
                                                    ->placeholder('—'),
                                                IconEntry::make('affiliate_level_two_ils')
                                                    ->label('Documento ILS')
                                                    ->icon(function (Affiliation $record): string {
                                                        $doc = $record->affiliates->first()?->document_ils;

                                                        return filled($doc) ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle';
                                                    })
                                                    ->color(function (Affiliation $record): string {
                                                        $doc = $record->affiliates->first()?->document_ils;

                                                        return filled($doc) ? 'success' : 'danger';
                                                    })
                                                    ->url(function (Affiliation $record): ?string {
                                                        $doc = $record->affiliates->first()?->document_ils;

                                                        return filled($doc) ? asset('storage/'.$doc) : null;
                                                    })
                                                    ->openUrlInNewTab(),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Declaración médica')
                            ->icon(Heroicon::OutlinedHeart)
                            ->schema([
                                Section::make('Declaración médica')
                                    ->description('Respuestas del cuestionario de salud.')
                                    ->icon(Heroicon::OutlinedHeart)
                                    ->collapsed()
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                ...self::medicalQuestionIconEntries(),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        AffiliationRenovationHistoryInfolist::tab(self::IOS_SECTION_CLASS),
                        Tab::make('Expediente digital')
                            ->icon(Heroicon::OutlinedFolderOpen)
                            ->schema([
                                Section::make('Expediente digital')
                                    ->description('Adjuntos del expediente individual (PDF e imágenes).')
                                    ->icon(Heroicon::OutlinedFolderOpen)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                RepeatableEntry::make('affiliationDocuments')
                                                    ->label('')
                                                    ->placeholder('No hay documentos adjuntos en el expediente.')
                                                    ->table([
                                                        TableColumn::make('Documento')->width('38%'),
                                                        TableColumn::make('Tipo')->width('18%'),
                                                        TableColumn::make('Tamaño')->width('12%'),
                                                        TableColumn::make('Fecha')->width('17%'),
                                                        TableColumn::make('Acciones')->width('15%')->alignStart(),
                                                    ])
                                                    ->schema([
                                                        TextEntry::make('original_name')
                                                            ->label('Documento')
                                                            ->icon(Heroicon::OutlinedDocumentText)
                                                            ->formatStateUsing(fn (mixed $state, $record): string => filled($state)
                                                                ? (string) $state
                                                                : self::formatStoredFileName($record->file_path))
                                                            ->url(fn ($record): ?string => filled($record->file_path)
                                                                ? asset('storage/'.$record->file_path)
                                                                : null)
                                                            ->openUrlInNewTab()
                                                            ->placeholder('—'),
                                                        TextEntry::make('mime_type')
                                                            ->label('Tipo')
                                                            ->formatStateUsing(function (mixed $state): string {
                                                                if (! filled($state)) {
                                                                    return '—';
                                                                }

                                                                $mime = (string) $state;

                                                                if (str_starts_with($mime, 'image/')) {
                                                                    return 'Imagen';
                                                                }

                                                                return $mime === 'application/pdf' ? 'PDF' : $mime;
                                                            }),
                                                        TextEntry::make('file_size')
                                                            ->label('Tamaño')
                                                            ->formatStateUsing(fn (mixed $state): string => self::formatFileSize(
                                                                is_numeric($state) ? (int) $state : null
                                                            )),
                                                        TextEntry::make('created_at')
                                                            ->label('Fecha')
                                                            ->dateTime('d/m/Y H:i')
                                                            ->placeholder('—'),
                                                        TextEntry::make('expediente_delete')
                                                            ->label('Acciones')
                                                            ->alignStart()
                                                            ->getStateUsing(fn (): string => "\u{00A0}")
                                                            ->formatStateUsing(fn (): string => '')
                                                            ->prefixActions([
                                                                Action::make('downloadExpedienteDocument')
                                                                    ->label('Descargar')
                                                                    ->tooltip('Descargar documento')
                                                                    ->icon(Heroicon::OutlinedArrowDownTray)
                                                                    ->color('verde')
                                                                    ->action(function (AffiliationDocument $document, ViewRecord $livewire) {
                                                                        abort_unless(
                                                                            (int) $document->affiliation_id === (int) $livewire->getRecord()->getKey(),
                                                                            403
                                                                        );

                                                                        abort_unless(
                                                                            filled($document->file_path) && Storage::disk('public')->exists($document->file_path),
                                                                            404
                                                                        );

                                                                        return response()->download(
                                                                            Storage::disk('public')->path($document->file_path),
                                                                            self::formatStoredFileName($document->original_name ?: $document->file_path) ?? 'documento'
                                                                        );
                                                                    }),
                                                                Action::make('deleteExpedienteDocument')
                                                                    ->label('Eliminar')
                                                                    ->tooltip('Eliminar documento')
                                                                    ->icon(Heroicon::OutlinedTrash)
                                                                    ->color('danger')
                                                                    ->requiresConfirmation()
                                                                    ->modalHeading('Eliminar documento')
                                                                    ->modalDescription('¿Seguro que deseas eliminar este archivo del expediente? Esta acción no se puede deshacer.')
                                                                    ->modalSubmitActionLabel('Eliminar')
                                                                    ->action(function (AffiliationDocument $document, ViewRecord $livewire): void {
                                                                        abort_unless(
                                                                            (int) $document->affiliation_id === (int) $livewire->getRecord()->getKey(),
                                                                            403
                                                                        );

                                                                        if (filled($document->file_path)) {
                                                                            Storage::disk('public')->delete($document->file_path);
                                                                        }

                                                                        $document->delete();

                                                                        $livewire->record->unsetRelation('affiliationDocuments');
                                                                        $livewire->record->load('affiliationDocuments');

                                                                        Notification::make()
                                                                            ->success()
                                                                            ->title('Documento eliminado')
                                                                            ->send();
                                                                    }),
                                                            ]),
                                                    ])
                                                    ->columnSpanFull(),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Observaciones')
                            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                            ->schema([
                                Section::make('Observaciones')
                                    ->description(fn (Affiliation $record): string => 'Bitácora de notas registradas por los analistas. Total: '.(int) ($record->affiliationObservations?->count() ?? 0).'. Use «Agregar observación» en la cabecera para añadir una nueva.')
                                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                RepeatableEntry::make('affiliationObservations')
                                                    ->label('')
                                                    ->placeholder('No hay observaciones registradas para esta afiliación.')
                                                    ->table([
                                                        TableColumn::make('Fecha')->width('20%'),
                                                        TableColumn::make('Observación')->width('55%'),
                                                        TableColumn::make('Registrado por')->width('25%'),
                                                    ])
                                                    ->schema([
                                                        TextEntry::make('created_at')
                                                            ->label('Fecha')
                                                            ->icon(Heroicon::OutlinedCalendarDays)
                                                            ->dateTime('d/m/Y H:i')
                                                            ->helperText(fn (AffiliationObservation $record): ?string => $record->created_at?->diffForHumans())
                                                            ->placeholder('—'),
                                                        TextEntry::make('description')
                                                            ->label('Observación')
                                                            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                                                            ->wrap()
                                                            ->placeholder('—'),
                                                        TextEntry::make('created_by')
                                                            ->label('Registrado por')
                                                            ->icon(Heroicon::OutlinedUser)
                                                            ->weight('medium')
                                                            ->getStateUsing(fn (AffiliationObservation $record): string => $record->createdBy?->name ?? (string) ($record->created_by ?? '—'))
                                                            ->helperText(fn (AffiliationObservation $record): ?string => $record->createdBy?->email)
                                                            ->placeholder('—'),
                                                    ])
                                                    ->columnSpanFull(),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    /**
     * @return array<int, IconEntry>
     */
    private static function medicalQuestionIconEntries(): array
    {
        $labels = [
            'cuestion_1' => '¿Usted y el grupo de beneficiarios solicitantes, gozan de buena salud?',
            'cuestion_2' => '¿Usted o el grupo de beneficiarios presentan alguna condición médica o congénita?',
            'cuestion_3' => '¿Usted o el grupo de beneficiario ha sido intervenido quirúrgicamente? ',
            'cuestion_4' => '¿Usted o el grupo de beneficiario padece o ha padecido alguna enfermedad?',
            'cuestion_5' => 'Enfermedades Cardiovasculares, tales como; Hipertensión Arterial, Ataque cardíaco, Angina o dolor de pecho, Soplo Cardíaco, Insuficiencia Cardíaca Congestiva o desórdenes del corazón o sistema circulatorio.',
            'cuestion_6' => 'Enfermedades Cerebrovasculares, tales como: Desmayos, confusión, parálisis de miembros, dificultad para hablar, articular y entender, Accidente Cerebro-vascular (ACV). Cefalea o migraña. Epilepsia o Convulsiones. Otros trastornos o enfermedad del Cerebro o Sistema Nervioso.',
            'cuestion_7' => 'Enfermedades Respiratorias, tales como: Asma Bronquial, Bronquitis, Bronquiolitis, Enfisema, Neumonía, Enfermedad pulmonar Obstructiva Crónica (EPOC) u otras enfermedades del Sistema Respiratorio.',
            'cuestion_8' => 'Enfermedades o Trastornos Endocrinos tales como: Diabetes Mellitus, Bocio, hipertiroidismo, hipotiroidismo, Tiroiditis, Resistencia a la insulina, enfermedad de Cushing, cáncer de tiroides.',
            'cuestion_9' => 'Enfermedades Gastrointestinales como: Litiasis vesicular, Cólico Biliar, Úlcera gástrica, gastritis, Hemorragia digestivas, colitis, hemorroides, Apendicitis, Peritonitis, Pancreatitis u otros desórdenes del estómago, intestino, hígado o vesícula biliar.',
            'cuestion_10' => 'Enfermedades Renales: Litiasis renal, Cólico nefrítico, Sangre en la orina o Hematuria, Cistitis, Infecciones urinarias, Pielonefritis, Insuficiencia renal aguda. Otras enfermedades del riñón, vejiga o próstata.',
            'cuestion_11' => 'Enfermedades Osteoarticulares, Artrosis, Artritis reumatoide, Traumatismo craneoencefálico, Fracturas óseas, Luxaciones o esguinces, tumores óseos, u otros trastornos de los músculos, articulaciones o columna vertical o espalda.',
            'cuestion_12' => '¿Ha sufrido o padece de alguna enfermedad de la Piel como: Dermatitis, Celulitis, Abscesos cutáneos, quistes, tumores o cáncer? ,Quemaduras o Heridas Complicadas.',
            'cuestion_13' => '¿Padece de alguna enfermedad o desorden de los ojos, oídos, nariz o garganta?',
            'cuestion_14' => '¿Ha padecido de algún Envenenamiento o Intoxicación, ¿Alergia o Reacción de Hipersensibilidad (medicamentosa, alimentaria, picadura de insecto, otras), edema de glotis o anafilaxia?',
            'cuestion_15' => '¿Usted o alguno de los solicitantes, toma algún tipo de medicamentos por tratamiento prolongado?',
            'cuestion_16' => '¿Ha padecido de algún Envenenamiento o Intoxicación, ¿Alergia o Reacción de Hipersensibilidad (medicamentosa, alimentaria, picadura de insecto, otras), edema de glotis o anafilaxia?',
        ];

        return collect($labels)
            ->map(fn (string $label, string $field): IconEntry => IconEntry::make($field)
                ->label($label)
                ->boolean())
            ->values()
            ->all();
    }
}
