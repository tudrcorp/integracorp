<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationMedicalAppointments\OperationMedicalAppointmentResource;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\UserFormPermissionOptions;
use App\Support\Operations\MedicalAppointmentManager;

function medicalAppointmentBasePath(string $path): string
{
    return dirname(__DIR__, 2).'/'.ltrim($path, '/');
}

it('declara appointment_at en el modelo OperationServiceOrder', function (): void {
    $src = file_get_contents(medicalAppointmentBasePath('app/Models/OperationServiceOrder.php'));

    expect($src)
        ->toContain("'appointment_at'")
        ->toContain("'appointment_at' => 'datetime'")
        ->toContain('medicalAppointment(): HasOne');
});

it('existe migración appointment_at en operation_service_orders', function (): void {
    $files = glob(medicalAppointmentBasePath('database/migrations/*add_appointment_at_to_operation_service_orders_table.php'));

    expect($files)->not->toBeEmpty();

    $src = file_get_contents($files[0]);

    expect($src)
        ->toContain("timestamp('appointment_at')")
        ->toContain('Schema::hasColumn');
});

it('existe migración y modelo de operation_medical_appointments', function (): void {
    $files = glob(medicalAppointmentBasePath('database/migrations/*create_operation_medical_appointments_table.php'));

    expect($files)->not->toBeEmpty();

    $src = file_get_contents($files[0]);

    expect($src)
        ->toContain('operation_service_order_id')
        ->toContain('telemedicine_patient_id')
        ->toContain('telemedicine_case_id')
        ->toContain('supplier_notify_email')
        ->toContain('supplier_notify_phone')
        ->toContain('appointment_at')
        ->toContain('previous_appointment_at')
        ->toContain('last_change_reason');

    $model = file_get_contents(medicalAppointmentBasePath('app/Models/OperationMedicalAppointment.php'));

    expect($model)
        ->toContain('STATUS_SCHEDULED')
        ->toContain('STATUS_RESCHEDULED')
        ->toContain("'appointment_at' => 'datetime'");
});

it('reconoce tipos presenciales que requieren cita', function (): void {
    expect(MedicalAppointmentManager::serviceTypeRequiresAppointment('LABORATORIOS'))->toBeTrue()
        ->and(MedicalAppointmentManager::serviceTypeRequiresAppointment('IMAGENOLOGIA'))->toBeTrue()
        ->and(MedicalAppointmentManager::serviceTypeRequiresAppointment('ESPECIALISTA'))->toBeTrue()
        ->and(MedicalAppointmentManager::serviceTypeRequiresAppointment('MEDICAMENTOS'))->toBeFalse()
        ->and(MedicalAppointmentManager::serviceTypeRequiresAppointment(null))->toBeFalse();
});

it('el manager escribe bitácora y exige motivo en reprogramación', function (): void {
    $src = file_get_contents(medicalAppointmentBasePath('app/Support/Operations/MedicalAppointmentManager.php'));

    expect($src)
        ->toContain('ASSIGN_PREFIX')
        ->toContain('RESCHEDULE_PREFIX')
        ->toContain('ObservationCase::query()->create')
        ->toContain('mb_strlen($reason) < 10')
        ->toContain('AppointmentRescheduleNotifier::dispatchForAppointment')
        ->toContain('$order->forceFill([')
        ->toContain("'appointment_at' => \$newAt");
});

it('el formulario de gestión incluye DateTimePicker de cita solo presencial', function (): void {
    $src = file_get_contents(medicalAppointmentBasePath(
        'app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/ManageCoordinationServiceItemsForm.php'
    ));

    expect($src)
        ->toContain("DateTimePicker::make('appointment_at')")
        ->toContain('serviceTypeRequiresAppointment')
        ->toContain("TextInput::make('supplier_notify_email')")
        ->toContain("TextInput::make('supplier_notify_phone')")
        ->toContain("TextInput::make('supplier_notify_address')")
        ->toContain('OperationServiceOrderProviderContacts::hasCatalogSelection');
});

