<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\RrhhCargo;
use App\Models\RrhhColaborador;
use App\Models\RrhhDepartamento;
use App\Support\OrgStructure\OrgStructureLiveSync;
use Illuminate\Database\Eloquent\Model;

/**
 * Mantiene el organigrama al día con RRHH sin que nadie toque la estructura:
 * un egreso deja el puesto vacante y cualquier cambio visible (nombre, foto,
 * cargo, contacto) invalida el árbol en caché.
 *
 * Se registra para colaboradores, cargos y departamentos.
 */
class OrgStructureRrhhObserver
{
    /**
     * Columnas del colaborador que se ven en el organigrama.
     *
     * @var list<string>
     */
    private const VISIBLE_COLUMNS = ['fullName', 'status', 'avatar', 'cargo_id', 'departmento_id', 'telefonoCorporativo', 'emailCorporativo'];

    public function created(Model $model): void
    {
        OrgStructureLiveSync::touch();
    }

    public function updated(Model $model): void
    {
        if ($model instanceof RrhhColaborador) {
            if ($model->wasChanged('status') && ! OrgStructureLiveSync::isActiveStatus($model->status)) {
                OrgStructureLiveSync::colaboradorLeft((int) $model->getKey(), (string) $model->fullName);

                return;
            }

            if (! $model->wasChanged(self::VISIBLE_COLUMNS)) {
                return;
            }
        }

        if (($model instanceof RrhhCargo || $model instanceof RrhhDepartamento) && ! $model->wasChanged(['description', 'departamento_id'])) {
            return;
        }

        OrgStructureLiveSync::touch();
    }

    public function deleted(Model $model): void
    {
        if ($model instanceof RrhhColaborador) {
            OrgStructureLiveSync::colaboradorLeft((int) $model->getKey(), (string) $model->fullName);

            return;
        }

        OrgStructureLiveSync::touch();
    }
}
