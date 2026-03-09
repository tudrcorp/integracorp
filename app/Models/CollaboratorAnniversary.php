<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollaboratorAnniversary extends Model
{
    //
    protected $table = 'collaborator_anniversaries';

    protected $fillable = [
        'rrhh_colaborador_id',
        'image',
        'created_by',
        'updated_by',
    ];

    public function rrhhColaborador()
    {
        return $this->belongsTo(RrhhColaborador::class);
    }

}
