<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhiteCompanyFee extends Model
{
    /** @use HasFactory<\Database\Factories\WhiteCompanyFeeFactory> */
    use HasFactory;

    protected $table = 'white_company_fees';

    protected $fillable = [
        'white_company_id',
        'fee_id',
        'sale_price',
        'neta',
        'status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'neta' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<WhiteCompany, $this>
     */
    public function whiteCompany(): BelongsTo
    {
        return $this->belongsTo(WhiteCompany::class);
    }

    /**
     * @return BelongsTo<Fee, $this>
     */
    public function fee(): BelongsTo
    {
        return $this->belongsTo(Fee::class);
    }
}
