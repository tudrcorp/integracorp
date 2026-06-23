<?php

declare(strict_types=1);

namespace App\Services\PublicAiAgent;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentLabels;
use App\Models\ProspectAgent;
use App\Models\ProspectAgentObservation;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProspectAgentRegistrationService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function create(array $payload): array
    {
        $validated = Validator::make($payload, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'regex:/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i',
                Rule::unique('prospect_agents', 'email'),
            ],
            'phone_1' => ['required', 'string', 'regex:/^[0-9]+$/'],
            'phone_2' => ['nullable', 'string', 'regex:/^[0-9]*$/'],
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'type' => ['required', 'string', Rule::in(array_keys(ProspectAgentLabels::typeOptions()))],
            'status' => ['nullable', 'string', Rule::in(array_keys(ProspectAgentLabels::statusOptions()))],
            'reference_by' => ['nullable', 'string', Rule::in(array_keys(ProspectAgentLabels::referenceOptions()))],
            'classification' => ['nullable', 'string', 'max:100'],
            'instagram' => ['nullable', 'string', 'max:100'],
            'initial_observ' => ['nullable', 'string', 'max:100'],
            'created_by' => ['nullable', 'string', 'max:255'],
            'updated_by' => ['nullable', 'string', 'max:255'],
            'conversation_summary' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        $prospectAgent = ProspectAgent::query()->create([
            'name' => (string) $validated['name'],
            'email' => (string) $validated['email'],
            'phone_1' => (string) $validated['phone_1'],
            'phone_2' => (string) ($validated['phone_2'] ?? ''),
            'country_id' => (string) $validated['country_id'],
            'state_id' => (string) $validated['state_id'],
            'city_id' => (string) $validated['city_id'],
            'type' => (string) $validated['type'],
            'status' => (string) ($validated['status'] ?? 'captación'),
            'reference_by' => (string) ($validated['reference_by'] ?? 'whatsapp-comercial'),
            'classification' => $validated['classification'] ?? null,
            'instagram' => $validated['instagram'] ?? null,
            'initial_observ' => $validated['initial_observ'] ?? null,
            'created_by' => (string) ($validated['created_by'] ?? 'CHAT PUBLICO'),
            'updated_by' => (string) ($validated['updated_by'] ?? 'CHAT PUBLICO'),
        ]);

        $conversationSummary = trim((string) ($validated['conversation_summary'] ?? ''));
        if ($conversationSummary !== '') {
            ProspectAgentObservation::query()->create([
                'prospect_agent_id' => (string) $prospectAgent->id,
                'observation' => mb_substr($conversationSummary, 0, 255),
                'created_by' => (string) ($validated['created_by'] ?? 'CHAT PUBLICO'),
            ]);
        }

        return [
            'prospect_agent_id' => (int) $prospectAgent->id,
            'name' => (string) $prospectAgent->name,
            'email' => (string) $prospectAgent->email,
            'status' => (string) $prospectAgent->status,
            'type' => (string) $prospectAgent->type,
            'reference_by' => (string) $prospectAgent->reference_by,
            'message' => 'Preregistro creado exitosamente.',
        ];
    }
}
