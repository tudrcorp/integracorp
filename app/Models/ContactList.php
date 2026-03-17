<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactList extends Model
{
    //
    protected $table = 'contact_lists';

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'group',
        'group_color',
        'owner__full_name',
        'owner_phone',
        'owner_email',
        'status',
        'created_by',
        'updated_by',
    ];
}
