<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountManager extends Model
{
    protected $table = 'account_managers';

    protected $fillable = [
        'user_id',
        'full_name',
        'ci',
        'birth_date',
        'phone',
        'address',
        'email',
        'created_by',
        'updated_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all of the comments for the Agency
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function agencies(): HasMany
    {
        return $this->hasMany(Agency::class, 'ownerAccountManagers', 'user_id');
    }

    /**
     * Get all of the comments for the Agency
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class, 'ownerAccountManagers', 'user_id');
    }
}