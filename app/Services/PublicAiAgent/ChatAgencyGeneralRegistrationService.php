<?php

declare(strict_types=1);

namespace App\Services\PublicAiAgent;

use App\Http\Controllers\AgencyController;
use App\Http\Controllers\NotificationController;
use App\Jobs\SendChatAgencyGeneralRegistrationWhatsAppJob;
use App\Models\Agency;
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

class ChatAgencyGeneralRegistrationService
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
        $ownerCode = (string) $validated['owner_code'];

        try {
            $registration = DB::transaction(function () use ($validated, $phone, $hashedPassword, $plainPassword, $ownerCode): array {
                $identity = AgencyController::reserveNextAgencyIdentity();

                $agency = new Agency;
                $agency->id = $identity['id'];
                $agency->owner_code = AgencyController::resolveOwnerCodeForAgency(3, $identity['code'], $ownerCode);
                $agency->code = $identity['code'];
                $agency->agency_type_id = 3;
                $agency->name_corporative = (string) $validated['name_corporative'];
                $agency->email = (string) $validated['email'];
                $agency->phone = $phone;
                $agency->status = 'ACTIVO';
                $this->applyTaxIdToAgency($agency, (string) $validated['tax_id']);
                $agency->save();

                $user = new User;
                $user->name = $agency->name_corporative;
                $user->email = $agency->email;
                $user->password = $hashedPassword;
                $user->is_agency = true;
                $user->code_agency = $agency->code;
                $user->agency_type = 'GENERAL';
                $user->link_agency = rtrim((string) config('app.url'), '/').'/agency/c/'.Crypt::encryptString((string) $agency->code);
                $user->status = 'ACTIVO';

                if (Schema::hasTable('users') && Schema::hasColumn('users', 'phone')) {
                    $user->phone = $phone;
                }

                $user->save();

                $agency->sendCartaBienvenida($agency->code, $agency->name_corporative, $agency->email, $plainPassword);

                return [
                    'registration_type' => 'agency_general',
                    'agency_id' => (int) $agency->id,
                    'user_id' => (int) $user->id,
                    'email' => (string) $user->email,
                    'password' => $plainPassword,
                    'code_agency' => (string) $agency->code,
                    'owner_code' => (string) $agency->owner_code,
                    'login_url' => $this->portalLoginUrl(),
                    'name' => (string) $agency->name_corporative,
                    'phone' => $phone,
                ];
            });

            $this->queueRegistrationPackageViaWhatsApp($registration);
            $registration['whatsapp_registration_queued'] = true;
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'success' => false,
                'message' => 'No pudimos completar el registro de la agencia general. Intenta nuevamente o contacta al equipo de negocios.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Agencia general registrada exitosamente.',
            'data' => $registration,
        ];
    }

    public function portalLoginUrl(): string
    {
        return (string) config(
            'services.chat_agency_general_registration.portal_login_url',
            rtrim((string) config('app.url'), '/').'/general/login',
        );
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function enrichRegistrationCredentials(array $credentials): array
    {
        $agencyId = (int) ($credentials['agency_id'] ?? 0);

        if ($agencyId > 0) {
            $agency = Agency::query()->find($agencyId);

            if ($agency !== null) {
                if (trim((string) ($credentials['name'] ?? '')) === '' && filled($agency->name_corporative)) {
                    $credentials['name'] = (string) $agency->name_corporative;
                }

                if (trim((string) ($credentials['phone'] ?? '')) === '' && filled($agency->phone)) {
                    $credentials['phone'] = (string) $agency->phone;
                }

                if (trim((string) ($credentials['email'] ?? '')) === '' && filled($agency->email)) {
                    $credentials['email'] = (string) $agency->email;
                }

                if (trim((string) ($credentials['code_agency'] ?? '')) === '' && filled($agency->code)) {
                    $credentials['code_agency'] = (string) $agency->code;
                }

                if (trim((string) ($credentials['owner_code'] ?? '')) === '' && filled($agency->owner_code)) {
                    $credentials['owner_code'] = (string) $agency->owner_code;
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

        $credentials['login_url'] ??= $this->portalLoginUrl();

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
        $agencyId = (int) ($credentials['agency_id'] ?? 0);
        $name = trim((string) ($credentials['name'] ?? ''));

        if ($phone === '' || $agencyId <= 0 || $name === '') {
            Log::warning('PUBLIC-CHAT: No se encoló WhatsApp registro agencia general por datos incompletos.', [
                'agency_id' => $agencyId,
                'has_phone' => $phone !== '',
                'has_name' => $name !== '',
            ]);

            return false;
        }

        SendChatAgencyGeneralRegistrationWhatsAppJob::dispatch([
            ...$credentials,
            'whatsapp_registration_queued' => true,
        ])->afterResponse();

        Log::info('PUBLIC-CHAT: WhatsApp registro agencia general encolado.', [
            'agency_id' => $agencyId,
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
        $agencyId = (int) ($credentials['agency_id'] ?? 0);
        $name = trim((string) ($credentials['name'] ?? ''));
        $agencyCode = trim((string) ($credentials['code_agency'] ?? ''));

        if ($phone === '' || $agencyId <= 0 || $name === '' || $agencyCode === '') {
            Log::warning('PUBLIC-CHAT: WhatsApp registro agencia general sin datos completos.', [
                'agency_id' => $agencyId,
                'has_phone' => $phone !== '',
                'has_name' => $name !== '',
            ]);

            return false;
        }

        $relativePath = null;

        try {
            ini_set('memory_limit', '2048M');
            $relativePath = $this->ensureWelcomeLetterPdf($agencyCode, $name);
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
            $filename = $this->welcomeLetterFilename($agencyCode);

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

        Log::info('PUBLIC-CHAT: Envío WhatsApp registro agencia general.', [
            'agency_id' => $agencyId,
            'text_sent' => $textSent,
            'document_sent' => $documentSent,
            'welcome_letter_path' => $relativePath,
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
        return sprintf(
            <<<'TEXT'
¡Bienvenido(a) a Integracorp!

Tu registro de Agencia General fue exitoso. Estos son tus datos de acceso:

• Usuario (correo): %s
• Contraseña: %s
• Código de agencia: %s
• Agencia master: %s
• Portal Agencia General: %s

Guarda esta información en un lugar seguro. En el siguiente mensaje recibirás tu carta de bienvenida en PDF.
TEXT,
            (string) ($credentials['email'] ?? 'N/D'),
            (string) ($credentials['password'] ?? 'N/D'),
            (string) ($credentials['code_agency'] ?? 'N/D'),
            (string) ($credentials['owner_code'] ?? 'N/D'),
            (string) ($credentials['login_url'] ?? $this->portalLoginUrl()),
        );
    }

    public function welcomeLetterFilename(string $agencyCode): string
    {
        return $agencyCode.'.pdf';
    }

    public function welcomeLetterRelativePath(string $agencyCode): string
    {
        return 'chat-agency-general-welcome/'.$this->welcomeLetterFilename($agencyCode);
    }

    public function ensureWelcomeLetterPdf(string $agencyCode, string $name): string
    {
        $relativePath = $this->welcomeLetterRelativePath($agencyCode);
        $fullPath = public_path('storage/'.$relativePath);
        $directory = dirname($fullPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (! file_exists($fullPath) || filesize($fullPath) < 100) {
            ini_set('memory_limit', '2048M');

            $pdf = Pdf::loadView('documents.carta-bienvenida-agencia', [
                'code' => $agencyCode,
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

    private function applyTaxIdToAgency(Agency $agency, string $taxId): void
    {
        ChatAgencyRepresentativeDocument::applyRawInputToAgency($agency, $taxId);
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
            'name_corporative' => ['required', 'string', 'min:3', 'max:255'],
            'tax_id' => ['required', 'string', 'min:6', 'max:20'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^[0-9]+$/'],
            'owner_code' => ['required', 'string', 'max:100'],
        ];

        if (Schema::hasTable('agencies') && Schema::hasColumn('agencies', 'email')) {
            $rules['email'][] = Rule::unique('agencies', 'email');
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'email')) {
            $rules['email'][] = Rule::unique('users', 'email');
        }

        if (Schema::hasTable('agents') && Schema::hasColumn('agents', 'email')) {
            $rules['email'][] = Rule::unique('agents', 'email');
        }

        $validator = Validator::make($payload, $rules, [
            'name_corporative.required' => 'La razón social es obligatoria.',
            'tax_id.required' => 'El RIF o número de cédula del representante es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.unique' => 'Este correo electrónico ya está registrado en el sistema.',
            'phone.required' => 'El teléfono es obligatorio.',
            'phone.regex' => 'El teléfono solo debe contener números.',
            'owner_code.required' => 'No se pudo determinar la agencia master asociada al registro.',
        ]);

        $validator->after(function ($validator) use ($payload): void {
            $taxId = trim((string) ($payload['tax_id'] ?? ''));

            if ($taxId !== '' && $this->taxIdExistsInAgencies($taxId)) {
                $validator->errors()->add('tax_id', 'Este RIF o cédula ya está registrado en el sistema.');
            }
        });

        return $validator->validate();
    }

    public function taxIdExistsInAgencies(string $taxId): bool
    {
        return ChatAgencyRepresentativeDocument::existsByRawInput($taxId);
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
