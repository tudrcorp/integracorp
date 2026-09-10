<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialTelemedicine;

use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\Filament\CommercialNetworkAccess;
use App\Support\Filament\CommercialNetworkTelemedicineScope;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class CommercialTelemedicinePanel
{
    public static function currentPanelId(): string
    {
        return (string) (Filament::getCurrentPanel()?->getId() ?? 'agents');
    }

    /**
     * @return class-string
     */
    public static function patientResourceClass(?string $panelId = null): string
    {
        return match ($panelId ?? self::currentPanelId()) {
            'master' => \App\Filament\Master\Resources\TelemedicinePatients\TelemedicinePatientResource::class,
            'general' => \App\Filament\General\Resources\TelemedicinePatients\TelemedicinePatientResource::class,
            default => \App\Filament\Agents\Resources\TelemedicinePatients\TelemedicinePatientResource::class,
        };
    }

    /**
     * @return class-string
     */
    public static function caseResourceClass(?string $panelId = null): string
    {
        return match ($panelId ?? self::currentPanelId()) {
            'master' => \App\Filament\Master\Resources\TelemedicineCases\TelemedicineCaseResource::class,
            'general' => \App\Filament\General\Resources\TelemedicineCases\TelemedicineCaseResource::class,
            default => \App\Filament\Agents\Resources\TelemedicineCases\TelemedicineCaseResource::class,
        };
    }

    public static function patientsIndexUrl(?int $affiliationId = null, ?int $affiliationCorporateId = null): string
    {
        $resource = self::patientResourceClass();
        $url = $resource::getUrl('index');
        $query = [];

        if ($affiliationId !== null && $affiliationId > 0) {
            $query['affiliation'] = $affiliationId;
        }

        if ($affiliationCorporateId !== null && $affiliationCorporateId > 0) {
            $query['affiliation_corporate'] = $affiliationCorporateId;
        }

        if ($query === []) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($query);
    }

    public static function patientViewUrl(TelemedicinePatient|int $patient): string
    {
        $id = $patient instanceof TelemedicinePatient ? $patient->getKey() : $patient;

        return self::patientResourceClass()::getUrl('view', ['record' => $id]);
    }

    public static function caseViewUrl(TelemedicineCase|int $case): string
    {
        $id = $case instanceof TelemedicineCase ? $case->getKey() : $case;

        return self::caseResourceClass()::getUrl('view', ['record' => $id]);
    }

    public static function authenticatedUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public static function canViewPatients(): bool
    {
        return CommercialNetworkAccess::canViewPatients(self::authenticatedUser());
    }

    public static function canViewCases(): bool
    {
        return CommercialNetworkAccess::canViewCases(self::authenticatedUser());
    }

    public static function recordIsAccessiblePatient(Model $record): bool
    {
        $user = self::authenticatedUser();

        return $user instanceof User
            && $record instanceof TelemedicinePatient
            && CommercialNetworkTelemedicineScope::userCanAccessPatient($user, $record);
    }

    public static function recordIsAccessibleCase(Model $record): bool
    {
        $user = self::authenticatedUser();

        return $user instanceof User
            && $record instanceof TelemedicineCase
            && CommercialNetworkTelemedicineScope::userCanAccessCase($user, $record);
    }
}
