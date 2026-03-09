<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationOnCallUser extends Model
{
    //
    protected $table = 'operation_on_call_users';

    protected $fillable = [
        'rrhh_colaborador_id',
        'name',
        'email',
        'phone',
        'date_OnCall',
        'status',
        'created_by',
        'updated_by',
    ];

    public function rrhh_colaborador()
    {
        return $this->belongsTo(RrhhColaborador::class);
    }

    public function created_by()
    {
        return $this->belongsTo(User::class);
    }

    public function updated_by()
    {
        return $this->belongsTo(User::class);
    }
}
