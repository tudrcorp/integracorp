<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CrmPushSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmPushSubscription extends Model
{
    /** @use HasFactory<CrmPushSubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'endpoint',
        'public_key',
        'auth_token',
        'content_encoding',
    ];
}
