<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\PlanGenerators\PlanGeneratorPreAffiliationSession;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Carbon;

class UserSessionAuditTracker
{
    public static function onLogin(Login $event): void
    {
        $user = $event->user;
        if ($user === null) {
            return;
        }

        $panel = self::resolvePanelFromRequestPath((string) request()->path());
        $startedAt = now();

        session()->put(self::sessionKey((int) $user->getAuthIdentifier()), [
            'started_at' => $startedAt->toIso8601String(),
            'panel' => $panel,
        ]);

        SecurityAudit::log('AUDIT_USER_SESSION_LOGIN', 'auth.session.login', [
            'panel' => $panel,
            'login_at' => $startedAt->toIso8601String(),
            'session_guard' => $event->guard,
        ], $user);
    }

    public static function onLogout(Logout $event): void
    {
        $user = $event->user;
        if ($user === null) {
            return;
        }

        $sessionKey = self::sessionKey((int) $user->getAuthIdentifier());
        $sessionInfo = session()->get($sessionKey, []);

        $startedAtRaw = is_array($sessionInfo) ? ($sessionInfo['started_at'] ?? null) : null;
        $loginPanel = is_array($sessionInfo) ? (string) ($sessionInfo['panel'] ?? 'unknown') : 'unknown';
        $logoutPanel = self::resolvePanelFromRequestPath((string) request()->path());

        $startedAt = is_string($startedAtRaw) ? Carbon::parse($startedAtRaw) : null;
        $durationSecondsRaw = $startedAt instanceof Carbon ? $startedAt->diffInSeconds(now()) : null;
        $durationSeconds = $durationSecondsRaw === null
            ? null
            : (int) max(0, floor((float) $durationSecondsRaw));

        SecurityAudit::log('AUDIT_USER_SESSION_LOGOUT', 'auth.session.logout', [
            'panel' => $logoutPanel,
            'login_panel' => $loginPanel,
            'logout_at' => now()->toIso8601String(),
            'session_guard' => $event->guard,
            'session_duration_seconds' => $durationSeconds,
            'session_duration_human' => self::durationLabel($durationSeconds),
            'session_started_at' => $startedAt?->toIso8601String(),
        ], $user);

        PlanGeneratorPreAffiliationSession::forget();

        session()->forget($sessionKey);
    }

    private static function sessionKey(int $userId): string
    {
        return 'security_audit.session.'.$userId;
    }

    private static function resolvePanelFromRequestPath(string $path): string
    {
        $segment = strtolower((string) explode('/', trim($path, '/'))[0]);

        return match ($segment) {
            'administration' => 'administration',
            'business' => 'business',
            'operations' => 'operations',
            'marketing' => 'marketing',
            'agents' => 'agents',
            'general' => 'general',
            'master' => 'master',
            'admin' => 'admin',
            'telemedicina' => 'telemedicina',
            default => 'unknown',
        };
    }

    private static function durationLabel(int|float|null $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        $seconds = (int) max(0, floor((float) $seconds));

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
    }
}
