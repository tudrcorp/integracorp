<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RrhhDepartamento extends Model
{
    protected $table = 'rrhh_departamentos';

    protected $fillable = [
        'description',
    ];

    public function colaboradores(): HasMany
    {
        return $this->hasMany(RrhhColaborador::class, 'departmento_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(RrhhAsignacion::class, 'departamento_id');
    }

    public function deducciones(): HasMany
    {
        return $this->hasMany(RrhhDeduccion::class, 'departamento_id');
    }
}
