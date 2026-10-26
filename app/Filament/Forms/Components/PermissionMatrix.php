<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Support\Filament\UserPermissionCatalog;
use Filament\Forms\Components\Field;

/**
 * Editor de permisos de un usuario: módulos en pestañas, grupos de menú en
 * tarjetas, buscador global, «solo marcados» y resumen de cambios.
 *
 * Todo ocurre en el navegador (Alpine): marcar, buscar o cambiar de módulo no
 * va al servidor. El estado (lista de ids) viaja al guardar, y el servidor lo
 * vuelve a filtrar con {@see UserPermissionCatalog::sanitize()}.
 */
class PermissionMatrix extends Field
{
    /**
     * @var view-string
     */
    protected string $view = 'filament.forms.components.permission-matrix';

    protected string $departmentsField = 'departament';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);

        $this->afterStateHydrated(static function (PermissionMatrix $component, mixed $state): void {
            $component->state(array_values(array_unique(array_map('intval', array_filter(is_array($state) ? $state : [], 'is_numeric')))));
        });

        $this->dehydrateStateUsing(static fn (mixed $state): array => array_values(array_unique(array_map('intval', array_filter(is_array($state) ? $state : [], 'is_numeric')))));
    }

    /**
     * Campo hermano con los módulos del usuario: solo se editan esos.
     */
    public function departmentsField(string $field): static
    {
        $this->departmentsField = $field;

        return $this;
    }

    public function getDepartmentsStatePath(): string
    {
        $path = $this->getStatePath();
        $position = strrpos($path, '.');

        return ($position === false ? '' : substr($path, 0, $position + 1)).$this->departmentsField;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getCatalog(): array
    {
        return UserPermissionCatalog::modules();
    }
}