it('persiste appointment_at al crear OS y sincroniza cita', function (): void {
    $payload = file_get_contents(medicalAppointmentBasePath(
        'app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php'
    ));
    $controller = file_get_contents(medicalAppointmentBasePath('app/Http/Controllers/OperationServiceOrderController.php'));
    $items = file_get_contents(medicalAppointmentBasePath('app/Support/Operations/CoordinationServiceItemsManager.php'));
    $quotes = file_get_contents(medicalAppointmentBasePath('app/Support/Operations/CoordinationServiceQuoteManager.php'));

    expect($payload)->toContain("'appointment_at' => \$data['appointment_at'] ?? null")
        ->and($controller)->toContain("'appointment_at' => \$data['appointment_at'] ?? null")
        ->and($items)->toContain('MedicalAppointmentManager::createFromServiceOrder')
        ->and($quotes)->toContain('MedicalAppointmentManager::createFromServiceOrder');
});

it('el notificador de reprogramación envía email y WhatsApp al proveedor', function (): void {
    $src = file_get_contents(medicalAppointmentBasePath('app/Support/Operations/AppointmentRescheduleNotifier.php'));

    expect($src)
        ->toContain('SendAppointmentRescheduleEmail::dispatch')
        ->toContain('SendNotificacionWhatsApp::dispatch')
        ->toContain('normalizePhoneForWhatsApp')
        ->toContain('CAMBIO DE FECHA DE CITA');

    $job = file_get_contents(medicalAppointmentBasePath('app/Jobs/SendAppointmentRescheduleEmail.php'));

    expect($job)
        ->toContain('implements ShouldQueue')
        ->toContain('AppointmentRescheduleMail')
        ->toContain('Mail::to($email)');

    expect(file_exists(medicalAppointmentBasePath('resources/views/mails/appointment-reschedule.blade.php')))->toBeTrue();
});

it('el recurso Citas Médicas cablea listado, reprogramar y acciones de OS', function (): void {
    $resource = file_get_contents(medicalAppointmentBasePath(
        'app/Filament/Operations/Resources/OperationMedicalAppointments/OperationMedicalAppointmentResource.php'
    ));
    $table = file_get_contents(medicalAppointmentBasePath(
        'app/Filament/Operations/Resources/OperationMedicalAppointments/Tables/OperationMedicalAppointmentsTable.php'
    ));

    expect($resource)
        ->toContain("'COORDINACIÓN DE SERVICIOS'")
        ->toContain('Citas Médicas')
        ->toContain('canCreate(): bool');

    expect($table)
        ->toContain("Action::make('reschedule')")
        ->toContain('MedicalAppointmentManager::reschedule')
        ->toContain("TextColumn::make('patient_ci')")
        ->toContain("->label('Cédula')")
        ->toContain("Action::make('preview_order_pdf')")
        ->toContain("Action::make('download_order_pdf')")
        ->toContain("Action::make('email_order_pdf')")
        ->toContain("Action::make('whatsapp_order')")
        ->toContain('OperationServiceOrderPdfMail')
        ->toContain('SendNotificacionWhatsApp::dispatch')
        ->toContain("FilamentIosButton::extraClassForFilamentColor('warning')")
        ->toContain("FilamentIosButton::extraClassForFilamentColor('info')");
});

it('el PDF de OS muestra la fecha de cita', function (): void {
    $src = file_get_contents(medicalAppointmentBasePath('resources/views/documents/operation-service-order-pdf.blade.php'));

    expect($src)
        ->toContain('Fecha y hora de cita')
        ->toContain('appointment_at');
});

it('registra el permiso citas-medicas para asignarlo a usuarios', function (): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(OperationMedicalAppointmentResource::class))
        ->toBe(['citas-medicas'])
        ->and(DepartmentNavigationPermissionRegistry::moduleFor(OperationMedicalAppointmentResource::class))
        ->toBe('OPERACIONES');

    $aliases = UserFormPermissionOptions::navToLegacySlugAliases();

    expect($aliases['operationmedicalappointmentresource'] ?? null)
        ->toBe(['citas-medicas']);
});
