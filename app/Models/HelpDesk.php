<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpDesk extends Model
{
    protected $table = "help_desks";

    protected $fillable = [
        'description',
        'image',
        'help_desk_category_id',
        'module',
        'status',
        'created_by',
        'updated_by',
    ];

    public $timestamps = false;

    public function help_desk_category()
    {
        return $this->belongsTo(HelpDeskCategory::class);
    }
}
