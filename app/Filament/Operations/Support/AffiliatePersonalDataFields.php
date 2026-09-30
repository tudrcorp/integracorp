<?php

declare(strict_types=1);

namespace App\Filament\Operations\Support;

use App\Support\Operations\AffiliatePersonalDataUpdater;
use App\Support\Telemedicine\TelemedicinePatientIdentity;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Campos de datos personales compartidos por la edición de afiliados
 * individuales y corporativos en Operaciones: misma validación y mismos textos.
 *
 * Cédula y teléfono se validan ya normalizados porque la base trae formatos
 * mezclados (`0424-8611567`, `V-12.345.678`): un dato viejo con guiones no debe
 * impedir guardar otro cambio.
 */
final class AffiliatePersonalDataFields
{
    public static function document(): TextInput
    {
        return TextInput::make('nro_identificacion')
            ->label('Cédula')
            ->placeholder('Ej.: 22171244')
            ->helperText('Se guarda solo con números. Si el afiliado es paciente de telemedicina, su ficha también se actualiza.')
            ->required()
            ->maxLength(20)
            ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                if (! AffiliatePersonalDataUpdater::documentIsValid($value)) {
                    $fail('La cédula debe tener entre 5 y 12 dígitos (puede escribirla con V-, puntos o guiones).');
                }
            })
            ->validationMessages([
                'required' => 'Indique la cédula del afiliado.',
            ]);
    }

    public static function sex(): Select
    {
        return Select::make('sex')
            ->label('Sexo')
            ->options(array_combine(AffiliatePersonalDataUpdater::SEX_OPTIONS, AffiliatePersonalDataUpdater::SEX_OPTIONS))
            ->in(AffiliatePersonalDataUpdater::SEX_OPTIONS)
            ->native(false)
            ->required()
            ->validationMessages([
                'required' => 'Seleccione el sexo del afiliado.',
                'in' => 'Seleccione MASCULINO o FEMENINO.',
            ]);
    }

    public static function birthDate(): DatePicker
    {
        return DatePicker::make('birth_date')
            ->label('Fecha de nacimiento')
            ->native(false)
            ->displayFormat(AffiliatePersonalDataUpdater::BIRTH_DATE_FORMAT)
            ->format(AffiliatePersonalDataUpdater::BIRTH_DATE_FORMAT)
            ->maxDate(now())
            ->minDate(now()->subYears(120))
            ->closeOnDateSelection()
            ->required()
            ->live()
            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('age', AffiliatePersonalDataUpdater::ageFromBirthDate($state)))
            ->validationMessages([
                'required' => 'Indique la fecha de nacimiento.',
                'before_or_equal' => 'La fecha de nacimiento no puede ser futura.',
            ]);
    }

    public static function age(): TextInput
    {
        return TextInput::make('age')
            ->label('Edad')
            ->suffix('años')
            ->helperText('Se calcula sola a partir de la fecha de nacimiento.')
            ->disabled()
            ->dehydrated(false);
    }

    public static function phone(string $name = 'phone', string $label = 'Teléfono', ?string $helperText = 'Con código de país o de operadora. Se usa para WhatsApp.'): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->tel()
            ->placeholder('Ej.: 584141234567')
            ->helperText($helperText)
            ->maxLength(25)
            ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                if (! AffiliatePersonalDataUpdater::phoneIsValid($value)) {
                    $fail('Escriba entre 10 y 15 dígitos (ej.: 584141234567 o 0414-1234567).');
                }
            });
    }

    public static function email(): TextInput
    {
        return TextInput::make('email')
            ->label('Correo')
            ->email()
            ->placeholder('correo@ejemplo.com')
            ->maxLength(255)
            ->validationMessages([
                'email' => 'Escriba un correo válido.',
            ]);
    }

    /**
     * Estado inicial del formulario: fecha en `d/m/Y`, edad calculada y sexo
     * canónico (la base guarda también `M`/`F`).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillState(array $data): array
    {
        $data['birth_date'] = AffiliatePersonalDataUpdater::normalizeBirthDate($data['birth_date'] ?? null)
            ?? ($data['birth_date'] ?? null);
        $data['age'] = AffiliatePersonalDataUpdater::ageFromBirthDate($data['birth_date']) ?? ($data['age'] ?? null);

        $sex = TelemedicinePatientIdentity::normalizeSex($data['sex'] ?? null);
        $data['sex'] = in_array($sex, AffiliatePersonalDataUpdater::SEX_OPTIONS, true) ? $sex : null;

        return $data;
    }
}
