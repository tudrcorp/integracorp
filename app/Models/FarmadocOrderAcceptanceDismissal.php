<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un analista cerró con «Entendido» el aviso de pedido aceptado: no se le vuelve a mostrar.
 */
class FarmadocOrderAcceptanceDismissal extends Model
{
    public $timestamps = false;

    protected $table = 'farmadoc_order_acceptance_dismissals';

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dismissed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FarmadocOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(FarmadocOrder::class, 'farmadoc_order_id');
    }
}
