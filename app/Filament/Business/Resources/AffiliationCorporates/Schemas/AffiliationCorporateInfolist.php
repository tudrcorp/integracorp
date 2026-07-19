<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\AffiliationCorporates\Schemas;

use App\Filament\Business\Resources\AffiliationCorporates\Support\AffiliationCorporateInfolistTab;
use App\Filament\Business\Resources\CorporateQuotes\CorporateQuoteResource;
use App\Models\AffiliationCorporate;
use App\Models\AffiliationCorporateDocument;
use App\Models\AffiliationCorporateObservation;
use Carbon\Carbon;
use Filament\Actions\Action;
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

class AffiliationCorporateInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private const IOS_INSET_GROUP_CLASS = 'rounded-xl border border-slate-200/60 bg-slate-50/50 p-3 dark:border-white/10 dark:bg-white/[0.04] sm:p-4';

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

    private static function loadedRelationCount(AffiliationCorporate $record, string $relation): int
    {
        if ($record->relationLoaded($relation)) {
            return $record->getRelation($relation)->count();
        }

        return (int) $record->{$relation}()->count();
    }

    /**
     * Fechas en BD a veces están como texto `d/m/Y`; el cast datetime de Eloquent falla al hidratar.
     */
    private static function formatLegacyDateColumn(mixed $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        if ($state instanceof \Carbon\CarbonInterface) {
            return $state->format('d/m/Y');
        }

        $s = trim((string) $state);

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $s)) {
            return $s;
        }

        try {
            return Carbon::parse($s)->format('d/m/Y');
        } catch (\Throwable) {
            return $s;
        }
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
                Tabs::make('affiliationCorporateInfolistTabs')
                    ->columnSpanFull()
                    ->livewireProperty('affiliationCorporateInfolistTab')
                    ->id('affiliation-corporate-infolist-tabs')
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        AffiliationCorporateInfolistTab::AFILIACION => Tab::make('Afiliación corporativa')
                            ->icon(Heroicon::OutlinedBuildingOffice2)
                            ->schema([
                                Section::make('Afiliación corporativa')
                                    ->description(fn (AffiliationCorporate $record): string => 'Generada el '.$record->created_at->format('d/m/Y').' a las '.$record->created_at->format('H:i'))
                                    ->icon(Heroicon::OutlinedBuildingOffice2)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('name_corporate')
                                                    ->label('Empresa')
                                                    ->size('lg')
                                                    ->weight('semibold')
                                                    ->color('gray')
                                                    ->placeholder('—'),
                                                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                                                    ->schema([
                                                        TextEntry::make('code')
                                                            ->label('Código de afiliación')
                                                            ->icon(Heroicon::OutlinedHashtag)
                                                            ->badge()
                                                            ->color('success')
                                                            ->formatStateUsing(fn (?string $state): string => filled($state) ? '# '.$state : '—'),
                                                        TextEntry::make('affiliation_type')
                                                            ->label('Tipo de afiliación')
                                                            ->icon(Heroicon::OutlinedStar)
                                                            ->badge()
                                                            ->color(fn (?string $state): string => $state === 'VIP' ? 'warning' : 'gray')
                                                            ->placeholder('—'),
                                                        TextEntry::make('email')
                                                            ->label('Correo corporativo')
                                                            ->icon(Heroicon::OutlinedEnvelope)
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                        TextEntry::make('phone')
                                                            ->label('Teléfono corporativo')
                                                            ->icon(Heroicon::OutlinedPhone)
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                        TextEntry::make('agent_id')
                                                            ->label('Código agente')
                                                            ->icon(Heroicon::OutlinedHashtag)
                                                            ->badge()
                                                            ->color('gray')
                                                            ->formatStateUsing(function ($state, AffiliationCorporate $record): string {
                                                                $code = $record->agent?->code_agent;
                                                                if (filled($code)) {
                                                                    return str_starts_with((string) $code, '#') ? (string) $code : '# '.(string) $code;
                                                                }
                                                                if ($state !== null && $state !== '') {
                                                                    $digits = preg_replace('/\D/', '', (string) $state);

                                                                    return '# AGT-'.str_pad($digits !== '' ? $digits : (string) $state, 5, '0', STR_PAD_LEFT);
                                                                }

                                                                return '—';
                                                            }),
                                                        TextEntry::make('agent.name')
                                                            ->label('Nombre del agente')
                                                            ->icon(Heroicon::OutlinedUser)
                                                            ->placeholder('—'),
                                                        TextEntry::make('created_at')
                                                            ->label('Fecha de solicitud')
                                                            ->icon(Heroicon::OutlinedCalendarDays)
                                                            ->dateTime('d/m/Y H:i'),
                                                        TextEntry::make('activated_at')
                                                            ->label('Fecha de emisión')
                                                            ->icon(Heroicon::OutlinedCalendar)
                                                            ->formatStateUsing(fn (mixed $state): ?string => self::formatLegacyDateColumn($state))
                                                            ->placeholder('—'),
                                                        TextEntry::make('corporate_quote_id')
                                                            ->label('Cotización corporativa')
                                                            ->icon(Heroicon::OutlinedDocumentDuplicate)
                                                            ->color('primary')
                                                            ->formatStateUsing(fn ($state, AffiliationCorporate $record): string => $record->corporate_quote?->code
                                                                ?? (filled($state) ? 'ID '.(string) $state : '—'))
                                                            ->url(fn (AffiliationCorporate $record): ?string => filled($record->corporate_quote_id)
                                                                ? CorporateQuoteResource::getUrl('edit', ['record' => $record->corporate_quote_id], panel: 'business')
                                                                : null)
                                                            ->openUrlInNewTab(),
                                                        TextEntry::make('agency.name_corporative')
                                                            ->label('Agencia')
                                                            ->icon(Heroicon::OutlinedBuildingLibrary)
                                                            ->placeholder('—'),
                                                        TextEntry::make('code_agency')
                                                            ->label('Código de agencia')
                                                            ->icon(Heroicon::OutlinedHashtag)
                                                            ->badge()
                                                            ->color('gray')
                                                            ->placeholder('—'),
                                                        TextEntry::make('owner_code')
                                                            ->label('Código jerárquico')
                                                            ->icon(Heroicon::OutlinedSquares2x2)
                                                            ->placeholder('—'),
                                                        TextEntry::make('accountManager.name')
                                                            ->label('Ejecutivo comercial')
                                                            ->icon(Heroicon::OutlinedBriefcase)
                                                            ->placeholder('—'),
                                                    ]),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        AffiliationCorporateInfolistTab::UBICACION => Tab::make('Ubicación fiscal')
                            ->icon(Heroicon::OutlinedMapPin)
                            ->schema([
                                Section::make('Ubicación y datos fiscales')
                                    ->description('Dirección fiscal del titular y ubicación geográfica.')
                                    ->icon(Heroicon::OutlinedMapPin)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('rif')
                                                    ->label('RIF')
                                                    ->icon(Heroicon::OutlinedIdentification)
                                                    ->placeholder('—'),
                                                TextEntry::make('document')
                                                    ->label('Documento del titular')
                                                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                                                    ->color('primary')
                                                    ->formatStateUsing(fn (mixed $state): ?string => self::formatStoredFileName($state))
                                                    ->url(fn (AffiliationCorporate $record): ?string => filled($record->document)
                                                        ? asset('storage/'.$record->document)
                                                        : null)
                                                    ->openUrlInNewTab()
                                                    ->placeholder('—'),
                                                TextEntry::make('address')
                                                    ->label('Dirección')
                                                    ->icon(Heroicon::OutlinedHome)
                                                    ->columnSpan(['default' => 1, 'sm' => 2, 'lg' => 3])
                                                    ->placeholder('—'),
                                                TextEntry::make('country.name')
                                                    ->label('País')
                                                    ->icon(Heroicon::OutlinedGlobeAlt)
                                                    ->placeholder('—'),
                                                TextEntry::make('state.definition')
                                                    ->label('Estado')
                                                    ->icon(Heroicon::OutlinedMap)
                                                    ->placeholder('—'),
                                                TextEntry::make('city.definition')
                                                    ->label('Ciudad')
                                                    ->icon(Heroicon::OutlinedBuildingOffice)
                                                    ->placeholder('—'),
                                                TextEntry::make('region_id')
                                                    ->label('Región')
                                                    ->icon(Heroicon::OutlinedMapPin)
                                                    ->formatStateUsing(fn ($state, AffiliationCorporate $record): string => $record->region?->definition ?? (string) $state)
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        AffiliationCorporateInfolistTab::DOCUMENTO => Tab::make('Documento del contratante')
                            ->icon(Heroicon::OutlinedDocumentArrowDown)
                            ->schema([
                                Section::make('Documento del contratante')
                                    ->description('Documento cargado para el contratante de la afiliación corporativa.')
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
                                                    ->visible(fn (AffiliationCorporate $record): bool => self::documentIsImage($record->document))
                                                    ->columnSpanFull(),
                                                TextEntry::make('document')
                                                    ->label('Documento del contratante')
                                                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                                                    ->color('primary')
                                                    ->weight('medium')
                                                    ->formatStateUsing(fn (mixed $state): ?string => self::formatStoredFileName($state))
                                                    ->url(fn (AffiliationCorporate $record): ?string => filled($record->document)
                                                        ? asset('storage/'.$record->document)
                                                        : null)
                                                    ->openUrlInNewTab()
                                                    ->placeholder('No hay documento cargado para el contratante.')
                                                    ->columnSpanFull(),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        AffiliationCorporateInfolistTab::CONTACTO => Tab::make('Contacto')
                            ->icon(Heroicon::OutlinedUserCircle)
                            ->schema([
                                Section::make('Solicitante / contacto')
                                    ->description('Persona de contacto y estado del trámite.')
                                    ->icon(Heroicon::OutlinedUserCircle)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('full_name_contact')
                                                    ->label('Nombre completo')
                                                    ->icon(Heroicon::OutlinedUser)
                                                    ->weight('medium')
                                                    ->placeholder('—'),
                                                TextEntry::make('nro_identificacion_contact')
                                                    ->label('Identificación')
                                                    ->icon(Heroicon::OutlinedIdentification)
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('email_contact')
                                                    ->label('Correo de contacto')
                                                    ->icon(Heroicon::OutlinedEnvelope)
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('phone_contact')
                                                    ->label('Teléfono de contacto')
                                                    ->icon(Heroicon::OutlinedPhone)
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('country_code_contact')
                                                    ->label('Prefijo teléfono contacto')
                                                    ->icon(Heroicon::OutlinedPhoneArrowUpRight)
                                                    ->placeholder('—'),
                                                TextEntry::make('created_by')
                                                    ->label('Usuario que registró')
                                                    ->icon(Heroicon::OutlinedUserPlus)
                                                    ->placeholder('—'),
                                                TextEntry::make('status')
                                                    ->label('Estatus')
                                                    ->icon(Heroicon::OutlinedSignal)
                                                    ->badge()
                                                    ->color(fn (?string $state): string => self::statusColor($state)),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        AffiliationCorporateInfolistTab::NEGOCIO => Tab::make('Negocio')
                            ->icon(Heroicon::OutlinedBriefcase)
                            ->schema([
                                Section::make('Negocio y alcance')
                                    ->description('Unidad de negocio, línea de servicio y proveedores.')
                                    ->icon(Heroicon::OutlinedBriefcase)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('businessUnit.definition')
                                                    ->label('Unidad de negocio')
                                                    ->icon(Heroicon::OutlinedBuildingOffice2)
                                                    ->placeholder('—'),
                                                TextEntry::make('businessLine.definition')
                                                    ->label('Línea de servicio')
                                                    ->icon(Heroicon::OutlinedRectangleStack)
                                                    ->placeholder('—'),
                                                TextEntry::make('service_providers')
                                                    ->label('Proveedores de servicios')
                                                    ->icon(Heroicon::OutlinedUsers)
                                                    ->formatStateUsing(function ($state): ?string {
                                                        if (blank($state)) {
                                                            return null;
                                                        }

                                                        return is_array($state) ? implode(', ', $state) : (string) $state;
                                                    })
                                                    ->placeholder('—'),
                                                TextEntry::make('type')
                                                    ->label('Tipo')
                                                    ->icon(Heroicon::OutlinedTag)
                                                    ->placeholder('—'),
                                                TextEntry::make('poblation')
                                                    ->label('Población')
                                                    ->icon(Heroicon::OutlinedUsers)
                                                    ->placeholder('—'),
                                                TextEntry::make('effective_date')
                                                    ->label('Vigencia / fecha efectiva')
                                                    ->icon(Heroicon::OutlinedCalendarDays)
                                                    ->formatStateUsing(fn (mixed $state): ?string => self::formatLegacyDateColumn($state))
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        AffiliationCorporateInfolistTab::PLANES => Tab::make('Planes asociados')
                            ->icon(Heroicon::OutlinedRectangleStack)
                            ->schema([
                                Section::make('Planes asociados')
                                    ->description(fn (AffiliationCorporate $record): string => 'Detalle de planes vinculados a la afiliación corporativa. Total: '.self::loadedRelationCount($record, 'affiliationCorporatePlans'))
                                    ->icon(Heroicon::OutlinedRectangleStack)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                RepeatableEntry::make('affiliationCorporatePlans')
                                                    ->label('')
                                                    ->placeholder('No hay planes asociados a esta afiliación corporativa.')
                                                    ->table([
                                                        TableColumn::make('Plan'),
                                                        TableColumn::make('Cobertura'),
                                                        TableColumn::make('Rango de edad'),
                                                        TableColumn::make('Tarifa'),
                                                        TableColumn::make('Frecuencia'),
                                                        TableColumn::make('Afiliados'),
                                                        TableColumn::make('Subtotal anual'),
                                                        TableColumn::make('Subtotal semestral'),
                                                        TableColumn::make('Subtotal trimestral'),
                                                        TableColumn::make('Subtotal mensual'),
                                                        TableColumn::make('Estatus'),
                                                    ])
                                                    ->schema([
                                                        TextEntry::make('plan.description')
                                                            ->label('Plan')
                                                            ->badge()
                                                            ->color('primary')
                                                            ->placeholder('—'),
                                                        TextEntry::make('coverage.price')
                                                            ->label('Cobertura')
                                                            ->money('USD')
                                                            ->placeholder('—'),
                                                        TextEntry::make('ageRange.range')
                                                            ->label('Rango de edad')
                                                            ->suffix(' años')
                                                            ->placeholder('—'),
                                                        TextEntry::make('fee')
                                                            ->label('Tarifa')
                                                            ->money('USD')
                                                            ->placeholder('—'),
                                                        TextEntry::make('payment_frequency')
                                                            ->label('Frecuencia')
                                                            ->badge()
                                                            ->color('warning')
                                                            ->placeholder('—'),
                                                        TextEntry::make('total_persons')
                                                            ->label('Afiliados')
                                                            ->suffix(' pers.')
                                                            ->placeholder('—'),
                                                        TextEntry::make('subtotal_anual')
                                                            ->label('Subtotal anual')
                                                            ->money('USD')
                                                            ->placeholder('—'),
                                                        TextEntry::make('subtotal_biannual')
                                                            ->label('Subtotal semestral')
                                                            ->money('USD')
                                                            ->placeholder('—'),
                                                        TextEntry::make('subtotal_quarterly')
                                                            ->label('Subtotal trimestral')
                                                            ->money('USD')
                                                            ->placeholder('—'),
                                                        TextEntry::make('subtotal_monthly')
                                                            ->label('Subtotal mensual')
                                                            ->money('USD')
                                                            ->placeholder('—'),
                                                        TextEntry::make('status')
                                                            ->label('Estatus')
                                                            ->badge()
                                                            ->color(fn (?string $state): string => self::affiliateStatusColor($state))
                                                            ->placeholder('—'),
                                                    ])
                                                    ->columnSpanFull(),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        AffiliationCorporateInfolistTab::PAGOS => Tab::make('Pagos')
                            ->icon(Heroicon::OutlinedBanknotes)
                            ->schema([
                                Section::make('Pagos')
                                    ->description('Frecuencia, montos y vigencia del voucher ILS.')
                                    ->icon(Heroicon::OutlinedBanknotes)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make(['default' => 1, 'sm' => 2])
                                                    ->extraAttributes([
                                                        'class' => self::IOS_INSET_GROUP_CLASS.' sm:col-span-2 lg:col-span-3',
                                                    ])
                                                    ->columnSpanFull()
                                                    ->schema([
                                                        TextEntry::make('payment_frequency')
                                                            ->label('Frecuencia de pago')
                                                            ->badge()
                                                            ->color('warning'),
                                                        TextEntry::make('fee_anual')
                                                            ->label('Costo anual')
                                                            ->money('USD'),
                                                        TextEntry::make('total_amount')
                                                            ->label('Total a pagar')
                                                            ->money('USD')
                                                            ->weight('semibold'),
                                                    ]),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        AffiliationCorporateInfolistTab::EXPEDIENTE => Tab::make('Expediente digital')
                            ->icon(Heroicon::OutlinedFolderOpen)
                            ->schema([
                                Section::make('Expediente digital')
                                    ->description('Adjuntos del expediente corporativo (PDF e imágenes).')
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
                                                RepeatableEntry::make('affiliationCorporateDocuments')
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
                                                                    ->action(function (AffiliationCorporateDocument $document, ViewRecord $livewire) {
                                                                        abort_unless(
                                                                            (int) $document->affiliation_corporate_id === (int) $livewire->getRecord()->getKey(),
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
                                                                    ->action(function (AffiliationCorporateDocument $document, ViewRecord $livewire): void {
                                                                        abort_unless(
                                                                            (int) $document->affiliation_corporate_id === (int) $livewire->getRecord()->getKey(),
                                                                            403
                                                                        );

                                                                        if (filled($document->file_path)) {
                                                                            Storage::disk('public')->delete($document->file_path);
                                                                        }

                                                                        $document->delete();

                                                                        $livewire->record->unsetRelation('affiliationCorporateDocuments');
                                                                        $livewire->record->load('affiliationCorporateDocuments');

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
                        AffiliationCorporateInfolistTab::OBSERVACIONES => Tab::make('Observaciones')
                            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                            ->schema([
                                Section::make('Observaciones')
                                    ->description(fn (AffiliationCorporate $record): string => 'Bitácora de notas registradas por los analistas. Total: '.self::loadedRelationCount($record, 'affiliationCorporateObservations').'. Use «Agregar observación» en la cabecera para añadir una nueva.')
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
                                                RepeatableEntry::make('affiliationCorporateObservations')
                                                    ->label('')
                                                    ->placeholder('No hay observaciones registradas para esta afiliación corporativa.')
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
                                                            ->helperText(fn (AffiliationCorporateObservation $record): ?string => $record->created_at?->diffForHumans())
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
                                                            ->getStateUsing(fn (AffiliationCorporateObservation $record): string => $record->createdBy?->name ?? (string) ($record->created_by ?? '—'))
                                                            ->helperText(fn (AffiliationCorporateObservation $record): ?string => $record->createdBy?->email)
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
}
