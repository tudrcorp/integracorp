<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Users\Schemas;

use App\Support\Filament\UserRoleProfiles;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class UserInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private const ROLE_GROUP_CLASS = 'rounded-xl border border-slate-200/60 bg-slate-50/40 p-3 dark:border-white/10 dark:bg-white/[0.03] sm:p-4';

    private const ROLE_CARD_CLASS = 'rounded-xl border border-slate-200/70 bg-gradient-to-br from-white to-slate-50/90 p-3 shadow-sm dark:border-white/10 dark:from-white/[0.06] dark:to-white/[0.02]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('userInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Información del usuario')
                            ->icon(Heroicon::OutlinedUser)
                            ->schema([
                                Section::make('Informacion del Usuario')
                                    ->description('Datos personales y departamento.')
                                    ->icon(Heroicon::OutlinedUser)
                                    ->iconColor('primary')
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                                                    ->schema([
                                                        TextEntry::make('name')
                                                            ->label('Nombre y Apellido del usuario'),
                                                        TextEntry::make('identity_card')
                                                            ->label('Documento de identidad')
                                                            ->icon(Heroicon::OutlinedIdentification)
                                                            ->placeholder('—')
                                                            ->copyable(),
                                                        TextEntry::make('phone')
                                                            ->label('Numero de Telefono'),
                                                        TextEntry::make('birth_date')
                                                            ->label('Fecha de Nacimiento'),
                                                        TextEntry::make('email')
                                                            ->label('Correo Electrónico'),
                                                        TextEntry::make('created_at')
                                                            ->dateTime()
                                                            ->placeholder('-'),
                                                        TextEntry::make('departament')
                                                            ->label('Departamento'),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Rol del usuario')
                            ->icon(Heroicon::OutlinedShieldCheck)
                            ->schema([
                                Section::make('Rol del Usuario')
                                    ->description('Perfiles funcionales asignados al usuario en INTEGRACORP.')
                                    ->icon(Heroicon::OutlinedShieldCheck)
                                    ->iconColor('primary')
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                UserRoleProfiles::summaryEntry(),
                                                Grid::make(1)
                                                    ->schema(self::roleGroupSections())
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    /**
     * @return list<Section>
     */
    private static function roleGroupSections(): array
    {
        return collect(UserRoleProfiles::groupedDefinitions())
            ->map(function (array $roles, string $groupLabel): Section {
                return Section::make($groupLabel)
                    ->compact()
                    ->extraAttributes([
                        'class' => self::ROLE_GROUP_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->schema(UserRoleProfiles::infolistEntriesForGroup($roles, self::ROLE_CARD_CLASS)),
                    ]);
            })
            ->values()
            ->all();
    }
}
