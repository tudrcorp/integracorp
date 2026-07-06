<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Auth;

final class UserCredentialSynchronizer
{
    public static function syncRelatedRecordsAndAudit(
        User $user,
        string $originalEmail,
        bool $emailChanged,
        bool $passwordChanged,
    ): void {
        if (! $emailChanged && ! $passwordChanged) {
            return;
        }

        $auditDetails = [
            'target_user_id' => $user->id,
            'target_user_name' => $user->name,
            'target_user_email' => $user->email,
            'email_changed' => $emailChanged,
            'password_changed' => $passwordChanged,
            'original_email' => $emailChanged ? $originalEmail : null,
            'related_sync' => [],
        ];

        if ($emailChanged) {
            $agent = self::resolveLinkedAgent($user, $originalEmail);
            if ($agent !== null) {
                $previousAgentEmail = (string) $agent->email;
                $agent->email = $user->email;
                $agent->updated_by = Auth::user()?->name;
                $agent->save();

                $auditDetails['related_sync']['agent'] = [
                    'agent_id' => $agent->id,
                    'code_agent' => $agent->code_agent,
                    'email_from' => $previousAgentEmail,
                    'email_to' => $user->email,
                ];

                SecurityAudit::log(
                    'AUDIT_BUSINESS_USER_AGENT_EMAIL_SYNCED',
                    'business.users.credentials.agent-email-sync',
                    [
                        'target_user_id' => $user->id,
                        'agent_id' => $agent->id,
                        'email_from' => $previousAgentEmail,
                        'email_to' => $user->email,
                    ],
                );
            }

            $agency = self::resolveLinkedAgency($user, $originalEmail);
            if ($agency !== null) {
                $previousAgencyEmail = (string) $agency->email;
                $agency->email = $user->email;
                $agency->updated_by = Auth::user()?->name;
                $agency->save();

                $auditDetails['related_sync']['agency'] = [
                    'agency_id' => $agency->id,
                    'agency_code' => $agency->code,
                    'agency_type' => $user->agency_type,
                    'email_from' => $previousAgencyEmail,
                    'email_to' => $user->email,
                ];

                SecurityAudit::log(
                    'AUDIT_BUSINESS_USER_AGENCY_EMAIL_SYNCED',
                    'business.users.credentials.agency-email-sync',
                    [
                        'target_user_id' => $user->id,
                        'agency_id' => $agency->id,
                        'agency_type' => $user->agency_type,
                        'email_from' => $previousAgencyEmail,
                        'email_to' => $user->email,
                    ],
                );
            }
        }

        SecurityAudit::log(
            'AUDIT_BUSINESS_USER_CREDENTIALS_UPDATED',
            'business.users.credentials.update',
            $auditDetails,
        );
    }

    public static function resolveLinkedAgent(User $user, string $lookupEmail): ?Agent
    {
        if (! $user->is_agent && ! $user->is_subagent) {
            return null;
        }

        $agent = Agent::query()
            ->where('email', $lookupEmail)
            ->first();

        if ($agent !== null) {
            return $agent;
        }

        $codeAgent = trim((string) ($user->code_agent ?? ''));

        if ($codeAgent !== '') {
            return Agent::query()
                ->where('code_agent', $codeAgent)
                ->first();
        }

        return null;
    }

    public static function resolveLinkedAgency(User $user, string $lookupEmail): ?Agency
    {
        if (! $user->is_agency || ! in_array($user->agency_type, ['MASTER', 'GENERAL'], true)) {
            return null;
        }

        $agency = Agency::query()
            ->where('email', $lookupEmail)
            ->first();

        if ($agency !== null) {
            return $agency;
        }

        $codeAgency = trim((string) ($user->code_agency ?? ''));

        if ($codeAgency !== '') {
            return Agency::query()
                ->where('code', $codeAgency)
                ->first();
        }

        return null;
    }
}
