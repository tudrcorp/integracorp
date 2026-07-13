<?php

declare(strict_types=1);

namespace App\Support\Filament\Operations;

use App\Models\Supplier;
use App\Models\User;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
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

    public static function portalUsersRepeater(string $repeaterCardClass = self::REPEATER_CARD): Repeater
    {
        return Repeater::make('integracorpUsers')
            ->label('Usuarios de acceso a módulos')
            ->relationship('integracorpUsers')
            ->visible(fn (Get $get): bool => (bool) $get('gestion_integracorp'))
            ->disabled(fn (): bool => ! OperationsSuperAdmin::check())
            ->dehydrated(fn (): bool => OperationsSuperAdmin::check())
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
            .'Solo un analista con rol <span class="font-semibold">SUPERADMIN</span> puede modificar esta configuración.'
            .'</p>'
        );
    }
}
