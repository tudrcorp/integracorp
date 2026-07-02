<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyAssociate extends Model
{
    protected $fillable = [
        'company_id',
        'company_responsible_id',
        'full_name',
        'identity_card',
        'birth_date',
        'age',
        'email',
        'phone',
        'sex',
        'contact_full_name',
        'contact_phone',
        'contact_email',
        'identity_document',
        'registered_at',
        'vaucher_ils',
        'date_init',
        'date_end',
        'document_ils',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'age' => 'integer',
            'registered_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(CompanyResponsible::class, 'company_responsible_id');
    }

    public function hasVoucherIls(): bool
    {
        return filled($this->vaucher_ils)
            || filled($this->date_init)
            || filled($this->date_end)
            || filled($this->document_ils);
    }

    public function voucherIlsDocumentUrl(): ?string
    {
        return filled($this->document_ils)
            ? asset('storage/'.$this->document_ils)
            : null;
    }
}
