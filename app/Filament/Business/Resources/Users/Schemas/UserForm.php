<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Users\Schemas;

use App\Filament\Business\Pages\UserPermissionsOverview;
use App\Filament\Forms\Components\ModuleSelector;
use App\Filament\Forms\Components\PermissionMatrix;
use App\Filament\Forms\Components\RoleSelector;
use App\Models\Rol;
use App\Models\User;
use App\Support\Filament\CommercialNetworkAccess;
use App\Support\Filament\CommercialNetworkPermissionRegistry;
use App\Support\Filament\UserCredentialSynchronizer;
use App\Support\Filament\UserModulesFormUi;
use App\Support\Filament\UserPermissionCatalog;
use App\Support\Filament\UserPermissionFormUi;
use App\Support\Filament\UserRoleProfiles;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
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
     * Campo del editor de permisos: la lista de ids marcados (todos los módulos).
     */
    public const PERMISSION_IDS_FIELD = 'permission_ids';

    /**
     * Campos del formulario que son permisos y no columnas de `users`.
     *
     * @return list<string>
     */
    public static function allPermissionFieldKeys(): array
    {
        return [self::PERMISSION_IDS_FIELD, CommercialNetworkPermissionRegistry::FIELD_KEY];
    }

    /**
     * Ids de permisos a guardar: los del editor que pertenecen a los módulos
     * asignados (validados contra el catálogo) más los de red comercial.
     *
     * @param  array<string, mixed>  $state
     * @return list<int>
     */
    public static function extractPermissionIdsFromState(array $state): array
    {
        $departments = is_array($state['departament'] ?? null) ? $state['departament'] : [];
        $permissionIds = UserPermissionCatalog::sanitize(
            is_array($state[self::PERMISSION_IDS_FIELD] ?? null) ? $state[self::PERMISSION_IDS_FIELD] : [],
            $departments,
        );

        $commercialValue = $state[CommercialNetworkPermissionRegistry::FIELD_KEY] ?? null;

        if (is_array($commercialValue)) {
            $allowed = array_map('intval', array_keys(CommercialNetworkPermissionRegistry::options()));

            foreach ($commercialValue as $id) {
                if (is_numeric($id) && in_array((int) $id, $allowed, true)) {
                    $permissionIds[] = (int) $id;
                }
            }
        }

        return array_values(array_unique($permissionIds));
    }

    /**
     * @return list<Section>
     */
    public static function commercialNetworkPermissionsTabSchema(): array
    {
        return [
            Section::make('Seguimiento de afiliados')
                ->description('Estos permisos abren menús de solo consulta en el panel del agente o de la agencia. El usuario verá únicamente pacientes y casos de sus afiliados.')
                ->icon(Heroicon::OutlinedHeart)
                ->extraAttributes([
                    'class' => self::IOS_SECTION_CLASS,
                ])
                ->schema([
                    Grid::make(1)
                        ->extraAttributes([
                            'class' => self::IOS_INNER_CLASS,
                        ])
                        ->schema([
                            Placeholder::make('commercial_network_permissions_intro')
                                ->hiddenLabel()
                                ->content(new HtmlString(
                                    '<div class="space-y-2 text-sm text-slate-600 dark:text-slate-300">'
                                    .'<p>Asigna qué puede consultar este usuario en su panel comercial. No otorga acceso al panel de Operaciones ni permite crear o modificar casos.</p>'
                                    .'<ul class="list-disc space-y-1 pl-5">'
                                    .'<li>Pacientes: ficha de telemedicina de sus afiliados.</li>'
                                    .'<li>Gestión de casos: casos abiertos y consultas. Sin historia clínica y sin altas médicas.</li>'
                                    .'</ul>'
                                    .'</div>'
                                )),
                            CheckboxList::make(CommercialNetworkPermissionRegistry::FIELD_KEY)
                                ->hiddenLabel()
                                ->options(fn (): array => CommercialNetworkPermissionRegistry::options())
                                ->descriptions(fn (): array => CommercialNetworkPermissionRegistry::optionDescriptions())
                                ->bulkToggleable()
                                ->columns(1),
                        ]),
                ]),
        ];
    }

    /**
     * Copia los permisos (y, si se pide, los módulos) de otro usuario al
     * formulario. No guarda nada: el analista revisa y guarda.
     */
    public static function copyPermissionsAction(): Action
    {
        return Action::make('copyPermissionsFrom')
            ->label('Copiar permisos de…')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->modalHeading('Copiar permisos de otro usuario')
            ->modalDescription('Carga en este formulario los permisos de un compañero. No se guarda nada hasta que pulses «Guardar»: podrás revisar y ajustar antes.')
            ->modalSubmitActionLabel('Cargar permisos')
            ->schema(fn (?User $record): array => [
                Select::make('source_user_id')
                    ->label('Usuario de referencia')
                    ->placeholder('Escribe un nombre o correo…')
                    ->searchable()
                    ->required()
                    ->getSearchResultsUsing(fn (string $search): array => self::copySourceOptions($search, $record?->getKey()))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => User::query()->whereKey($value)->value('name'))
                    ->helperText('Solo aparecen usuarios activos con permisos asignados.'),
                Radio::make('mode')
                    ->label('¿Qué hacer con los permisos actuales?')
                    ->options([
                        'replace' => 'Reemplazarlos por los del usuario de referencia',
                        'merge' => 'Conservarlos y sumar los del usuario de referencia',
                    ])
                    ->default('replace')
                    ->required(),
                Toggle::make('copy_modules')
                    ->label('Asignar también sus módulos')
                    ->helperText('Sin el módulo, sus permisos se quitarían al guardar. Nunca se quita un módulo que este usuario ya tenga.')
                    ->default(true),
            ])
            ->action(function (array $data, Get $get, Set $set): void {
                $source = User::query()->find($data['source_user_id'] ?? null);

                if ($source === null) {
                    Notification::make()->danger()->title('No se encontró el usuario de referencia')->send();

                    return;
                }

                $sourceIds = UserPermissionCatalog::assignableIdsOf((int) $source->getKey());
                $departments = array_values(array_filter((array) ($get('departament') ?? []), 'is_string'));

                if ((bool) ($data['copy_modules'] ?? false)) {
                    $sourceModules = array_values(array_filter((array) ($source->departament ?? []), fn (mixed $module): bool => is_string($module) && $module !== 'SUPERADMIN'));
                    $departments = array_values(array_unique([...$departments, ...$sourceModules]));
                    $set('departament', $departments);
                }

                $current = array_map('intval', array_filter((array) ($get(self::PERMISSION_IDS_FIELD) ?? []), 'is_numeric'));
                $next = ($data['mode'] ?? 'replace') === 'merge'
                    ? array_values(array_unique([...$current, ...$sourceIds]))
                    : $sourceIds;

                $set(self::PERMISSION_IDS_FIELD, $next);

                $kept = count(UserPermissionCatalog::sanitize($next, $departments));

                Notification::make()
                    ->success()
                    ->title('Permisos cargados de '.$source->name)
                    ->body($kept.' '.($kept === 1 ? 'permiso queda marcado' : 'permisos quedan marcados').'. Revisa el resumen de cambios y pulsa «Guardar» para aplicarlos.')
                    ->send();
            });
    }

    /**
     * @return array<int, string>
     */
    private static function copySourceOptions(string $search, mixed $excludeId): array
    {
        $search = trim($search);

        return User::query()
            ->where('status', 'ACTIVO')
            ->whereHas('permissions')
            ->when($excludeId !== null, fn ($query) => $query->whereKeyNot($excludeId))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')))
            ->withCount('permissions')
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email'])
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->name.' · '.$user->permissions_count.' permisos'])
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
                ->description(fn (Get $get, ?User $record): string => CommercialNetworkAccess::formUserIsCommercialNetwork($get, $record)
                    ? 'Opcionales para agentes y agencias: asígnalos solo si también debe entrar a paneles de INTEGRACORP. Su panel comercial se gestiona en «Permisos de red».'
                    : 'Elige a qué paneles entra esta persona. Las pantallas exactas de cada panel se marcan en la pestaña «Permisos».')
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
                            ModuleSelector::make('departament')
                                ->label('Módulos asignados')
                                ->hiddenLabel()
                                ->default([])
                                ->required(fn (Get $get, ?User $record): bool => ! CommercialNetworkAccess::formUserIsCommercialNetwork($get, $record))
                                ->validationMessages(['required' => 'Asigna al menos un módulo: sin módulos la persona no puede entrar a ningún panel interno.'])
                                ->optionalForCommercialNetwork(fn (Get $get, ?User $record): bool => CommercialNetworkAccess::formUserIsCommercialNetwork($get, $record))
                                ->permissionsField(self::PERMISSION_IDS_FIELD)
                                ->emailField('email'),
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
                                Section::make('Roles del Usuario')
                                    ->description('Los roles cambian qué portal abre la persona o qué parte de la información ve. Cada tarjeta explica qué hace.')
                                    ->icon(Heroicon::OutlinedShieldCheck)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        ...collect(UserRoleProfiles::formCatalog())
                                            ->map(fn (array $role): Hidden => Hidden::make($role['field'])->default(false))
                                            ->all(),
                                        RoleSelector::make('roles_editor')
                                            ->hiddenLabel(),
                                    ]),
                            ]),
                        Tab::make('Permisos de red')
                            ->icon(Heroicon::OutlinedHeart)
                            ->visible(fn (Get $get, ?User $record): bool => CommercialNetworkAccess::formUserIsCommercialNetwork($get, $record))
                            ->schema(self::commercialNetworkPermissionsTabSchema()),
                        Tab::make('Permisos')
                            ->icon(Heroicon::OutlinedKey)
                            ->schema([
                                View::make(UserPermissionFormUi::stylesView())
                                    ->columnSpanFull(),
                                Section::make('Permisos por módulo')
                                    ->key('permissionsByModule')
                                    ->description('Marca las pantallas y acciones a las que puede acceder el usuario, agrupadas igual que en el menú de cada panel.')
                                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                                    ->afterHeader([
                                        self::copyPermissionsAction(),
                                        Action::make('openPermissionsOverview')
                                            ->label('Ver por pantalla')
                                            ->icon(Heroicon::OutlinedKey)
                                            ->color('gray')
                                            ->tooltip('Quién tiene cada permiso, para dar o quitar a varias personas a la vez')
                                            ->url(fn (): string => UserPermissionsOverview::getUrl())
                                            ->openUrlInNewTab(),
                                    ])
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
                                                PermissionMatrix::make(self::PERMISSION_IDS_FIELD)
                                                    ->hiddenLabel()
                                                    ->departmentsField('departament'),
                                            ]),
                                    ]),
                            ]),
                    ]),

                Hidden::make('created_by')->default(fn () => Auth::user()->name),
                Hidden::make('updated_by'),
            ]);
    }
}
