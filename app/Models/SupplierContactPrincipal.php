<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierContactPrincipal extends Model
{
    protected $table = 'supplier_contact_principals';

    protected $fillable = [
        'supplier_id',
        'name',
        'position',
        'phone',
        'email',
        'personal_phone',
        'local_phone',
        'departament',
        'created_by',
        'updated_by',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}