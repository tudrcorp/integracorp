<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgencyNoteBlog extends Model
{
    protected $table = 'agency_note_blogs';

    protected $fillable = ['agency_id', 'note', 'created_by'];

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }
}