<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Telemedicine\Concerns\HidesDeletedTelemedicineCaseTraces;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelemedicineCaseChatRead extends Model
{
    use HidesDeletedTelemedicineCaseTraces;

    protected $table = 'telemedicine_case_chat_reads';

    protected $fillable = [
        'telemedicine_case_id',
        'user_id',
        'last_read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TelemedicineCase, $this>
     */
    public function telemedicineCase(): BelongsTo
    {
        return $this->belongsTo(TelemedicineCase::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
