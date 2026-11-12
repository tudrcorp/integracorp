<?php

declare(strict_types=1);

namespace App\Filament\Business\Concerns;

use Livewire\Attributes\Locked;

/**
 * Evita que «Guardar» en la edición de una afiliación pise la tarifa y la cuota con los valores
 * que tenía el formulario al abrirse. Mientras la página está abierta, las inclusiones y
 * exclusiones de afiliados (en la tabla de abajo) ya recalculan esos totales en la base.
 *
 * Si el analista no cambió nada que los recalcule en el formulario, no se escriben y queda lo de
 * la base. Si sí cambiaron (p. ej. un recálculo intencional en el formulario), se guardan.
 */
trait KeepsLiveAffiliationTotalsOnSave
{
    /** @var array<string, float|null> */
    #[Locked]
    public array $totalsAtFill = [];

    /** @var list<string> */
    protected static array $liveTotalFields = ['fee_anual', 'total_amount'];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (static::$liveTotalFields as $field) {
            $this->totalsAtFill[$field] = isset($data[$field]) && is_numeric($data[$field]) ? round((float) $data[$field], 2) : null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        foreach (static::$liveTotalFields as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $submitted = is_numeric($data[$field]) ? round((float) $data[$field], 2) : null;
            if ($submitted === ($this->totalsAtFill[$field] ?? null)) {
                unset($data[$field]);
            }
        }

        return $data;
    }
}
