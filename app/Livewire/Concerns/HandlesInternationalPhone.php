<?php

namespace App\Livewire\Concerns;

use App\Support\PhoneCountryCodeOptions;

trait HandlesInternationalPhone
{
    public string $country_code = '+58';

    public string $phone = '';

    /**
     * @return array<string, string>
     */
    public function countryCodeOptions(): array
    {
        return PhoneCountryCodeOptions::common();
    }

    public function isVenezuelaSelected(): bool
    {
        return PhoneCountryCodeOptions::isVenezuela($this->country_code);
    }

    public function phonePlaceholder(): string
    {
        return $this->isVenezuelaSelected()
            ? '412 701 8390'
            : __('Número local sin código de país');
    }

    public function updatedCountryCode(): void
    {
        $this->updatedPhone($this->phone);
    }

    public function updatedPhone(?string $value): void
    {
        $digits = preg_replace('/\D/', '', $value ?? '');

        if ($digits === '') {
            $this->phone = '';

            return;
        }

        if ($this->isVenezuelaSelected()) {
            if (! str_starts_with($digits, '0') && in_array($digits[0] ?? '', ['2', '4'], true)) {
                $digits = '0'.$digits;
            }

            $this->phone = $this->formatLocalPhoneDisplay(substr($digits, 0, 11));

            return;
        }

        $this->phone = substr($digits, 0, 14);
    }

    public function phoneDigits(): string
    {
        return preg_replace('/\D/', '', $this->phone) ?? '';
    }

    public function phoneForStorage(): string
    {
        return $this->country_code.ltrim($this->phoneDigits(), '0');
    }

    public function isPhoneValid(): bool
    {
        $digits = $this->phoneDigits();

        if ($digits === '') {
            return false;
        }

        if ($this->isVenezuelaSelected()) {
            return (bool) preg_match('/^0(2|4)\d{9}$/', $digits);
        }

        $localDigits = ltrim($digits, '0');

        return strlen($localDigits) >= 6 && strlen($localDigits) <= 14;
    }

    /**
     * @return array<int, mixed>
     */
    protected function internationalPhoneValidationRules(): array
    {
        return [
            'country_code' => ['required', 'in:'.implode(',', array_keys(PhoneCountryCodeOptions::common()))],
            'phone' => [
                'required',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->isPhoneValid()) {
                        return;
                    }

                    if ($this->isVenezuelaSelected()) {
                        $fail('Ingrese un número venezolano válido: móvil 04XX o fijo 02XX (11 dígitos, con cero inicial).');

                        return;
                    }

                    $fail('Ingrese un número local válido (entre 6 y 14 dígitos, sin el código de país).');
                },
            ],
        ];
    }

    private function formatLocalPhoneDisplay(string $digits): string
    {
        if (strlen($digits) <= 4) {
            return $digits;
        }

        if (strlen($digits) <= 7) {
            return substr($digits, 0, 4).' '.substr($digits, 4);
        }

        return substr($digits, 0, 4).' '.substr($digits, 4, 3).' '.substr($digits, 7);
    }
}
