<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Support\Filament\UserModulesFormUi;
use App\Support\Filament\UserPermissionCatalog;
use Closure;
use Filament\Forms\Components\Concerns\HasNestedRecursiveValidationRules;
use Filament\Forms\Components\Contracts\HasNestedRecursiveValidationRules as HasNestedRecursiveValidationRulesContract;
use Filament\Forms\Components\Field;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * Módulos del usuario en tarjetas: paneles de trabajo y accesos especiales,
 * con lo que abre cada uno, lo que exige y cuántos permisos tiene marcados.
 *
 * Marcar o desmarcar no va al servidor (antes cada clic recargaba el
 * formulario completo): el estado se comparte en el navegador con el editor
 * de permisos y viaja al guardar.
 */
class ModuleSelector extends Field implements HasNestedRecursiveValidationRulesContract
{
    use HasNestedRecursiveValidationRules;

    /**
     * @var view-string
     */
    protected string $view = 'filament.forms.components.module-selector';

    protected string $permissionsField = 'permission_ids';

    protected string $emailField = 'email';

    protected bool|Closure $optionalForCommercialNetwork = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);

        $this->afterStateHydrated(static function (ModuleSelector $component, mixed $state): void {
            $component->state(self::normalize($state));
        });

        $this->dehydrateStateUsing(static fn (mixed $state): array => self::normalize($state));

        $this->rule('array');
        $this->nestedRecursiveRule(static fn (): In => Rule::in(array_column(UserModulesFormUi::moduleCatalog(), 'key')));
    }

    /**
     * @return list<string>
     */
    public static function normalize(mixed $state): array
    {
        $values = is_array($state) ? $state : [];

        return array_values(array_unique(array_filter($values, static fn (mixed $value): bool => is_string($value) && trim($value) !== '')));
    }

    public function permissionsField(string $field): static
    {
        $this->permissionsField = $field;

        return $this;
    }

    /**
     * Para agentes y agencias los módulos internos son opcionales: cambia el
     * texto del estado vacío.
     */
    public function optionalForCommercialNetwork(bool|Closure $condition = true): static
    {
        $this->optionalForCommercialNetwork = $condition;

        return $this;
    }

    public function isOptionalForCommercialNetwork(): bool
    {
        return (bool) $this->evaluate($this->optionalForCommercialNetwork);
    }

    public function emailField(string $field): static
    {
        $this->emailField = $field;

        return $this;
    }

    public function siblingStatePath(string $field): string
    {
        $path = $this->getStatePath();
        $position = strrpos($path, '.');

        return ($position === false ? '' : substr($path, 0, $position + 1)).$field;
    }

    public function getPermissionsStatePath(): string
    {
        return $this->siblingStatePath($this->permissionsField);
    }

    public function getEmailStatePath(): string
    {
        return $this->siblingStatePath($this->emailField);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getModules(): array
    {
        return UserModulesFormUi::moduleCatalog();
    }

    /**
     * Permiso => módulo, para contar los marcados de cada módulo.
     *
     * @return array<int, string>
     */
    public function getPermissionModules(): array
    {
        return array_map(static fn (array $entry): string => $entry['module'], UserPermissionCatalog::index());
    }
}
