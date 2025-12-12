<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierRedGlobal extends Model
{
    protected $table = 'supplier_red_globals';
    
    protected $fillable = [
        'supplier_id',
        'city_id',
        'state_id',
        'name',
        'personal_phone',
        'local_phone',
        'email',
        'address',
        'created_by',
        'updated_by',
    ];
    
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }
}