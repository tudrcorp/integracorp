<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierObservacion extends Model
{
    protected $table = 'supplier_observacions';

    protected $fillable = [
        'supplier_id',
        'observation',
        'created_by',
    ];
    
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}