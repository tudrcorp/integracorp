<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Capemiac extends Model
{
    protected $table = 'capemiacs';
    
    protected $fillable = [
        'cliente',
        'segmento',
        'rif',
        'telefonoUno',
        'telefonoDos',
        'telefonoTres',
        'email',
        'fecha_registro',
    ];
}