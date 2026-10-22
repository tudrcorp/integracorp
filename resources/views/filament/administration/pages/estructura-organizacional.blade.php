<x-filament-panels::page>
    @if ($this->demo)
        @include('filament.shared.org-structure.partials.chart', ['config' => $this->demoConfig()])
    @elseif ($this->draftId !== null)
        @include('filament.shared.org-structure.partials.chart', ['config' => $this->editorConfig()])
    @else
        @php($list = $this->listData())

        <div style="display: grid; gap: 1.5rem;">
            <x-filament::section icon="heroicon-o-rectangle-group">
                <x-slot name="heading">Organigrama vigente</x-slot>

                @if ($list['current'] !== null)
                    <div style="display: flex; flex-wrap: wrap; gap: 1rem 2rem; align-items: center;">
                        <div style="min-width: 0;">
                            <div style="font-weight: 600; font-size: 1.05rem;">{{ $list['current']->name }}</div>
                            <div style="font-size: .85rem; opacity: .7;">
                                Vigente desde {{ $list['current']->published_at?->format('d/m/Y H:i') ?? '—' }}
                            </div>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: .5rem;">
                            <x-filament::badge color="gray">{{ $list['currentStats']['positions'] }} puestos</x-filament::badge>
                            <x-filament::badge color="gray">{{ $list['currentStats']['people'] }} personas</x-filament::badge>
                            @if ($list['currentStats']['vacancies'] > 0)
                                <x-filament::badge color="warning">{{ $list['currentStats']['vacancies'] }} vacantes</x-filament::badge>
                            @endif
                            @if ($list['currentStats']['unassigned'] > 0)
                                <x-filament::badge color="warning">{{ $list['currentStats']['unassigned'] }} sin puesto</x-filament::badge>
                            @endif
                        </div>
                    </div>

                    @if ($list['events']->isNotEmpty())
                        <div style="margin-top: 1rem; display: grid; gap: .35rem; font-size: .85rem;">
                            <div style="font-weight: 600; opacity: .8;">Últimos movimientos</div>
                            @foreach ($list['events'] as $event)
                                <div style="display: flex; gap: .75rem; flex-wrap: wrap;">
                                    <span style="opacity: .6; font-variant-numeric: tabular-nums;">{{ $event->created_at?->format('d/m/Y H:i') }}</span>
                                    <span>{{ $event->summary }}</span>
                                    <span style="opacity: .6;">· {{ $event->user_name }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @else
                    <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: space-between;">
                        <p style="margin: 0;">
                            Todavía no hay un organigrama publicado.
                            @if ($list['canEdit'])
                                Empieza en blanco: el lienzo se abre con un solo cuadro para el puesto principal.
                            @else
                                Quien tenga el permiso de edición puede crear la primera versión.
                            @endif
                        </p>
                        @if ($list['canEdit'])
                            {{ $this->newOrgChartAction }}
                        @endif
                    </div>
                @endif
            </x-filament::section>

            <x-filament::section icon="heroicon-o-pencil-square">
                <x-slot name="heading">Borradores en curso</x-slot>
                <x-slot name="description">Un borrador no cambia nada hasta que se publica. Puede haber hasta 10 abiertos.</x-slot>

                @forelse ($list['open'] as $draft)
                    <div wire:key="draft-{{ $draft->id }}" style="display: flex; flex-wrap: wrap; gap: .75rem 1.5rem; align-items: center; padding: .75rem 0; border-top: 1px solid rgba(148, 163, 184, .25);">
                        <div style="flex: 1 1 280px; min-width: 0;">
                            <div style="display: flex; gap: .5rem; align-items: center; flex-wrap: wrap;">
                                <span style="font-weight: 600;">{{ $draft->name }}</span>
                                <x-filament::badge :color="$draft->status->color()">{{ $draft->status->label() }}</x-filament::badge>
                            </div>
                            <div style="font-size: .82rem; opacity: .7;">
                                {{ $draft->basedOn ? 'Sobre «'.$draft->basedOn->name.'»' : 'Carga inicial desde RRHH' }}
                                · creado por {{ $draft->creator?->name ?? '—' }}
                                · actualizado {{ $draft->updated_at?->diffForHumans() }}
                                @if ($draft->status === \App\Enums\OrgStructureVersionStatus::InReview && $draft->submitter)
                                    · enviado por {{ $draft->submitter->name }}
                                @endif
                            </div>
                            @if ($draft->status === \App\Enums\OrgStructureVersionStatus::Returned && $draft->review_notes)
                                <div style="font-size: .82rem; color: rgb(var(--danger-600, 220 38 38));">Devuelto: {{ $draft->review_notes }}</div>
                            @endif
                        </div>
                        <div style="display: flex; gap: .5rem; flex-wrap: wrap;">
                            <x-filament::button tag="a" :href="static::getUrl(['borrador' => $draft->id])" wire:navigate size="sm" icon="heroicon-o-arrow-right">
                                {{ $draft->status === \App\Enums\OrgStructureVersionStatus::InReview && $list['canReview'] ? 'Revisar' : 'Abrir' }}
                            </x-filament::button>
                            {{ ($this->discardDraftAction)(['draft' => $draft->id]) }}
                        </div>
                    </div>
                @empty
                    <p style="margin: 0; opacity: .75;">
                        No hay borradores abiertos.
                        @if ($list['canEdit'])
                            Pulsa «Nuevo organigrama» para empezar en blanco o «Partir del vigente» para reorganizar el actual.
                        @endif
                    </p>
                @endforelse
            </x-filament::section>

            @if ($list['closed']->isNotEmpty())
                <x-filament::section icon="heroicon-o-clock" collapsible collapsed>
                    <x-slot name="heading">Historial</x-slot>
                    <x-slot name="description">Versiones archivadas y borradores descartados. Las archivadas se consultan desde el organigrama.</x-slot>

                    @foreach ($list['closed'] as $version)
                        <div style="display: flex; flex-wrap: wrap; gap: .5rem 1rem; align-items: center; padding: .5rem 0; border-top: 1px solid rgba(148, 163, 184, .25); font-size: .9rem;">
                            <span style="font-weight: 500;">{{ $version->name }}</span>
                            <x-filament::badge :color="$version->status->color()">{{ $version->status->label() }}</x-filament::badge>
                            <span style="opacity: .65;">
                                {{ $version->published_at ? 'publicada '.$version->published_at->format('d/m/Y') : 'creada por '.($version->creator?->name ?? '—') }}
                                @if ($version->archived_at)
                                    · archivada {{ $version->archived_at->format('d/m/Y') }}
                                @endif
                            </span>
                        </div>
                    @endforeach
                </x-filament::section>
            @endif
        </div>
    @endif
</x-filament-panels::page>
