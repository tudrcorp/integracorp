<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\AffiliationCorporates\Schemas;

use App\Filament\Business\Resources\CorporateQuotes\CorporateQuoteResource;
use App\Models\AffiliationCorporate;
use App\Models\AffiliationCorporateDocument;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class AffiliationCorporateInfolist
{
    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

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
                                                ? CorporateQuoteResource::getUrl('edit', ['record' => $record->corporate_quote_id])
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

                Section::make('Pagos e ILS')
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
                                TextEntry::make('vaucher_ils')
                                    ->label('Voucher ILS')
                                    ->icon(Heroicon::OutlinedDocumentText)
                                    ->badge()
                                    ->color('success')
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('date_payment_initial_ils')
                                    ->label('Pago ILS desde')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->formatStateUsing(function (mixed $state): ?string {
                                        if (blank($state)) {
                                            return null;
                                        }
                                        try {
                                            return Carbon::parse($state)->format('d/m/Y');
                                        } catch (\Throwable) {
                                            return (string) $state;
                                        }
                                    })
                                    ->placeholder('—'),
                                TextEntry::make('date_payment_final_ils')
                                    ->label('Pago ILS hasta')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->formatStateUsing(function (mixed $state): ?string {
                                        if (blank($state)) {
                                            return null;
                                        }
                                        try {
                                            return Carbon::parse($state)->format('d/m/Y');
                                        } catch (\Throwable) {
                                            return (string) $state;
                                        }
                                    })
                                    ->placeholder('—'),
                                TextEntry::make('document_ils')
                                    ->label('Archivo documento ILS')
                                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                                    ->color('primary')
                                    ->formatStateUsing(fn (mixed $state): ?string => self::formatStoredFileName($state))
                                    ->url(fn (AffiliationCorporate $record): ?string => filled($record->document_ils)
                                        ? asset('storage/'.$record->document_ils)
                                        : null)
                                    ->openUrlInNewTab()
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Observaciones')
                    ->description('Notas internas o comentarios sobre el trámite.')
                    ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(1)
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('observations')
                                    ->label('Observaciones')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),

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
            ]);
    }
}
