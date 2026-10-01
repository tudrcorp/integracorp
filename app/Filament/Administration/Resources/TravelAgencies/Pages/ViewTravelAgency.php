<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\TravelAgencies\Pages;

use App\Filament\Administration\Resources\TravelAgencies\TravelAgencyResource;
use App\Models\TravelAgency;
use App\Support\Filament\RecordPageHeader;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;

class ViewTravelAgency extends ViewRecord
{
    protected static string $resource = TravelAgencyResource::class;

    protected static ?string $title = 'Ficha de Agencia de Viajes';

    public function getHeading(): string|Htmlable
    {
        /** @var TravelAgency $agency */
        $agency = $this->getRecord();

        $code = filled($agency->id_agencia) ? ' · '.$agency->id_agencia : '';
        $identification = trim(($agency->typeIdentification ? $agency->typeIdentification.'-' : '').($agency->numberIdentification ?? ''));
        $level = trim((string) ($agency->nivel ?? ''));
        $commission = is_numeric($agency->comision) ? rtrim(rtrim(number_format((float) $agency->comision, 2, ',', '.'), '0'), ',').' %' : null;

        return RecordPageHeader::render(
            eyebrow: 'Agencia de viajes'.$code,
            title: (string) ($agency->name ?: 'Sin nombre'),
            status: RecordPageHeader::statusFor($agency->status),
            chips: [
                filled($agency->classification) ? RecordPageHeader::tag((string) $agency->classification) : null,
                $level !== '' ? RecordPageHeader::tag(str_starts_with(strtolower($level), 'nivel') ? ucfirst($level) : 'Nivel '.$level, RecordPageHeader::TONE_VIOLET) : null,
            ],
            facts: [
                'Identificación' => $identification,
                'Representante' => $agency->representante,
                'Correo' => $agency->email,
                'Teléfono' => $agency->phone,
                'Comisión' => $commission,
                'Crédito aprobado' => RecordPageHeader::money($agency->montoCreditoAprobado),
            ],
            imageUrl: filled($agency->logo) && Storage::disk('public')->exists((string) $agency->logo) ? $agency->logoUrl() : null,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(TravelAgencyResource::getUrl()),
            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-o-pencil')
                ->color('primary'),
        ];
    }
}
