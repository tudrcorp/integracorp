<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collaborator extends Model
{
    protected $table = 'collaborators';

    protected $fillable = [
        'code',
        'full_name',
        'dni',
        'birth_date',
        'company_init_date',
        'departament',
        'position',
        'sex',
        'phone',
        'coorporate_email',
        'alternative_email',
        'status',
        'created_by',
    ];
}