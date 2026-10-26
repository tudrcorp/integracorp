<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\User;
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

    /**
     * @return list<string>
     */
    public static function activeRoleLabels(User $user): array
    {
        $labels = collect(self::groupedDefinitions())
            ->flatten(1)
            ->filter(fn (array $role): bool => (bool) $user->{$role['field']})
            ->pluck('label')
            ->values()
            ->all();

        return $labels === [] ? ['Sin perfiles'] : $labels;
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
     * Roles editables en el formulario, con lo que hacen de verdad en el
     * sistema (revisado contra el código, no contra el nombre del campo).
     *
     * - `effect`: `grants` abre algo; `restricts` limita lo que la persona ve.
     * - `live`: el valor cambia otras pestañas del formulario (Permisos de red,
     *   módulos opcionales), así que se envía al servidor al marcarlo.
     *
     * @return list<array{field: string, label: string, group: string, icon: string, accent: string, effect: string, description: string, live: bool}>
     */
    public static function formCatalog(): array
    {
        return [
            ['field' => 'is_agent', 'label' => 'Agente', 'group' => 'Red comercial', 'icon' => 'heroicon-o-user-circle', 'accent' => '#0891b2', 'effect' => 'grants', 'description' => 'Entra al portal de agentes (/agents) para cotizar y afiliar. Habilita la pestaña «Permisos de red».', 'live' => true],
            ['field' => 'is_subagent', 'label' => 'Subagente', 'group' => 'Red comercial', 'icon' => 'heroicon-o-users', 'accent' => '#0e7490', 'effect' => 'grants', 'description' => 'Entra al portal de subagentes, bajo la red de un agente.', 'live' => false],
            ['field' => 'is_agency', 'label' => 'Agencia', 'group' => 'Red comercial', 'icon' => 'heroicon-o-building-office-2', 'accent' => '#038c4c', 'effect' => 'grants', 'description' => 'Entra al panel de su agencia: Master o General, según el tipo de agencia. Habilita la pestaña «Permisos de red».', 'live' => true],
            ['field' => 'is_accountManagers', 'label' => 'Administrador de cuentas', 'group' => 'Negocios', 'icon' => 'heroicon-o-briefcase', 'accent' => '#d97706', 'effect' => 'restricts', 'description' => 'Restringe lo que ve: en Negocios y Administración solo verá las cotizaciones, afiliaciones, agentes y agencias de su cartera.', 'live' => false],
            ['field' => 'is_business_admin', 'label' => 'Administrador de Negocios', 'group' => 'Negocios', 'icon' => 'heroicon-o-building-storefront', 'accent' => '#075985', 'effect' => 'grants', 'description' => 'En Negocios ve columnas reservadas (fecha de asignación, registrado por) y puede editar campos bloqueados de cotizaciones y afiliaciones.', 'live' => false],
            ['field' => 'is_superAdmin', 'label' => 'Super Administrador de catálogo', 'group' => 'Catálogo de planes', 'icon' => 'heroicon-o-star', 'accent' => '#7c3aed', 'effect' => 'grants', 'description' => 'Entra a Planes, Tarifas, Coberturas, Beneficios y Rangos de edad del panel Administrador. No da acceso total: eso es el módulo Superadministrador, en la pestaña «Módulos».', 'live' => false],
        ];
    }

    /**
     * Indicadores que no se editan aquí: se muestran para que se sepa si la
     * persona los tiene y dónde se asignan.
     *
     * @return list<array{field: string, label: string, icon: string, description: string}>
     */
    public static function readOnlyCatalog(): array
    {
        return [
            ['field' => 'is_admin', 'label' => 'Administrador', 'icon' => 'heroicon-o-shield-check', 'description' => 'Entra al panel Administrador (/admin). No se asigna desde este formulario.'],
            ['field' => 'is_doctor', 'label' => 'Doctor', 'icon' => 'heroicon-o-heart', 'description' => 'Se marca al crear la ficha del médico en telemedicina.'],
            ['field' => 'is_designer', 'label' => 'Diseñador y Marketing', 'icon' => 'heroicon-o-paint-brush', 'description' => 'Recibe el aviso de los videos de notificaciones masivas de Marketing.'],
        ];
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
