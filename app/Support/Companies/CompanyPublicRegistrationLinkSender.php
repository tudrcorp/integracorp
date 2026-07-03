<?php

declare(strict_types=1);

namespace App\Support\Companies;

use App\Mail\CompanyPublicRegistrationLinkEmail;
use App\Models\Company;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class CompanyPublicRegistrationLinkSender
{
    public static function sendEmail(Company $company, string $email): bool
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            SecurityAudit::log('AUDIT_BUSINESS_COMPANY_PUBLIC_LINK_EMAIL_FAILED', 'business.companies.send-public-link', [
                'company_id' => $company->getKey(),
                'recipient_email' => $email,
                'reason' => 'invalid_email',
            ]);

            return false;
        }

        $link = CompanyAssociateRegistrar::publicRegistrationUrl($company);
        $companyName = (string) ($company->name ?? 'Empresa');

        try {
            Mail::to($email)->send(new CompanyPublicRegistrationLinkEmail([
                'link' => $link,
                'company_name' => $companyName,
                'sent_at' => now(),
            ]));

            SecurityAudit::log('AUDIT_BUSINESS_COMPANY_PUBLIC_LINK_EMAIL_SENT', 'business.companies.send-public-link', [
                'company_id' => $company->getKey(),
                'recipient_email' => $email,
                'sent_by_user_id' => Auth::id(),
            ]);

            return true;
        } catch (Throwable $exception) {
            SecurityAudit::log('AUDIT_BUSINESS_COMPANY_PUBLIC_LINK_EMAIL_FAILED', 'business.companies.send-public-link', [
                'company_id' => $company->getKey(),
                'recipient_email' => $email,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public static function sendWhatsApp(Company $company, string $phone): bool
    {
        $toPhone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($phone)
            ?? preg_replace('/\D+/', '', $phone);

        if ($toPhone === null || $toPhone === '') {
            SecurityAudit::log('AUDIT_BUSINESS_COMPANY_PUBLIC_LINK_WHATSAPP_FAILED', 'business.companies.send-public-link', [
                'company_id' => $company->getKey(),
                'recipient_phone' => $phone,
                'reason' => 'invalid_phone',
            ]);

            return false;
        }

        $link = CompanyAssociateRegistrar::publicRegistrationUrl($company);
        $companyName = (string) ($company->name ?? 'Empresa');

        $body = <<<TEXT
        ¡Hola! 👋

        Le compartimos el enlace público de registro de asociados para *{$companyName}* en Integracorp Nuevos Negocios.

        Los responsables podrán validar su cédula y registrar a sus usuarios desde este formulario:

        👉 {$link}

        Si necesita ayuda, contáctenos.

        Equipo Integracorp-TDC
        TEXT;

        $params = [
            'token' => config('parameters.TOKEN'),
            'image' => config('parameters.PUBLIC_URL').'/images-whatsapp/integracorp.png',
            'to' => $toPhone,
            'caption' => $body,
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => config('parameters.CURLOPT_URL_IMAGE'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => [
                'content-type: application/x-www-form-urlencoded',
            ],
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($error !== '') {
            Log::error('NEGOCIOS-EMPRESA: Error de conexión cURL en WhatsApp API (enlace público)', [
                'error' => $error,
                'phone' => $toPhone,
                'company_id' => $company->getKey(),
            ]);

            SecurityAudit::log('AUDIT_BUSINESS_COMPANY_PUBLIC_LINK_WHATSAPP_FAILED', 'business.companies.send-public-link', [
                'company_id' => $company->getKey(),
                'recipient_phone' => $phone,
                'error' => $error,
            ]);

            return false;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            Log::warning('NEGOCIOS-EMPRESA: WhatsApp API respondió con error (enlace público)', [
                'status_code' => $httpCode,
                'response' => $response,
                'phone' => $toPhone,
                'company_id' => $company->getKey(),
            ]);

            SecurityAudit::log('AUDIT_BUSINESS_COMPANY_PUBLIC_LINK_WHATSAPP_FAILED', 'business.companies.send-public-link', [
                'company_id' => $company->getKey(),
                'recipient_phone' => $phone,
                'http_code' => $httpCode,
            ]);

            return false;
        }

        SecurityAudit::log('AUDIT_BUSINESS_COMPANY_PUBLIC_LINK_WHATSAPP_SENT', 'business.companies.send-public-link', [
            'company_id' => $company->getKey(),
            'recipient_phone' => $phone,
            'sent_by_user_id' => Auth::id(),
        ]);

        return true;
    }
}
