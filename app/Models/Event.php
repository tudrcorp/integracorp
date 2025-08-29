<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $table = 'events';

    protected $fillable = [
        'title',
        'description',
        'image',
        'dateInit',
        'dateEnd',
        'status',
        'created_by',
        'total_guest',
    ];

    public function guests()
    {
        return $this->hasMany(Guest::class);
    }
}