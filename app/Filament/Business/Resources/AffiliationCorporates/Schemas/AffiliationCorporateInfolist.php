<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\AffiliationCorporates\Schemas;

use App\Models\AffiliationCorporate;
use Carbon\Carbon;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

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
                                            ->color('success'),
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
                                            ->formatStateUsing(fn ($state): string => 'AGT-000'.(string) $state),
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
                                            ->placeholder('—'),
                                    ]),
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
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
