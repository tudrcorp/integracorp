<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierTipoClinica extends Model
{
    protected $table = 'supplier_tipo_clinicas';

    protected $fillable = [
        'description',
        'created_by',
        'updated_by',
    ];

    public function supplier()
    {
        return $this->hasMany(Supplier::class);
    }
}