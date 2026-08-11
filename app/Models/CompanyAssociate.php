<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CompanyAssociateStatus;
use Illuminate\Database\Eloquent\Builder;
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
        'flight_date',
        'flight_time',
        'sex',
        'state_id',
        'city_id',
        'observations',
        'contact_full_name',
        'contact_phone',
        'contact_email',
        'identity_document',
        'identity_documents',
        'registered_at',
        'registration_start_date',
        'registration_end_date',
        'registration_period_days',
        'status',
        'annulment_reason',
        'annulled_at',
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
            'registration_start_date' => 'date',
            'registration_end_date' => 'date',
            'registration_period_days' => 'integer',
            'status' => CompanyAssociateStatus::class,
            'annulled_at' => 'datetime',
            'flight_date' => 'date',
            'identity_documents' => 'array',
        ];
    }

    /**
     * @param  Builder<CompanyAssociate>  $query
     * @return Builder<CompanyAssociate>
     */
    public function scopeConsumesRegistrationDay(Builder $query): Builder
    {
        return $query->where('status', '!=', CompanyAssociateStatus::Anulado->value);
    }

    public function isAnnulled(): bool
    {
        return $this->status === CompanyAssociateStatus::Anulado;
    }

    public function canBeAnnulled(): bool
    {
        return $this->status instanceof CompanyAssociateStatus
            && $this->status->canBeAnnulled();
    }

    /**
     * @return list<string>
     */
    public function identityDocumentPaths(): array
    {
        $documents = $this->identity_documents;

        if (is_array($documents) && $documents !== []) {
            return array_values(array_filter(
                $documents,
                fn (mixed $path): bool => is_string($path) && filled($path),
            ));
        }

        if (filled($this->identity_document)) {
            return [(string) $this->identity_document];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    public function identityDocumentUrls(): array
    {
        return array_map(
            fn (string $path): string => asset('storage/'.$path),
            $this->identityDocumentPaths(),
        );
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(CompanyResponsible::class, 'company_responsible_id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
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
