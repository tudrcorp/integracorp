<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogTransactionalR4 extends Model
{
    protected $table = 'log_transactional_r4';
    
    protected $fillable = [
        'code',
        'message',
        'uuid',
    ];
    
}