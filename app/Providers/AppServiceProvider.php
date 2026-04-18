<?php

namespace App\Providers;

use App\Filament\Business\Resources\Agents\Widgets\ControlActividadInteraccion;
use App\Models\CorporateQuote;
use App\Models\CorporateQuoteRequest;
use App\Models\DressTylorQuote;
use App\Models\IndividualQuote;
use App\Models\TravelAgency;
use App\Models\TravelAgent;
use App\Support\SecurityAudit;
use App\Support\UserSessionAuditTracker;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registro explícito para evitar fallos de auto-descubrimiento en Livewire (widgets fuera de rutas discoverWidgets).
        Livewire::component('app.filament.business.resources.agents.widgets.control-actividad-interaccion', ControlActividadInteraccion::class);
        Event::listen(Login::class, [UserSessionAuditTracker::class, 'onLogin']);
        Event::listen(Logout::class, [UserSessionAuditTracker::class, 'onLogout']);
        $this->registerTravelResourcesSecurityAudits();
        $this->registerQuoteResourcesSecurityAudits();

        FilamentTimezone::set('America/Caracas');

        FilamentColor::register([
            'azulOscuro' => Color::hex('#052F60'),
            'azulClaro' => Color::hex('#305B93'),
            'azul' => Color::hex('#5488AE'),
            'verdeOpaco' => Color::hex('#4A8982'),
            'verde' => Color::hex('#529471'),
            'gris' => Color::hex('#E8EBEA'),

            'no-urgente' => Color::hex('#005ca9'),
            'estandar' => Color::hex('#02976d'),
            'urgencia' => Color::hex('#eab527'),
            'emergencia' => Color::hex('#f17f29'),
            'critico' => Color::hex('#e4003b'),

            'planIncial' => Color::hex('#9ce1ff'),
            'planIdeal' => Color::hex('#25b4e7'),
            'planEspecial' => Color::hex('#2d89ca'),
            'planCorp' => Color::hex('#3b82f6'),

            'verdeApple' => Color::hex('#34c759'),
        ]);
    }

    private function registerTravelResourcesSecurityAudits(): void
    {
        TravelAgency::created(function (TravelAgency $agency): void {
            if ($this->shouldSkipModelAudit()) {
                return;
            }

            SecurityAudit::log(
                $this->travelAction('TRAVEL_AGENCY_CREATED'),
                'travel-agencies.model.created',
                [
                    'panel' => $this->resolvePanelFromPath(),
                    'travel_agency_id' => $agency->id,
                    'name' => $agency->name,
                    'email' => $agency->email,
                    'status' => $agency->status,
                ]
            );
        });

        TravelAgency::updated(function (TravelAgency $agency): void {
            if ($this->shouldSkipModelAudit()) {
                return;
            }

            SecurityAudit::log(
                $this->travelAction('TRAVEL_AGENCY_UPDATED'),
                'travel-agencies.model.updated',
                [
                    'panel' => $this->resolvePanelFromPath(),
                    'travel_agency_id' => $agency->id,
                    'name' => $agency->name,
                    'email' => $agency->email,
                    'changed_fields' => $this->resolveChangedFields($agency),
                ]
            );
        });

        TravelAgency::deleted(function (TravelAgency $agency): void {
            if ($this->shouldSkipModelAudit()) {
                return;
            }

            SecurityAudit::log(
                $this->travelAction('TRAVEL_AGENCY_DELETED'),
                'travel-agencies.model.deleted',
                [
                    'panel' => $this->resolvePanelFromPath(),
                    'travel_agency_id' => $agency->id,
                    'name' => $agency->name,
                    'email' => $agency->email,
                ]
            );
        });

        TravelAgent::created(function (TravelAgent $agent): void {
            if ($this->shouldSkipModelAudit()) {
                return;
            }

            SecurityAudit::log(
                $this->travelAction('TRAVEL_AGENT_CREATED'),
                'travel-agents.model.created',
                [
                    'panel' => $this->resolvePanelFromPath(),
                    'travel_agent_id' => $agent->id,
                    'name' => $agent->name,
                    'email' => $agent->email,
                    'travel_agency_id' => $agent->travel_agency_id,
                ]
            );
        });

        TravelAgent::updated(function (TravelAgent $agent): void {
            if ($this->shouldSkipModelAudit()) {
                return;
            }

            SecurityAudit::log(
                $this->travelAction('TRAVEL_AGENT_UPDATED'),
                'travel-agents.model.updated',
                [
                    'panel' => $this->resolvePanelFromPath(),
                    'travel_agent_id' => $agent->id,
                    'name' => $agent->name,
                    'email' => $agent->email,
                    'travel_agency_id' => $agent->travel_agency_id,
                    'changed_fields' => $this->resolveChangedFields($agent),
                ]
            );
        });

        TravelAgent::deleted(function (TravelAgent $agent): void {
            if ($this->shouldSkipModelAudit()) {
                return;
            }

            SecurityAudit::log(
                $this->travelAction('TRAVEL_AGENT_DELETED'),
                'travel-agents.model.deleted',
                [
                    'panel' => $this->resolvePanelFromPath(),
                    'travel_agent_id' => $agent->id,
                    'name' => $agent->name,
                    'email' => $agent->email,
                    'travel_agency_id' => $agent->travel_agency_id,
                ]
            );
        });
    }

    private function registerQuoteResourcesSecurityAudits(): void
    {
        CorporateQuoteRequest::created(function (CorporateQuoteRequest $record): void {
            if ($this->shouldSkipModelAudit()) {
                return;
            }

            SecurityAudit::log(
                $this->quoteAction('COTIZADOR_CREATED'),
                'cotizador.model.created',
                [
                    'panel' => $this->resolvePanelFromPath(),
                    'cotizador_id' => $record->id,
                    'email' => $record->email,
                    'status' => $record->status,
                ]
            );
        });

        CorporateQuoteRequest::updated(function (CorporateQuoteRequest $record): void {
            if ($this->shouldSkipModelAudit()) {
                return;
            }

            SecurityAudit::log(
                $this->quoteAction('COTIZADOR_UPDATED'),
                'cotizador.model.updated',
                [
                    'panel' => $this->resolvePanelFromPath(),
                    'cotizador_id' => $record->id,
                    'email' => $record->email,
                    'status' => $record->status,
                    'changed_fields' => $this->resolveChangedFields($record),
                ]
            );
        });

        CorporateQuoteRequest::deleted(function (CorporateQuoteRequest $record): void {
            if ($this->shouldSkipModelAudit()) {
                return;
            }

            SecurityAudit::log(
                $this->quoteAction('COTIZADOR_DELETED'),
                'cotizador.model.deleted',
                [
                    'panel' => $this->resolvePanelFromPath(),
                    'cotizador_id' => $record->id,
                    'email' => $record->email,
                ]
            );
        });

        IndividualQuote::created(function (IndividualQuote $record): void {
            if ($this->shouldSkipModelAudit()) {
                return;
            }

            SecurityAudit::log(
                $this->quoteAction('INDIVIDUAL_QUOTE_CREATED'),
                'individual-quotes.model.created',
                [
                    'panel' => $this->resolvePanelFromPath(),
                    'individual_quote_id' => $record->id,
                    'status' => $record->status,
                    'agent_id' => $record->agent_id,
                    'owner_code' => $record->owner_code,
                    'code_agency' => $record->code_agency,
                ]
            );
        });

        IndividualQuote::updated(function (IndividualQuote $record): void {
            if ($this->shouldSkipModelAudit()) {
                return;
            }

            SecurityAudit::log(
                $this->quoteAction('INDIVIDUAL_QUOTE_UPDATED'),
                'individual-quotes.model.updated',
                [
                    'panel' => $this->resolvePanelFromPath(),
                    'individual_quote_id' => $record->id,
                    'status' => $record->status,
                    'agent_id' => $record->agent_id,
                    'owner_code' => $record->owner_code,
                    'code_agency' => $record->code_agency,
                    'changed_fields' => $this->resolveChangedFields($record),
                ]
            );
        });

        IndividualQuote::deleted(function (IndividualQuote $record): void {
            if ($this->shouldSkipModelAudit()) {
                return;
            }

            SecurityAudit::log(
                $this->quoteAction('INDIVIDUAL_QUOTE_DELETED'),
                'individual-quotes.model.deleted',
                [
                    'panel' => $this->resolvePanelFromPath(),
                    'individual_quote_id' => $record->id,
                    'status' => $record->status,
                    'agent_id' => $record->agent_id,
                ]
            );
        });

        CorporateQuote::created(function (CorporateQuote $record): void {
            if ($this->shouldSkipModelAudit()) {
                return;
            }

            SecurityAudit::log(
                $this->quoteAction('CORPORATE_QUOTE_CREATED'),
                'corporate-quotes.model.created',
                [
                    'panel' => $this->resolvePanelFromPath(),
                    'corporate_quote_id' => $record->id,
                    'status' => $record->status,
                    'agent_id' => $record->agent_id,
                    'owner_code' => $record->owner_code,
                    'code_agency' => $record->code_agency,
                ]
            );
        });

        CorporateQuote::updated(function (CorporateQuote $record): void {
            if ($this->shouldSkipModelAudit()) {
                return;
            }

            SecurityAudit::log(
                $this->quoteAction('CORPORATE_QUOTE_UPDATED'),
                'corporate-quotes.model.updated',
                [
                    'panel' => $this->resolvePanelFromPath(),
                    'corporate_quote_id' => $record->id,
                    'status' => $record->status,
                    'agent_id' => $record->agent_id,
                    'owner_code' => $record->owner_code,
                    'code_agency' => $record->code_agency,
                    'changed_fields' => $this->resolveChangedFields($record),
                ]
            );
        });

        CorporateQuote::deleted(function (CorporateQuote $record): void {
            if ($this->shouldSkipModelAudit()) {
                return;
            }

            SecurityAudit::log(
                $this->quoteAction('CORPORATE_QUOTE_DELETED'),
                'corporate-quotes.model.deleted',
                [
                    'panel' => $this->resolvePanelFromPath(),
                    'corporate_quote_id' => $record->id,
                    'status' => $record->status,
                    'agent_id' => $record->agent_id,
                ]
            );
        });

        DressTylorQuote::created(function (DressTylorQuote $record): void {
            if ($this->shouldSkipModelAudit()) {
                return;
            }

            SecurityAudit::log(
                $this->quoteAction('DRESS_TYLOR_QUOTE_CREATED'),
                'dress-tylor-quotes.model.created',
                [
                    'panel' => $this->resolvePanelFromPath(),
                    'dress_tylor_quote_id' => $record->id,
                    'status' => $record->status,
                    'user_id' => $record->user_id,
                ]
            );
        });

        DressTylorQuote::updated(function (DressTylorQuote $record): void {
            if ($this->shouldSkipModelAudit()) {
                return;
            }

            SecurityAudit::log(
                $this->quoteAction('DRESS_TYLOR_QUOTE_UPDATED'),
                'dress-tylor-quotes.model.updated',
                [
                    'panel' => $this->resolvePanelFromPath(),
                    'dress_tylor_quote_id' => $record->id,
                    'status' => $record->status,
                    'user_id' => $record->user_id,
                    'changed_fields' => $this->resolveChangedFields($record),
                ]
            );
        });

        DressTylorQuote::deleted(function (DressTylorQuote $record): void {
            if ($this->shouldSkipModelAudit()) {
                return;
            }

            SecurityAudit::log(
                $this->quoteAction('DRESS_TYLOR_QUOTE_DELETED'),
                'dress-tylor-quotes.model.deleted',
                [
                    'panel' => $this->resolvePanelFromPath(),
                    'dress_tylor_quote_id' => $record->id,
                    'status' => $record->status,
                    'user_id' => $record->user_id,
                ]
            );
        });
    }

    private function travelAction(string $suffix): string
    {
        return $this->panelAwareAuditAction($suffix, ['BUSINESS', 'MARKETING']);
    }

    private function quoteAction(string $suffix): string
    {
        return $this->panelAwareAuditAction($suffix, [
            'BUSINESS',
            'MARKETING',
            'AGENTS',
            'GENERAL',
            'MASTER',
            'ADMINISTRATION',
            'ADMIN',
            'OPERATIONS',
            'TELEMEDICINA',
        ]);
    }

    /**
     * @param  list<string>  $allowedPanels
     */
    private function panelAwareAuditAction(string $suffix, array $allowedPanels): string
    {
        $panel = strtoupper($this->resolvePanelFromPath());

        if (! in_array($panel, $allowedPanels, true)) {
            $panel = 'SYSTEM';
        }

        return 'AUDIT_'.$panel.'_'.$suffix;
    }

    private function resolvePanelFromPath(): string
    {
        $path = request()->path();
        if (! is_string($path) || $path === '') {
            return 'unknown';
        }

        return strtolower((string) explode('/', trim($path, '/'))[0]);
    }

    private function shouldSkipModelAudit(): bool
    {
        if (app()->runningInConsole()) {
            return true;
        }

        return ! app()->bound('request');
    }

    /**
     * @return array<string, array{old:mixed,new:mixed}>
     */
    private function resolveChangedFields(Model $model): array
    {
        $changes = [];

        foreach ($model->getChanges() as $field => $newValue) {
            if ($field === 'updated_at') {
                continue;
            }

            $changes[(string) $field] = [
                'old' => $model->getOriginal((string) $field),
                'new' => $newValue,
            ];
        }

        return $changes;
    }
}
