<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\User;
use App\Support\Filament\UserPermissionCatalog;
use App\Support\Filament\UserPermissionFormUi;
use App\Support\SecurityAudit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Permisos por pantalla: la vista inversa del editor de permisos del usuario.
 * Se elige un permiso y se ve quién lo tiene, desde cuándo y quién se lo dio;
 * desde aquí se da a varias personas a la vez o se quita.
 *
 * Mismo acceso que Usuarios: solo SUPERADMIN.
 */
class UserPermissionsOverview extends Page implements HasTable
{
    use AuthorizesDepartmentNavigation;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'Permisos por pantalla';

    protected static ?string $title = 'Permisos por pantalla';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACIÓN';

    protected static ?string $slug = 'permisos-por-pantalla';

    protected string $view = 'filament.business.pages.user-permissions-overview';

    #[Url(as: 'permiso')]
    public ?int $permissionId = null;

    /**
     * Se revalida en cada petición de Livewire, no solo al entrar.
     */
    public function boot(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function mount(): void
    {
        $this->selectPermission($this->permissionId);
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getSubheading(): ?string
    {
        return 'Elige un permiso para ver quién lo tiene. Desde aquí puedes darlo a varias personas a la vez o quitarlo.';
    }

    public function selectPermission(?int $permissionId): void
    {
        $this->permissionId = $permissionId !== null && isset(UserPermissionCatalog::index()[$permissionId]) ? $permissionId : null;
        $this->resetTable();
    }

    /**
     * @return array{id: int, name: string, group: string, module: string, module_label: string, accent: string}|null
     */
    public function selectedPermission(): ?array
    {
        $entry = $this->permissionId !== null ? (UserPermissionCatalog::index()[$this->permissionId] ?? null) : null;

        if ($entry === null) {
            return null;
        }

        return [
            'id' => (int) $this->permissionId,
            'name' => $entry['name'],
            'group' => $entry['group'],
            'module' => $entry['module'],
            'module_label' => UserPermissionFormUi::moduleDisplayLabel($entry['module']),
            'accent' => UserPermissionFormUi::moduleAccent($entry['module']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'catalog' => UserPermissionCatalog::modules(),
            'holders' => self::holderCounts(),
            'selected' => $this->selectedPermission(),
        ];
    }

    /**
     * Personas con cuenta activa que tienen cada permiso.
     *
     * @return array<int, int>
     */
    public static function holderCounts(): array
    {
        return DB::table('user_permissions')
            ->join('users', 'users.id', '=', 'user_permissions.user_id')
            ->where('users.status', 'ACTIVO')
            ->groupBy('user_permissions.permission_id')
            ->select('user_permissions.permission_id')
            ->selectRaw('COUNT(DISTINCT user_permissions.user_id) as total')
            ->pluck('total', 'permission_id')
            ->mapWithKeys(fn (mixed $total, mixed $id): array => [(int) $id => (int) $total])
            ->all();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => User::query()
                ->join('user_permissions as granted', 'granted.user_id', '=', 'users.id')
                ->where('granted.permission_id', $this->permissionId ?? 0)
                ->select(['users.id', 'users.name', 'users.email', 'users.status', 'users.departament', 'granted.created_at as granted_at', 'granted.created_by as granted_by']))
            ->columns([
                TextColumn::make('name')
                    ->label('Persona')
                    ->description(fn (User $record): string => (string) $record->email)
                    ->searchable(['users.name', 'users.email'])
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(fn (?string $state): string => mb_strtoupper((string) $state) === 'ACTIVO' ? 'success' : 'gray'),
                IconColumn::make('has_module')
                    ->label('Tiene el módulo')
                    ->state(fn (User $record): bool => $this->userHasModule($record))
                    ->boolean()
                    ->tooltip(fn (User $record): string => $this->userHasModule($record)
                        ? 'El permiso está activo.'
                        : 'No tiene el módulo: el permiso no hace nada y se quitará la próxima vez que se edite este usuario.'),
                TextColumn::make('granted_at')
                    ->label('Asignado')
                    ->dateTime('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('granted_by')
                    ->label('Por')
                    ->placeholder('—'),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateIcon(Heroicon::OutlinedKey)
            ->emptyStateHeading(fn (): string => $this->permissionId === null ? 'Elige un permiso' : 'Nadie tiene este permiso')
            ->emptyStateDescription(fn (): string => $this->permissionId === null
                ? 'Búscalo en la lista de la izquierda para ver quién lo tiene.'
                : 'Usa «Dar este permiso a…» para asignarlo.')
            ->headerActions([$this->grantAction()])
            ->recordActions([
                Action::make('revoke')
                    ->label('Quitar')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Quitar el permiso')
                    ->modalDescription(fn (User $record): string => $record->name.' dejará de ver «'.($this->selectedPermission()['name'] ?? '').'» desde su próximo clic en el sistema.')
                    ->modalSubmitActionLabel('Quitar permiso')
                    ->action(fn (User $record) => $this->revoke(new Collection([$record]))),
            ])
            ->toolbarActions([
                BulkAction::make('revokeSelected')
                    ->label('Quitar a seleccionados')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Quitar el permiso a las personas seleccionadas')
                    ->modalDescription('Dejarán de ver esta pantalla o acción desde su próximo clic en el sistema.')
                    ->modalSubmitActionLabel('Quitar permiso')
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => $this->revoke($records)),
            ]);
    }

    private function grantAction(): Action
    {
        return Action::make('grant')
            ->label('Dar este permiso a…')
            ->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn (): bool => $this->permissionId !== null)
            ->modalHeading(fn (): string => 'Dar «'.($this->selectedPermission()['name'] ?? '').'»')
            ->modalSubmitActionLabel('Dar permiso')
            ->schema(fn (): array => [
                Select::make('user_ids')
                    ->label('Personas')
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->placeholder('Escribe un nombre o correo…')
                    ->getSearchResultsUsing(fn (string $search): array => $this->grantCandidates($search))
                    ->getOptionLabelsUsing(fn (array $values): array => User::query()->whereKey($values)->pluck('name', 'id')->all())
                    ->helperText('Solo usuarios activos del personal que aún no lo tienen.'),
                Toggle::make('assign_module')
                    ->label(fn (): string => 'Asignar también el módulo '.($this->selectedPermission()['module_label'] ?? '').' a quien no lo tenga')
                    ->helperText('Sin el módulo, el permiso no tiene efecto.')
                    ->default(true),
            ])
            ->action(function (array $data): void {
                $this->grant(array_map('intval', (array) ($data['user_ids'] ?? [])), (bool) ($data['assign_module'] ?? false));
            });
    }

    /**
     * @return array<int, string>
     */
    private function grantCandidates(string $search): array
    {
        $search = trim($search);

        return User::query()
            ->where('status', 'ACTIVO')
            ->where(fn (Builder $query) => $query->where('email', 'like', '%@tudrencasa.com')->orWhere('is_doctor', true))
            ->whereDoesntHave('permissions', fn (Builder $query) => $query->where('permissions.id', $this->permissionId ?? 0))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')))
            ->orderBy('name')
            ->limit(25)
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @param  list<int>  $userIds
     */
    public function grant(array $userIds, bool $assignModule): void
    {
        $permission = $this->selectedPermission();

        if ($permission === null || $userIds === []) {
            return;
        }

        $actor = (string) Auth::user()?->name;
        $granted = [];
        $modulesAdded = [];

        DB::transaction(function () use ($userIds, $assignModule, $permission, $actor, &$granted, &$modulesAdded): void {
            $users = User::query()->whereKey($userIds)->where('status', 'ACTIVO')->lockForUpdate()->get();

            foreach ($users as $user) {
                $user->permissions()->syncWithoutDetaching([$permission['id'] => ['created_by' => $actor, 'updated_by' => $actor]]);
                $granted[] = $user->name;

                $departments = is_array($user->departament) ? $user->departament : [];

                if ($assignModule && ! in_array($permission['module'], $departments, true)) {
                    $user->departament = [...$departments, $permission['module']];
                    $user->updated_by = $actor;
                    $user->save();
                    $modulesAdded[] = $user->name;
                }
            }
        });

        SecurityAudit::log('AUDIT_USER_PERMISSION_GRANTED', 'business.permissions-by-screen', [
            'permission' => UserPermissionCatalog::describe([$permission['id']])[0],
            'users' => $granted,
            'module_assigned_to' => $modulesAdded,
        ]);

        $this->dispatch('permission-holders-updated', holders: (object) self::holderCounts());

        Notification::make()
            ->success()
            ->title(count($granted) === 1 ? 'Permiso asignado a '.$granted[0] : 'Permiso asignado a '.count($granted).' personas')
            ->body($modulesAdded === [] ? null : 'También se asignó el módulo '.$permission['module_label'].' a '.implode(', ', $modulesAdded).'.')
            ->send();
    }

    /**
     * @param  Collection<int, User>  $records
     */
    public function revoke(Collection $records): void
    {
        $permission = $this->selectedPermission();

        if ($permission === null || $records->isEmpty()) {
            return;
        }

        $names = [];

        DB::transaction(function () use ($records, $permission, &$names): void {
            foreach ($records as $record) {
                $user = User::query()->find($record->getKey());

                if ($user !== null) {
                    $user->permissions()->detach($permission['id']);
                    $names[] = $user->name;
                }
            }
        });

        SecurityAudit::log('AUDIT_USER_PERMISSION_REVOKED', 'business.permissions-by-screen', [
            'permission' => UserPermissionCatalog::describe([$permission['id']])[0],
            'users' => $names,
        ]);

        $this->dispatch('permission-holders-updated', holders: (object) self::holderCounts());

        Notification::make()
            ->success()
            ->title(count($names) === 1 ? 'Permiso quitado a '.$names[0] : 'Permiso quitado a '.count($names).' personas')
            ->send();
    }

    private function userHasModule(User $record): bool
    {
        $module = $this->selectedPermission()['module'] ?? null;

        return $module !== null && is_array($record->departament) && in_array($module, $record->departament, true);
    }
}
