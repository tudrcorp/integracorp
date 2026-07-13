<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Users\Schemas;

use App\Models\Rol;
use App\Models\User;
use App\Support\Filament\UserCredentialSynchronizer;
use App\Support\Filament\UserFormPermissionOptions;
use App\Support\Filament\UserModulesFormUi;
use App\Support\Filament\UserPermissionFormUi;
use App\Support\Filament\UserRoleFormUi;
use App\Support\Filament\UserRoleProfiles;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class UserForm
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    /** @var list<string> */
    private const PERMISSIONS_EXCLUDED_MODULES = [
        'SISTEMAS',
        'SUPERADMIN',
        'TELEMEDICINA',
    ];

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
     * Módulos visibles en la pestaña de permisos granulares.
     *
     * @return array<int, string>
     */
    public static function getPermissionAssignableModules(): array
    {
        return array_values(array_filter(
            self::getDepartamentModules(),
            fn (string $module): bool => ! in_array($module, self::PERMISSIONS_EXCLUDED_MODULES, true),
        ));
    }

    /**
     * Clave segura para Livewire (evita guiones/espacios en nombres de módulo como MODERADOR-INTRANET).
     */
    public static function permissionFieldKey(string $module): string
    {
        return 'permissions_mod_'.Str::slug($module, '_');
    }

    public static function permissionGroupFieldKey(string $module, string $navigationGroup): string
    {
        return self::permissionFieldKey($module).'_nav_'.Str::slug($navigationGroup, '_');
    }

    /**
     * @return list<string>
     */
    public static function allPermissionFieldKeys(): array
    {
        $keys = [];

        foreach (self::getPermissionAssignableModules() as $module) {
            foreach (array_keys(UserFormPermissionOptions::groupedOptionsForModule($module)) as $navigationGroup) {
                $keys[] = self::permissionGroupFieldKey($module, $navigationGroup);
            }

            $keys[] = self::permissionFieldKey($module);
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  array<string, mixed>  $state
     * @return list<int>
     */
    public static function extractPermissionIdsFromState(array $state): array
    {
        $departments = is_array($state['departament'] ?? null)
            ? array_values(array_filter(
                $state['departament'],
                fn (mixed $department): bool => is_string($department) && trim($department) !== '',
            ))
            : [];

        $permissionIds = [];

        foreach (self::getPermissionAssignableModules() as $module) {
            if (! in_array($module, $departments, true)) {
                continue;
            }

            foreach (array_keys(UserFormPermissionOptions::groupedOptionsForModule($module)) as $navigationGroup) {
                $key = self::permissionGroupFieldKey($module, $navigationGroup);
                $value = $state[$key] ?? null;

                if (is_array($value)) {
                    foreach ($value as $id) {
                        $permissionIds[] = (int) $id;
                    }
                }
            }

            $legacyKey = self::permissionFieldKey($module);
            $legacyValue = $state[$legacyKey] ?? null;

            if (is_array($legacyValue)) {
                foreach ($legacyValue as $id) {
                    $permissionIds[] = (int) $id;
                }
            }
        }

        return array_values(array_unique($permissionIds));
    }

    public static function moduleFromPermissionFieldKey(string $fieldKey): ?string
    {
        if (! str_starts_with($fieldKey, 'permissions_mod_')) {
            return null;
        }

        $fieldSlug = substr($fieldKey, strlen('permissions_mod_'));

        if (str_contains($fieldSlug, '_nav_')) {
            $fieldSlug = Str::before($fieldSlug, '_nav_');
        }

        foreach (self::getDepartamentModules() as $module) {
            if (Str::slug($module, '_') === $fieldSlug) {
                return $module;
            }
        }

        return null;
    }

    /**
     * @return list<Section>
     */
    public static function permissionModuleSections(): array
    {
        return collect(self::getPermissionAssignableModules())
            ->map(function (string $module): Section {
                $total = UserFormPermissionOptions::countForModule($module);
                $groupedOptions = UserFormPermissionOptions::groupedOptionsForModule($module);
                $groupCount = count($groupedOptions);

                return Section::make(UserPermissionFormUi::moduleDisplayLabel($module))
                    ->description(UserPermissionFormUi::moduleMenuSubtitle($module))
                    ->icon(UserPermissionFormUi::moduleIcon($module))
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn (Get $get): bool => in_array($module, $get('departament') ?? [], true))
                    ->extraAttributes([
                        'class' => UserPermissionFormUi::moduleSectionClass($module),
                    ])
                    ->schema([
                        Placeholder::make('permission_module_header_'.Str::slug($module, '_'))
                            ->hiddenLabel()
                            ->content(UserPermissionFormUi::moduleHeaderHtml($module, $total, $groupCount)),
                        Grid::make(1)
                            ->extraAttributes([
                                'class' => 'user-perm-groups-stack',
                            ])
                            ->schema(
                                collect($groupedOptions)
                                    ->map(function (array $options, string $navigationGroup) use ($module): Section {
                                        $optionCount = count($options);

                                        return Section::make()
                                            ->key('user_perm_'.Str::slug($module, '_').'_'.Str::slug($navigationGroup, '_'))
                                            ->heading(UserPermissionFormUi::groupHeaderHtml($navigationGroup, $optionCount, $module))
                                            ->collapsible()
                                            ->collapsed($optionCount > 8)
                                            ->compact()
                                            ->extraAttributes([
                                                'class' => UserPermissionFormUi::groupCardClass($module),
                                            ])
                                            ->schema([
                                                Grid::make(1)
                                                    ->extraAttributes([
                                                        'class' => 'user-perm-checkbox-shell',
                                                    ])
                                                    ->schema([
                                                        CheckboxList::make(self::permissionGroupFieldKey($module, $navigationGroup))
                                                            ->hiddenLabel()
                                                            ->options($options)
                                                            ->bulkToggleable()
                                                            ->searchable($optionCount >= 5)
                                                            ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                                                            ->gridDirection('row')
                                                            ->extraAttributes([
                                                                'class' => 'user-perm-checkbox-list',
                                                            ]),
                                                    ]),
                                            ]);
                                    })
                                    ->values()
                                    ->all()
                            ),
                    ]);
            })
            ->all();
    }

    /**
     * @return array<int, mixed>
     */
    public static function modulesTabSchema(): array
    {
        return [
            View::make(UserModulesFormUi::stylesView())
                ->columnSpanFull(),
            Section::make('Paneles INTEGRACORP')
                ->description('Selecciona los módulos a los que tendrá acceso este usuario.')
                ->icon(Heroicon::OutlinedSquares2x2)
                ->extraAttributes([
                    'class' => self::IOS_SECTION_CLASS,
                ])
                ->schema([
                    Grid::make(1)
                        ->extraAttributes([
                            'class' => self::IOS_INNER_CLASS.' user-modules-tab-inner',
                        ])
                        ->schema([
                            Placeholder::make('modules_intro')
                                ->hiddenLabel()
                                ->content(UserModulesFormUi::modulesIntroHtml()),
                            Placeholder::make('modules_selection_summary')
                                ->hiddenLabel()
                                ->content(fn (Get $get): HtmlString => UserModulesFormUi::selectionSummaryHtml($get('departament'))),
                            Placeholder::make('proveedor_amd_notice')
                                ->hiddenLabel()
                                ->visible(fn (?User $record): bool => (bool) ($record?->is_proveedor_amd))
                                ->content(new HtmlString(
                                    '<div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">'
                                    .'<p class="font-semibold">Tipo de usuario: Proveedor AMD</p>'
                                    .'<p class="mt-1 leading-relaxed opacity-90">'
                                    .'Este usuario es de portal de proveedor. El acceso al módulo Operaciones se gestiona abajo; el tipo Proveedor AMD no es un módulo de la lista.'
                                    .'</p>'
                                    .'</div>'
                                )),
                            CheckboxList::make('departament')
                                ->label('Módulos asignados')
                                ->options(fn (): array => UserModulesFormUi::moduleOptions())
                                ->columns(['default' => 1, 'lg' => 2])
                                ->gridDirection('row')
                                ->bulkToggleable()
                                ->searchable()
                                ->required()
                                ->live()
                                ->extraAttributes([
                                    'class' => 'user-modules-checkbox-list',
                                ]),
                            Placeholder::make('modules_permissions_hint')
                                ->hiddenLabel()
                                ->content(UserModulesFormUi::permissionsHintHtml()),
                        ]),
                ]),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('userFormTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->persistTabInQueryString('userTab')
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
                                                        TextInput::make('identity_card')
                                                            ->label('Documento de identidad')
                                                            ->prefixIcon('heroicon-m-identification')
                                                            ->placeholder('Ej: V-12345678')
                                                            ->maxLength(20)
                                                            ->required(fn (?User $record): bool => $record === null)
                                                            ->unique(table: User::class, ignoreRecord: true)
                                                            ->helperText('Formato sugerido: V-12345678 o E-12345678'),
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
                        Tab::make('Módulos')
                            ->icon(Heroicon::OutlinedSquares2x2)
                            ->schema(self::modulesTabSchema()),
                        Tab::make('Correo y contraseña')
                            ->icon(Heroicon::OutlinedEnvelope)
                            ->visibleOn('edit')
                            ->schema([
                                Section::make('Credenciales de acceso')
                                    ->description('Actualiza el correo y la contraseña del usuario. Si es agente o agencia, el correo también se sincronizará en su ficha comercial.')
                                    ->icon(Heroicon::OutlinedKey)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Placeholder::make('credentials_sync_hint')
                                                    ->hiddenLabel()
                                                    ->content(function (?User $record): HtmlString {
                                                        if ($record === null) {
                                                            return new HtmlString('');
                                                        }

                                                        $hints = [];

                                                        if ($record->is_agent || $record->is_subagent) {
                                                            $agent = UserCredentialSynchronizer::resolveLinkedAgent($record, (string) $record->email);
                                                            $hints[] = $agent !== null
                                                                ? 'Agente vinculado: '.e((string) ($agent->code_agent ?? 'AGT-000'.$agent->id)).' · '.e((string) $agent->name)
                                                                : 'Perfil agente: se sincronizará el correo en la tabla de agentes al guardar.';
                                                        }

                                                        if ($record->is_agency && in_array($record->agency_type, ['MASTER', 'GENERAL'], true)) {
                                                            $agency = UserCredentialSynchronizer::resolveLinkedAgency($record, (string) $record->email);
                                                            $hints[] = $agency !== null
                                                                ? 'Agencia vinculada: '.e((string) ($agency->code ?? '')).' · '.e((string) ($agency->name_corporative ?? ''))
                                                                : 'Perfil agencia '.$record->agency_type.': se sincronizará el correo en la tabla de agencias al guardar.';
                                                        }

                                                        if ($hints === []) {
                                                            return new HtmlString(
                                                                '<p class="text-sm text-slate-600 dark:text-slate-300">Los cambios de credenciales quedan registrados en la traza de seguridad del sistema.</p>'
                                                            );
                                                        }

                                                        return new HtmlString(
                                                            '<ul class="list-disc space-y-1 pl-5 text-sm text-slate-600 dark:text-slate-300">'
                                                            .implode('', array_map(
                                                                fn (string $hint): string => '<li>'.$hint.'</li>',
                                                                $hints,
                                                            ))
                                                            .'</ul>'
                                                        );
                                                    }),
                                                Grid::make(['default' => 1, 'sm' => 2])
                                                    ->schema([
                                                        TextInput::make('email')
                                                            ->label('Correo electrónico')
                                                            ->prefixIcon('heroicon-m-envelope')
                                                            ->required()
                                                            ->email()
                                                            ->unique(table: User::class, ignoreRecord: true)
                                                            ->rules([
                                                                fn (?User $record): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                                                                    if (! is_string($value) || $record === null) {
                                                                        return;
                                                                    }

                                                                    if ($record->is_agent || $record->is_subagent) {
                                                                        $agent = UserCredentialSynchronizer::resolveLinkedAgent($record, (string) $record->email);
                                                                        $agentRule = (new Unique('agents', 'email'))
                                                                            ->when(
                                                                                $agent !== null,
                                                                                fn (Unique $rule): Unique => $rule->ignore($agent->id),
                                                                            );

                                                                        if (\Illuminate\Support\Facades\Validator::make(
                                                                            ['email' => $value],
                                                                            ['email' => $agentRule],
                                                                        )->fails()) {
                                                                            $fail('El correo ya está registrado en otro agente.');
                                                                        }
                                                                    }

                                                                    if ($record->is_agency && in_array($record->agency_type, ['MASTER', 'GENERAL'], true)) {
                                                                        $agency = UserCredentialSynchronizer::resolveLinkedAgency($record, (string) $record->email);
                                                                        $agencyRule = (new Unique('agencies', 'email'))
                                                                            ->when(
                                                                                $agency !== null,
                                                                                fn (Unique $rule): Unique => $rule->ignore($agency->id),
                                                                            );

                                                                        if (\Illuminate\Support\Facades\Validator::make(
                                                                            ['email' => $value],
                                                                            ['email' => $agencyRule],
                                                                        )->fails()) {
                                                                            $fail('El correo ya está registrado en otra agencia.');
                                                                        }
                                                                    }
                                                                },
                                                            ]),
                                                        TextInput::make('password')
                                                            ->label('Nueva contraseña')
                                                            ->password()
                                                            ->revealable()
                                                            ->dehydrated(fn (?string $state): bool => filled($state))
                                                            ->minLength(8)
                                                            ->same('password_confirmation')
                                                            ->helperText('Déjala en blanco si no deseas cambiar la contraseña.'),
                                                        TextInput::make('password_confirmation')
                                                            ->label('Confirmar contraseña')
                                                            ->password()
                                                            ->revealable()
                                                            ->dehydrated(false),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Roles del usuario')
                            ->icon(Heroicon::OutlinedShieldCheck)
                            ->schema([
                                View::make(UserRoleFormUi::stylesView())
                                    ->columnSpanFull(),
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
                                                            ->map(function (array $roles, string $groupLabel): Section {
                                                                return Section::make($groupLabel)
                                                                    ->compact()
                                                                    ->extraAttributes([
                                                                        'class' => UserRoleFormUi::groupShellClass($groupLabel),
                                                                    ])
                                                                    ->schema([
                                                                        Grid::make(['default' => 1, 'md' => 2])
                                                                            ->extraAttributes([
                                                                                'class' => UserRoleFormUi::togglesGridClass(),
                                                                            ])
                                                                            ->schema(UserRoleProfiles::formTogglesForGroup($roles, $groupLabel)),
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
                                View::make(UserPermissionFormUi::stylesView())
                                    ->columnSpanFull(),
                                Section::make('Permisos por módulo')
                                    ->description('Asigna los ítems de menú a los que puede acceder el usuario. Los permisos se muestran agrupados igual que en la barra lateral del panel.')
                                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS.' user-perm-tab-inner',
                                            ])
                                            ->schema([
                                                Placeholder::make('permissions_intro')
                                                    ->hiddenLabel()
                                                    ->content(UserPermissionFormUi::permissionsIntroHtml()),
                                                Placeholder::make('permissions_empty_state')
                                                    ->hiddenLabel()
                                                    ->content(UserPermissionFormUi::permissionsEmptyStateHtml())
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
