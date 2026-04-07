<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpDesk extends Model
{
    protected $table = 'help_desks';

    protected $fillable = [
        'description',
        'image',
        'priority',
        'status',
        'created_by',
        'updated_by',
        'rrhh_colaborador_id',
        'observation',
    ];

    public function help_desk_category()
    {
        return $this->belongsTo(HelpDeskCategory::class);
    }

    public function rrhhColaborador()
    {
        return $this->belongsTo(RrhhColaborador::class);
    }
}
