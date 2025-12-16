<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierStatusConvenio extends Model
{
    protected $table = 'supplier_status_convenios';

    protected $fillable = [
        'supplier_id',
        'description',
        'created_by',
        'updated_by',
    ];
    
    public function supplier()
    {
        return $this->hasMany(Supplier::class);
    }
}