<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

class OtpBoxesInput extends Field
{
    /**
     * @var view-string
     */
    protected string $view = 'filament.forms.components.otp-boxes';

    protected int|Closure $length = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule('numeric');
        $this->rule(fn (): string => 'digits:'.$this->getLength());
    }

    public function length(int|Closure $length): static
    {
        $this->length = $length;

        return $this;
    }

    public function getLength(): int
    {
        return $this->evaluate($this->length);
    }
}
