<?php

declare(strict_types=1);

namespace App\Services\PublicAiAgent;

use App\Http\Controllers\NotificationController;
use App\Jobs\SendChatAgentRegistrationWhatsAppJob;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ChatAgentRegistrationService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *   success: bool,
     *   message: string,
     *   data?: array<string, mixed>,
     *   errors?: array<string, list<string>>
     * }
     */
    public function register(array $payload): array
    {
        if (! isset($payload['owner_code']) || trim((string) $payload['owner_code']) === '') {
            $payload['owner_code'] = $this->resolveOwnerCode($payload);
        }

        try {
            $validated = $this->validatePayload($payload);
        } catch (ValidationException $exception) {
            return [
                'success' => false,
                'message' => $this->firstValidationMessage($exception),
                'errors' => $exception->errors(),
            ];
        }

        $plainPassword = Str::password(10, letters: true, numbers: true, symbols: false);
        $phone = $this->normalizePhoneForStorage((string) $validated['phone']);
        $hashedPassword = Hash::make($plainPassword);

        try {
            $registration = DB::transaction(function () use ($validated, $phone, $hashedPassword, $plainPassword): array {
                $agent = new Agent;
                $agent->owner_code = (string) $validated['owner_code'];
                $agent->agent_type_id = 2;
                $agent->name = (string) $validated['name'];
                $agent->email = (string) $validated['email'];
                $agent->phone = $phone;
                $agent->status = 'ACTIVO';
                $agent->save();

                $user = new User;
                $user->code_agent = 'AGT-000'.$agent->id;
                $user->agent_id = $agent->id;
                $user->code_agency = $agent->owner_code;
                $user->is_agent = true;
                $user->name = mb_strtoupper((string) $validated['name']);
                $user->email = (string) $validated['email'];
                $user->password = $hashedPassword;
                $user->link_agent = rtrim((string) config('app.url'), '/').'/agent/c/'.Crypt::encryptString((string) $agent->id);
                $user->status = 'ACTIVO';

                if (Schema::hasTable('users') && Schema::hasColumn('users', 'phone')) {
                    $user->phone = $phone;
                }

                $user->save();

                $agent->sendCartaBienvenida($agent->id, $agent->name, $agent->email, $plainPassword);

                return [
                    'registration_type' => 'chat_agent',
                    'agent_id' => (int) $agent->id,
                    'user_id' => (int) $user->id,
                    'email' => (string) $user->email,
                    'password' => $plainPassword,
                    'code_agent' => (string) $user->code_agent,
                    'login_url' => (string) config('services.chat_agent_registration.portal_login_url'),
                    'name' => (string) $agent->name,
                    'phone' => $phone,
                ];
            });

            $this->queueRegistrationPackageViaWhatsApp($registration);
            $registration['whatsapp_registration_queued'] = true;
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'success' => false,
                'message' => 'No pudimos completar el registro en el sistema. Intenta nuevamente o contacta al equipo de negocios.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Agente registrado exitosamente.',
            'data' => $registration,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function resolveOwnerCode(array $payload): string
    {
        $agencyId = (int) ($payload['selected_agency_id'] ?? 0);

        if ($agencyId > 0 && Schema::hasTable('agencies')) {
            $agency = Agency::query()->find($agencyId);

            if ($agency !== null && filled($agency->code)) {
                return (string) $agency->code;
            }
        }

        return (string) config('services.chat_agent_registration.default_owner_code', 'TDG-100');
    }

    public function whatsappBusinessUrl(): string
    {
        $phone = preg_replace('/\D+/', '', (string) config('services.chat_agent_registration.business_whatsapp_phone', '584127018390')) ?? '';

        return 'https://wa.me/'.$phone;
    }

    public function whatsappBusinessDisplayLabel(): string
    {
        $digits = preg_replace('/\D+/', '', (string) config('services.chat_agent_registration.business_whatsapp_phone', '584127018390')) ?? '';

        if (str_starts_with($digits, '58') && strlen($digits) === 12) {
            return sprintf(
                '+58 %s %s %s',
                substr($digits, 2, 3),
                substr($digits, 5, 3),
                substr($digits, 8, 4),
            );
        }

        return $digits !== '' ? '+'.$digits : '+58 412 701 8390';
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function enrichRegistrationCredentials(array $credentials): array
    {
        $agentId = (int) ($credentials['agent_id'] ?? 0);

        if ($agentId > 0) {
            $agent = Agent::query()->find($agentId);

            if ($agent !== null) {
                if (trim((string) ($credentials['name'] ?? '')) === '' && filled($agent->name)) {
                    $credentials['name'] = (string) $agent->name;
                }

                if (trim((string) ($credentials['phone'] ?? '')) === '' && filled($agent->phone)) {
                    $credentials['phone'] = (string) $agent->phone;
                }

                if (trim((string) ($credentials['email'] ?? '')) === '' && filled($agent->email)) {
                    $credentials['email'] = (string) $agent->email;
                }
            }
        }

        if (
            trim((string) ($credentials['phone'] ?? '')) === ''
            && Schema::hasTable('users')
            && Schema::hasColumn('users', 'phone')
            && ($credentials['user_id'] ?? 0) > 0
        ) {
            $userPhone = User::query()->whereKey((int) $credentials['user_id'])->value('phone');

            if (is_string($userPhone) && trim($userPhone) !== '') {
                $credentials['phone'] = trim($userPhone);
            }
        }

        return $credentials;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function queueRegistrationPackageViaWhatsApp(array $credentials): bool
    {
        if (($credentials['whatsapp_registration_queued'] ?? false) === true) {
            return true;
        }

        $credentials = $this->enrichRegistrationCredentials($credentials);

        $phone = trim((string) ($credentials['phone'] ?? ''));
        $agentId = (int) ($credentials['agent_id'] ?? 0);
        $name = trim((string) ($credentials['name'] ?? ''));

        if ($phone === '' || $agentId <= 0 || $name === '') {
            Log::warning('PUBLIC-CHAT: No se encoló WhatsApp registro agente por datos incompletos.', [
                'agent_id' => $agentId,
                'has_phone' => $phone !== '',
                'has_name' => $name !== '',
            ]);

            return false;
        }

        SendChatAgentRegistrationWhatsAppJob::dispatch([
            ...$credentials,
            'whatsapp_registration_queued' => true,
        ])->afterResponse();

        Log::info('PUBLIC-CHAT: WhatsApp registro agente encolado.', [
            'agent_id' => $agentId,
            'phone' => $phone,
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function sendRegistrationPackageViaWhatsApp(array $credentials): bool
    {
        $credentials = $this->enrichRegistrationCredentials($credentials);

        $phone = trim((string) ($credentials['phone'] ?? ''));
        $agentId = (int) ($credentials['agent_id'] ?? 0);
        $name = trim((string) ($credentials['name'] ?? ''));

        if ($phone === '' || $agentId <= 0 || $name === '') {
            Log::warning('PUBLIC-CHAT: WhatsApp registro agente sin datos completos.', [
                'agent_id' => $agentId,
                'has_phone' => $phone !== '',
                'has_name' => $name !== '',
            ]);

            return false;
        }

        $relativePath = null;

        try {
            ini_set('memory_limit', '2048M');
            $relativePath = $this->ensureWelcomeLetterPdf($agentId, $name);
        } catch (\Throwable $exception) {
            report($exception);
        }

        $textSent = NotificationController::sendIntegracorpBrandWhatsAppCaption(
            $phone,
            $this->buildRegistrationWhatsAppCaptionMessage($name, $credentials),
        );

        $documentSent = false;

        if (is_string($relativePath) && $relativePath !== '') {
            $documentCaption = 'Adjunto encontrarás tu carta de bienvenida oficial de Integracorp.';
            $filename = $this->welcomeLetterFilename($agentId);

            sleep(2);

            $documentSent = NotificationController::sendWhatsAppDocument(
                $phone,
                $documentCaption,
                $relativePath,
                $filename,
            );

            if (! $documentSent) {
                sleep(3);

                $documentSent = NotificationController::sendWhatsAppDocument(
                    $phone,
                    $documentCaption,
                    $relativePath,
                    $filename,
                );
            }
        }

        Log::info('PUBLIC-CHAT: Envío WhatsApp registro agente.', [
            'agent_id' => $agentId,
            'text_sent' => $textSent,
            'document_sent' => $documentSent,
            'welcome_letter_path' => $relativePath,
            'welcome_letter_url' => is_string($relativePath) && $relativePath !== ''
                ? $this->publicStorageDocumentUrl($relativePath)
                : null,
        ]);

        if ($relativePath === null) {
            return $textSent;
        }

        return $textSent && $documentSent;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function buildRegistrationWhatsAppCaptionMessage(string $name, array $credentials): string
    {
        return sprintf(
            'Apreciado/a: *%s*'."\n\n%s",
            $name,
            $this->buildRegistrationWhatsAppChatBody($credentials),
        );
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function buildRegistrationWhatsAppChatBody(array $credentials): string
    {
        $loginUrl = (string) ($credentials['login_url'] ?? config('services.chat_agent_registration.portal_login_url'));

        return sprintf(
            <<<'TEXT'
¡Bienvenido(a) a Integracorp!

Tu registro fue exitoso. Estos son tus datos de acceso:

• Usuario (correo): %s
• Contraseña: %s
• Código de agente: %s
• Portal de agentes: %s

Guarda esta información en un lugar seguro. En el siguiente mensaje recibirás tu carta de bienvenida en PDF.
TEXT,
            (string) ($credentials['email'] ?? 'N/D'),
            (string) ($credentials['password'] ?? 'N/D'),
            (string) ($credentials['code_agent'] ?? 'N/D'),
            $loginUrl,
        );
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function buildRegistrationWhatsAppCaption(array $credentials): string
    {
        $loginUrl = (string) ($credentials['login_url'] ?? config('services.chat_agent_registration.portal_login_url'));

        return sprintf(
            "¡Bienvenido(a) a Integracorp!\n\nTu registro fue exitoso.\n\nDatos de acceso:\n• Usuario (correo): %s\n• Contraseña: %s\n• Código de agente: %s\n• Portal de agentes: %s\n\nAdjunto tu carta de bienvenida.",
            (string) ($credentials['email'] ?? 'N/D'),
            (string) ($credentials['password'] ?? 'N/D'),
            (string) ($credentials['code_agent'] ?? 'N/D'),
            $loginUrl,
        );
    }

    public function welcomeLetterFilename(int $agentId): string
    {
        return 'AGT-000'.$agentId.'.pdf';
    }

    public function welcomeLetterRelativePath(int $agentId): string
    {
        return 'chat-agent-welcome/'.$this->welcomeLetterFilename($agentId);
    }

    public function publicStorageDocumentUrl(string $relativePath): string
    {
        return rtrim((string) config('parameters.PUBLIC_URL'), '/').'/'.ltrim($relativePath, '/');
    }

    public function ensureWelcomeLetterPdf(int $agentId, string $name): string
    {
        $relativePath = $this->welcomeLetterRelativePath($agentId);
        $fullPath = public_path('storage/'.$relativePath);
        $legacyRelativePath = $this->welcomeLetterFilename($agentId);
        $legacyFullPath = public_path('storage/'.$legacyRelativePath);
        $directory = dirname($fullPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (
            file_exists($legacyFullPath)
            && filesize($legacyFullPath) >= 100
            && (! file_exists($fullPath) || filesize($fullPath) < 100)
        ) {
            copy($legacyFullPath, $fullPath);
        }

        if (! file_exists($fullPath) || filesize($fullPath) < 100) {
            ini_set('memory_limit', '2048M');

            $pdf = Pdf::loadView('documents.carta-bienvenida-agente', [
                'id' => $agentId,
                'name' => $name,
            ]);
            $pdf->save($fullPath);
            unset($pdf);
        }

        if (! file_exists($fullPath) || filesize($fullPath) < 100) {
            throw new \RuntimeException('No se pudo generar la carta de bienvenida para WhatsApp.');
        }

        return $relativePath;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validatePayload(array $payload): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^[0-9]+$/'],
            'owner_code' => ['required', 'string', 'max:100'],
            'classification' => ['nullable', 'string', Rule::in(['agent', 'subagent'])],
        ];

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'email')) {
            $rules['email'][] = Rule::unique('users', 'email');
        }

        if (Schema::hasTable('agents') && Schema::hasColumn('agents', 'email')) {
            $rules['email'][] = Rule::unique('agents', 'email');
        }

        return Validator::make($payload, $rules)->validate();
    }

    private function normalizePhoneForStorage(string $phoneDigits): string
    {
        $digits = preg_replace('/\D+/', '', $phoneDigits) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            return '+58'.substr($digits, 1);
        }

        if (str_starts_with($digits, '58')) {
            return '+'.$digits;
        }

        return '+58'.$digits;
    }

    private function firstValidationMessage(ValidationException $exception): string
    {
        $message = collect($exception->errors())->flatten()->filter()->first();

        return is_string($message) && $message !== ''
            ? $message
            : 'Los datos enviados no son válidos para completar el registro.';
    }
}
