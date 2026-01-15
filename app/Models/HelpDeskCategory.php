<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpDeskCategory extends Model
{
    protected $table = "help_desk_categories";

    protected $fillable = [
        'code',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    public $timestamps = false;

    public function help_desks()
    {
        return $this->hasMany(HelpDesk::class);
    }
}
