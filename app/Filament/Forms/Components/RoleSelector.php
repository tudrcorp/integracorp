<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Models\User;
use App\Support\Filament\UserRoleProfiles;
use Filament\Forms\Components\Field;

/**
 * Roles del usuario en tarjetas. No guarda estado propio: cada tarjeta está
 * enlazada en el navegador a su campo booleano (`is_agent`, `is_agency`…),
 * que viaja al guardar. Solo los roles que cambian otras pestañas se envían
 * al servidor al marcarlos.
 */
class RoleSelector extends Field
{
    /**
     * @var view-string
     */
    protected string $view = 'filament.forms.components.role-selector';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dehydrated(false);
    }

    public function siblingStatePath(string $field): string
    {
        $path = $this->getStatePath();
        $position = strrpos($path, '.');

        return ($position === false ? '' : substr($path, 0, $position + 1)).$field;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRoles(): array
    {
        return array_map(fn (array $role): array => $role + ['path' => $this->siblingStatePath($role['field'])], UserRoleProfiles::formCatalog());
    }

    /**
     * Indicadores de solo lectura con su valor actual.
     *
     * @return list<array{field: string, label: string, icon: string, description: string, active: bool}>
     */
    public function getReadOnlyRoles(): array
    {
        $record = $this->getRecord();

        return array_map(static fn (array $role): array => $role + [
            'active' => $record instanceof User && (bool) $record->{$role['field']},
        ], UserRoleProfiles::readOnlyCatalog());
    }
}
