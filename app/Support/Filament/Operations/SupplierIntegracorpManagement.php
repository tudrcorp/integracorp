<?php

declare(strict_types=1);

namespace App\Support\Filament\Operations;

use App\Models\Supplier;
use App\Models\User;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique as UniqueRule;

final class SupplierIntegracorpManagement
{
    private const REPEATER_CARD = 'rounded-2xl border border-slate-200/80 bg-white/90 p-2 shadow-sm dark:border-white/10 dark:bg-slate-900/40';

    /** @var list<string> */
    public const PORTAL_USER_DEPARTAMENTS = ['OPERACIONES'];

    public const DEFAULT_PORTAL_USER_PASSWORD = '12345678';

    /**
     * @return list<string>
     */
    public static function portalUserDepartaments(): array
    {
        return self::PORTAL_USER_DEPARTAMENTS;
    }

    /**
     * SUPERADMIN siempre puede. Un analista de Operaciones solo si el permiso
     * le fue asignado desde el formulario de usuarios.
     */
    public static function userCanManage(): bool
    {
        return BusinessFilamentActionAccess::userCan(
            BusinessFilamentActionPermissionRegistry::MANAGE_SUPPLIER_INTEGRACORP_PROCESSES
        );
    }

    public const ALIAS_MAX_LENGTH = 60;

    public static function aliasInput(): TextInput
    {
        return TextInput::make('integracorp_alias')
            ->label('Alias del proveedor')
            ->placeholder('Ej.: ATENMEDI')
            ->helperText('Este alias aparecerá en la lista de «Proveedor(es) de Servicios» del módulo de Afiliaciones (individuales y corporativas) y se usará para referenciar la información de los afiliados atendidos por este proveedor. Se guarda en MAYÚSCULAS y no puede repetirse entre proveedores.')
            ->visible(fn (Get $get): bool => (bool) $get('gestion_integracorp'))
            ->required(fn (Get $get): bool => (bool) $get('gestion_integracorp'))
            ->disabled(fn (): bool => ! self::userCanManage())
            ->dehydrated(fn (): bool => self::userCanManage())
            ->maxLength(self::ALIAS_MAX_LENGTH)
            ->unique(table: 'suppliers', column: 'integracorp_alias', ignoreRecord: true)
            ->dehydrateStateUsing(fn (mixed $state): ?string => self::normalizeAlias($state))
            ->validationMessages([
                'required' => 'Ingrese el alias del proveedor para habilitar la gestión en Integracorp.',
                'unique' => 'Este alias ya lo usa otro proveedor. Elija uno distinto.',
                'max' => 'El alias admite hasta '.self::ALIAS_MAX_LENGTH.' caracteres.',
            ])
            ->extraInputAttributes([
                'class' => 'uppercase',
                'autocomplete' => 'off',
            ])
            ->columnSpanFull();
    }

    public static function normalizeAlias(mixed $alias): ?string
    {
        if (! is_string($alias) && ! is_numeric($alias)) {
            return null;
        }

        $normalized = Str::upper(Str::squish((string) $alias));

        return $normalized === '' ? null : $normalized;
    }

    public static function portalUsersRepeater(string $repeaterCardClass = self::REPEATER_CARD): Repeater
    {
        return Repeater::make('integracorpAnalysts')
            ->label('Usuarios de acceso a módulos')
            ->relationship('integracorpAnalysts')
            ->visible(fn (Get $get): bool => (bool) $get('gestion_integracorp'))
            ->disabled(fn (): bool => ! self::userCanManage())
            ->dehydrated(fn (): bool => self::userCanManage())
            ->addActionLabel('Agregar usuario')
            ->defaultItems(0)
            ->collapsible()
            ->collapsed()
            ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null)
                ? (string) $state['name']
                : (filled($state['email'] ?? null) ? (string) $state['email'] : 'Nuevo usuario'))
            ->reorderable(false)
            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => self::normalizeIntegracorpUserData($data, creating: true))
            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => self::normalizeIntegracorpUserData($data, creating: false))
            ->extraAttributes([
                'class' => $repeaterCardClass,
            ])
            ->schema([
                Grid::make(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                table: 'users',
                                column: 'email',
                                modifyRuleUsing: function (UniqueRule $rule, Get $get): UniqueRule {
                                    if (filled($get('id'))) {
                                        $rule->ignore($get('id'));
                                    }

                                    return $rule;
                                },
                            )
                            ->validationMessages([
                                'unique' => 'Este correo ya está registrado en el sistema.',
                            ]),
                        Hidden::make('departament')
                            ->default(self::portalUserDepartaments())
                            ->dehydrated(true),
                        Hidden::make('is_proveedor_amd')
                            ->default(true)
                            ->dehydrated(true),
                        Hidden::make('status')
                            ->default('ACTIVO')
                            ->dehydrated(true),
                        Hidden::make('created_by')
                            ->default(fn (): ?string => Auth::user()?->name)
                            ->dehydrated(true),
                        Hidden::make('updated_by')
                            ->default(fn (): ?string => Auth::user()?->name)
                            ->dehydrated(true),
                    ]),
            ])
            ->columnSpanFull();
    }

    public static function deactivateIntegracorpUsers(Supplier $supplier): void
    {
        User::query()
            ->where('supplier_id', $supplier->id)
            ->update([
                'status' => 'INACTIVO',
                'updated_by' => Auth::user()?->name,
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeIntegracorpUserData(array $data, bool $creating): array
    {
        $data['departament'] = self::portalUserDepartaments();
        $data['is_proveedor_amd'] = true;
        $data['doctor_id'] = null;
        $data['status'] = 'ACTIVO';
        $data['updated_by'] = Auth::user()?->name;

        if ($creating) {
            $data['created_by'] = Auth::user()?->name;
            $data['password'] = self::DEFAULT_PORTAL_USER_PASSWORD;
        } else {
            unset($data['password']);
        }

        return $data;
    }

    public static function modulesPanelHtml(?bool $enabled = null): HtmlString
    {
        return new HtmlString(
            view('filament.operations.suppliers.partials.integracorp-modules-panel', [
                'enabled' => $enabled,
            ])->render()
        );
    }

    public static function gestionIntegracorpStatusHtml(Supplier $supplier): HtmlString
    {
        return new HtmlString(
            view('filament.operations.suppliers.gestion-integracorp-status-readonly', [
                'supplier' => $supplier,
            ])->render()
        );
    }

    public static function readOnlyNoticeHtml(): HtmlString
    {
        return new HtmlString(
            '<p class="rounded-xl border border-amber-200/80 bg-amber-50/90 px-3.5 py-2.5 text-xs leading-relaxed text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">'
            .'Solo un SUPERADMIN o un analista de Operaciones con el permiso <span class="font-semibold">Gestión de Procesos en Integracorp</span> puede modificar esta configuración.'
            .'</p>'
        );
    }
}
