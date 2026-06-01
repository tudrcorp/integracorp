<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\TelemedicineDoctors\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class TelemedicineDoctorInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private const IOS_DOCTOR_HERO_OUTER = 'relative overflow-hidden rounded-[1.75rem] border border-sky-200/75 bg-gradient-to-b from-sky-50/98 via-white to-slate-50/92 shadow-[0_18px_50px_-14px_rgba(14,165,233,0.28),0_1px_0_0_rgba(255,255,255,0.85)_inset] ring-1 ring-sky-300/45 backdrop-blur-[2px] dark:border-sky-500/30 dark:from-sky-950/55 dark:via-gray-900/96 dark:to-slate-950/92 dark:shadow-[0_22px_60px_-18px_rgba(56,189,248,0.14)] dark:ring-sky-400/25';

    private const IOS_DOCTOR_HERO_INNER = 'relative rounded-[1.25rem] border border-white/90 bg-white/90 p-5 shadow-[inset_0_1px_0_0_rgba(255,255,255,0.95),0_10px_28px_-10px_rgba(15,23,42,0.1)] backdrop-blur-md dark:border-white/12 dark:bg-white/[0.07] dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.05),0_12px_32px_-12px_rgba(0,0,0,0.35)] sm:p-6';

    private const SIGNATURE_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5 min-w-0 max-w-full overflow-hidden';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('telemedicineDoctorInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Perfil del médico')
                            ->icon(Heroicon::OutlinedUserCircle)
                            ->schema([
                                Section::make('Perfil del doctor(a)')
                                    ->description('Identidad y datos de contacto del expediente médico.')
                                    ->icon(Heroicon::OutlinedUserCircle)
                                    ->extraAttributes([
                                        'class' => self::IOS_DOCTOR_HERO_OUTER,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 5])
                                            ->extraAttributes([
                                                'class' => self::IOS_DOCTOR_HERO_INNER,
                                            ])
                                            ->schema([
                                                ImageEntry::make('image')
                                                    ->label('Foto de perfil')
                                                    ->imageHeight(120)
                                                    ->circular()
                                                    ->columnSpan(['default' => 1, 'sm' => 1, 'lg' => 1]),
                                                TextEntry::make('full_name')
                                                    ->label('Nombre completo')
                                                    ->icon(Heroicon::OutlinedUser)
                                                    ->weight('bold')
                                                    ->size(TextSize::Large)
                                                    ->formatStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper($state) : null)
                                                    ->placeholder('—'),
                                                TextEntry::make('nro_identificacion')
                                                    ->label('Número de identificación')
                                                    ->icon(Heroicon::OutlinedIdentification)
                                                    ->prefix('V-')
                                                    ->badge()
                                                    ->color('success')
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('email')
                                                    ->label('Correo electrónico')
                                                    ->icon(Heroicon::OutlinedEnvelope)
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('phone')
                                                    ->label('Teléfono')
                                                    ->icon(Heroicon::OutlinedPhone)
                                                    ->copyable()
                                                    ->badge()
                                                    ->color('info')
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Credenciales profesionales')
                            ->icon(Heroicon::OutlinedAcademicCap)
                            ->schema([
                                Section::make('Credenciales profesionales')
                                    ->description('Especialidad y códigos de registro profesional.')
                                    ->icon(Heroicon::OutlinedAcademicCap)
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('specialty')
                                                    ->label('Especialidad')
                                                    ->icon(Heroicon::OutlinedAcademicCap)
                                                    ->badge()
                                                    ->color('primary')
                                                    ->placeholder('—'),
                                                TextEntry::make('code_mpps')
                                                    ->label('Código MPPS')
                                                    ->icon(Heroicon::OutlinedHashtag)
                                                    ->badge()
                                                    ->color('info')
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('code_cm')
                                                    ->label('Código CM')
                                                    ->icon(Heroicon::OutlinedHashtag)
                                                    ->badge()
                                                    ->color('info')
                                                    ->copyable()
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Firma digital')
                            ->icon(Heroicon::OutlinedPencilSquare)
                            ->schema([
                                Section::make('Firma digital')
                                    ->description('Sello utilizado en informes y documentos médicos.')
                                    ->icon(Heroicon::OutlinedPencilSquare)
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD.' min-w-0 overflow-hidden',
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1])
                                            ->extraAttributes([
                                                'class' => self::SIGNATURE_INNER_CLASS,
                                            ])
                                            ->schema([
                                                ImageEntry::make('signature')
                                                    ->label('Firma')
                                                    ->imageHeight(120)
                                                    ->imageWidth('100%')
                                                    ->extraAttributes([
                                                        'class' => 'min-w-0 max-w-full overflow-hidden',
                                                    ])
                                                    ->extraImgAttributes([
                                                        'class' => 'block max-h-32 max-w-full w-full object-contain object-left rounded-lg',
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
