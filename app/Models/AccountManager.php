<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountManager extends Model
{
    protected $table = 'account_managers';

    protected $fillable = [
        'user_id',
        'full_name',
        'phone',
        'address',
        'email',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}