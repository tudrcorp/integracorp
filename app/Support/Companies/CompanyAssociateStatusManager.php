<?php

declare(strict_types=1);

namespace App\Support\Companies;

use App\Enums\CompanyAssociateStatus;
use App\Models\CompanyAssociate;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class CompanyAssociateStatusManager
{
    public const int ANNULMENT_REASON_MIN_LENGTH = 10;

    public static function defaultStatus(): CompanyAssociateStatus
    {
        return CompanyAssociateStatus::ActivoSinVaucherIls;
    }

    public static function markActiveAfterVoucherIls(CompanyAssociate $associate): void
    {
        $current = self::resolveStatus($associate);

        if ($current === CompanyAssociateStatus::Anulado) {
            return;
        }

        if ($current === CompanyAssociateStatus::Activo) {
            return;
        }

        $associate->forceFill([
            'status' => CompanyAssociateStatus::Activo->value,
        ])->save();
    }

    /**
     * Anula un asociado activo y libera el día consumido del responsable.
     *
     * @throws ValidationException
     * @throws RuntimeException
     */
    public static function annul(CompanyAssociate $associate, string $reason): CompanyAssociate
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < self::ANNULMENT_REASON_MIN_LENGTH) {
            throw ValidationException::withMessages([
                'annulment_reason' => 'Debe indicar la razón de la anulación (mínimo '
                    .self::ANNULMENT_REASON_MIN_LENGTH.' caracteres).',
            ]);
        }

        $current = self::resolveStatus($associate);

        if ($current === null || ! $current->canBeAnnulled()) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden anular asociados en estatus ACTIVO.',
            ]);
        }

        return DB::transaction(function () use ($associate, $reason): CompanyAssociate {
            $locked = CompanyAssociate::query()
                ->whereKey($associate->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedStatus = self::resolveStatus($locked);

            if ($lockedStatus === null || ! $lockedStatus->canBeAnnulled()) {
                throw new RuntimeException('El asociado ya no está en estatus ACTIVO.');
            }

            $locked->forceFill([
                'status' => CompanyAssociateStatus::Anulado->value,
                'annulment_reason' => $reason,
                'annulled_at' => now(),
            ])->save();

            SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_ANNULLED', 'company-associates.annul', [
                'associate_id' => $locked->getKey(),
                'company_id' => $locked->company_id,
                'company_responsible_id' => $locked->company_responsible_id,
                'previous_status' => CompanyAssociateStatus::Activo->value,
                'status' => CompanyAssociateStatus::Anulado->value,
                'annulment_reason' => $reason,
                'days_returned' => CompanyAssociateRegistrar::DAYS_PER_REGISTRATION,
            ]);

            return $locked->fresh() ?? $locked;
        });
    }

    public static function resolveStatus(CompanyAssociate $associate): ?CompanyAssociateStatus
    {
        $raw = $associate->status;

        if ($raw instanceof CompanyAssociateStatus) {
            return $raw;
        }

        return CompanyAssociateStatus::fromStored($raw);
    }
}
