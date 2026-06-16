<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Users\Schemas;

use App\Models\Rol;
use App\Support\Filament\UserFormPermissionOptions;
use App\Support\Filament\UserRoleProfiles;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class UserForm
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    /**
     * Módulos/departamentos desde la tabla rols (campo name).
     *
     * @return array<int, string>
     */
    public static function getDepartamentModules(): array
    {
        return Rol::query()->orderBy('name')->pluck('name')->values()->all();
    }

    /**
     * @return list<Section>
     */
    public static function permissionModuleSections(): array
    {
        return collect(self::getDepartamentModules())
            ->map(function (string $module): Section {
                return Section::make($module)
                    ->description(function (Get $get) use ($module): string {
                        $selected = count($get("permissions_{$module}") ?? []);
                        $total = UserFormPermissionOptions::countForModule($module);

                        if ($total === 0) {
                            return 'Sin permisos configurados para este módulo.';
                        }

                        return "{$selected} de {$total} permisos seleccionados";
                    })
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->collapsible()
                    ->collapsed(fn (Get $get): bool => empty($get("permissions_{$module}")))
                    ->visible(fn (Get $get): bool => in_array($module, $get('departament') ?? [], true))
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(1)
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                CheckboxList::make("permissions_{$module}")
                                    ->label('Permisos disponibles')
                                    ->options(
                                        fn (): array => UserFormPermissionOptions::optionsForModule($module)
                                    )
                                    ->bulkToggleable()
                                    ->live()
                                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                                    ->gridDirection('row'),
                            ]),
                    ]);
            })
            ->all();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('userFormTabs')
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
                                    ->description('Informacion principal del usuario INTEGRACORP.')
                                    ->icon(Heroicon::OutlinedUserCircle)
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
                                                        TextInput::make('name')
                                                            ->label('Nombre y Apellido del usuario')
                                                            ->prefixIcon('heroicon-m-user')
                                                            ->required(),
                                                        TextInput::make('phone')
                                                            ->label('Telefono')
                                                            ->prefixIcon('heroicon-m-phone')
                                                            ->tel(),
                                                        DatePicker::make('birth_date')
                                                            ->label('Fecha de Nacimiento')
                                                            ->format('d/m/Y')
                                                            ->native(false)
                                                            ->displayFormat('d/m/Y'),
                                                        TextInput::make('email')
                                                            ->label('Correo Electrónico')
                                                            ->prefixIcon('heroicon-m-envelope')
                                                            ->required()
                                                            ->email()
                                                            ->hiddenOn('edit'),
                                                        Select::make('departament')
                                                            ->label('Módulo(s) al que pertenece el usuario')
                                                            ->prefixIcon('heroicon-m-squares-2x2')
                                                            ->required()
                                                            ->live()
                                                            ->helperText('Define los módulos con acceso. Los permisos se configuran en la pestaña «Permisos».')
                                                            ->options(fn () => Rol::query()->orderBy('name')->pluck('name', 'name'))
                                                            ->multiple(),
                                                        Select::make('status')
                                                            ->label('Estado')
                                                            ->prefixIcon('heroicon-m-signal')
                                                            ->required()
                                                            ->options([
                                                                'ACTIVO' => 'ACTIVO',
                                                                'INACTIVO' => 'INACTIVO',
                                                            ]),
                                                    ]),
                                            ]),
                                    ]),
                                Section::make('Contraseña del Usuario')
                                    ->description('Credenciales de acceso al sistema.')
                                    ->icon(Heroicon::OutlinedKey)
                                    ->collapsible()
                                    ->collapsed()
                                    ->hiddenOn('edit')
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make(['default' => 1, 'sm' => 2])
                                                    ->schema([
                                                        TextInput::make('password')
                                                            ->label('Contraseña')
                                                            ->required()
                                                            ->password()
                                                            ->revealable(),
                                                        TextInput::make('password_confirmation')
                                                            ->label('Confirmar Contraseña')
                                                            ->password()
                                                            ->required()
                                                            ->revealable(),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Roles del usuario')
                            ->icon(Heroicon::OutlinedShieldCheck)
                            ->schema([
                                Section::make('Roles del Usuario')
                                    ->description('Activa los perfiles funcionales que aplican a este usuario.')
                                    ->icon(Heroicon::OutlinedShieldCheck)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make(1)
                                                    ->schema(
                                                        collect(UserRoleProfiles::formGroupedDefinitions())
                                                            ->map(function (array $roles, string $groupLabel): Fieldset {
                                                                return Fieldset::make($groupLabel)
                                                                    ->schema([
                                                                        Grid::make(['default' => 1, 'sm' => 2])
                                                                            ->schema(UserRoleProfiles::formTogglesForGroup($roles)),
                                                                    ]);
                                                            })
                                                            ->values()
                                                            ->all()
                                                    ),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Permisos')
                            ->icon(Heroicon::OutlinedKey)
                            ->schema([
                                Section::make('Permisos por módulo')
                                    ->description('Otorga permisos granulares según los módulos asignados al usuario.')
                                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Placeholder::make('permissions_empty_state')
                                                    ->hiddenLabel()
                                                    ->content(new HtmlString(
                                                        '<div class="rounded-xl border border-dashed border-slate-300/90 bg-slate-50/80 px-4 py-6 text-center dark:border-white/15 dark:bg-white/[0.03]">'
                                                        .'<p class="text-sm font-medium text-slate-700 dark:text-slate-200">Sin módulos seleccionados</p>'
                                                        .'<p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Selecciona al menos un módulo en la pestaña «Información del usuario» para configurar permisos.</p>'
                                                        .'</div>'
                                                    ))
                                                    ->visible(fn (Get $get): bool => empty($get('departament'))),
                                                ...self::permissionModuleSections(),
                                            ]),
                                    ]),
                            ]),
                    ]),

                Hidden::make('created_by')->default(fn () => Auth::user()->name),
                Hidden::make('updated_by'),
            ]);
    }
}
