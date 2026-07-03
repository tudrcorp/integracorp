<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\User;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Icons\Heroicon;

class UserRoleProfiles
{
    /**
     * @return array<string, list<array{field: string, label: string, icon: Heroicon}>}
     */
    public static function groupedDefinitions(): array
    {
        return [
            'Perfiles comerciales' => [
                ['field' => 'is_agent', 'label' => 'Agente', 'icon' => Heroicon::OutlinedUserCircle],
                ['field' => 'is_subagent', 'label' => 'Subagente', 'icon' => Heroicon::OutlinedUsers],
                ['field' => 'is_agency', 'label' => 'Agencia', 'icon' => Heroicon::OutlinedBuildingOffice2],
                ['field' => 'is_accountManagers', 'label' => 'Administrador de Cuentas', 'icon' => Heroicon::OutlinedBriefcase],
            ],
            'Perfiles administrativos' => [
                ['field' => 'is_admin', 'label' => 'Administrador', 'icon' => Heroicon::OutlinedShieldCheck],
                ['field' => 'is_superAdmin', 'label' => 'Super Administrador', 'icon' => Heroicon::OutlinedStar],
                ['field' => 'is_business_admin', 'label' => 'Administrador de Negocios', 'icon' => Heroicon::OutlinedBuildingStorefront],
            ],
            'Perfiles especializados' => [
                ['field' => 'is_doctor', 'label' => 'Doctor', 'icon' => Heroicon::OutlinedHeart],
                ['field' => 'is_designer', 'label' => 'Diseñador y Marketing', 'icon' => Heroicon::OutlinedPaintBrush],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function fields(): array
    {
        return collect(self::groupedDefinitions())
            ->flatten(1)
            ->pluck('field')
            ->values()
            ->all();
    }

    public static function activeCount(User $user): int
    {
        return collect(self::fields())
            ->filter(fn (string $field): bool => (bool) $user->{$field})
            ->count();
    }

    public static function totalCount(): int
    {
        return count(self::fields());
    }

    public static function summaryEntry(): TextEntry
    {
        return TextEntry::make('roles_active_summary')
            ->label('Resumen de perfiles')
            ->state(function (User $record): string {
                $active = self::activeCount($record);
                $total = self::totalCount();

                return "{$active} de {$total} perfiles activos";
            })
            ->badge()
            ->color(fn (User $record): string => self::activeCount($record) > 0 ? 'success' : 'gray')
            ->icon(Heroicon::OutlinedIdentification)
            ->columnSpanFull();
    }

    /**
     * @return array<string, list<array{field: string, label: string, icon: Heroicon}>}
     */
    public static function formGroupedDefinitions(): array
    {
        return [
            'Perfiles comerciales' => [
                ['field' => 'is_agent', 'label' => 'Agente', 'icon' => Heroicon::OutlinedUserCircle],
                ['field' => 'is_subagent', 'label' => 'Subagente', 'icon' => Heroicon::OutlinedUsers],
                ['field' => 'is_agency', 'label' => 'Agencia', 'icon' => Heroicon::OutlinedBuildingOffice2],
                ['field' => 'is_accountManagers', 'label' => 'Administrador de Cuentas', 'icon' => Heroicon::OutlinedBriefcase],
            ],
            'Perfiles administrativos' => [
                ['field' => 'is_superAdmin', 'label' => 'Super Administrador', 'icon' => Heroicon::OutlinedStar],
                ['field' => 'is_business_admin', 'label' => 'Administrador de Negocios', 'icon' => Heroicon::OutlinedBuildingStorefront],
            ],
        ];
    }

    /**
     * @param  list<array{field: string, label: string, icon: Heroicon}>  $roles
     * @return list<Toggle>
     */
    public static function formTogglesForGroup(array $roles, string $groupLabel): array
    {
        return collect($roles)
            ->map(function (array $role) use ($groupLabel): Toggle {
                return Toggle::make($role['field'])
                    ->label(UserRoleFormUi::toggleLabelHtml($role))
                    ->inline(true)
                    ->onColor('success')
                    ->extraFieldWrapperAttributes([
                        'class' => UserRoleFormUi::toggleCardClass($groupLabel, $role['field']),
                    ]);
            })
            ->all();
    }

    /**
     * @param  list<array{field: string, label: string, icon: Heroicon}>  $roles
     * @return list<TextEntry>
     */
    public static function infolistEntriesForGroup(array $roles, string $cardClass): array
    {
        return collect($roles)
            ->map(function (array $role) use ($cardClass): TextEntry {
                return TextEntry::make($role['field'])
                    ->label($role['label'])
                    ->icon($role['icon'])
                    ->formatStateUsing(fn (?bool $state): string => $state ? 'Asignado' : 'No asignado')
                    ->badge()
                    ->color(fn (?bool $state): string => $state ? 'success' : 'gray')
                    ->iconColor(fn (?bool $state): string => $state ? 'success' : 'gray')
                    ->extraAttributes([
                        'class' => $cardClass,
                    ]);
            })
            ->all();
    }
}
