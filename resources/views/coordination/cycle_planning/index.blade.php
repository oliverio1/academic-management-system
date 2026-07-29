@extends('layouts.app')

@section('title', 'Planeacion por ciclo')

@section('content')
<div class="content px-3">
    @if(session('info'))
        <div class="alert alert-success mt-3">{{ session('info') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger mt-3">{{ session('error') }}</div>
    @endif

    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Planeacion academica por ciclo</h4>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('coordination.cycle-planning.index') }}">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="school_cycle_id">Ciclo escolar</label>
                                <select name="school_cycle_id" id="school_cycle_id" class="form-control" required>
                                    <option value="">Seleccione ciclo</option>
                                    @foreach($cycles as $cycle)
                                        <option value="{{ $cycle->id }}"
                                                data-modalities='@json($cycle->modalities->map(fn($modality) => ["id" => $modality->id, "name" => $modality->name])->values())'
                                                {{ (int) optional($selectedCycle)->id === (int) $cycle->id ? 'selected' : '' }}>
                                            {{ $cycle->name }} ({{ $cycle->code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-8 mb-3">
                                <label for="modality_id">Modalidad</label>
                                <select name="modality_id" id="modality_id" class="form-control" required>
                                    <option value="">Seleccione modalidad</option>
                                    @foreach(($selectedCycle?->modalities ?? collect()) as $modality)
                                        <option value="{{ $modality->id }}" {{ (string) request('modality_id', $selectedModalityId ?? '') === (string) $modality->id ? 'selected' : '' }}>
                                            {{ $modality->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 mb-3 d-flex align-items-end">
                                <button class="btn btn-primary w-100" type="submit">Cargar planeacion</button>
                            </div>
                        </div>
                    </form>

                    @if($selectedCycle)
                        <hr>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <form method="POST" action="{{ route('coordination.cycle-planning.groups.store') }}">
                                    @csrf
                                    <input type="hidden" name="school_cycle_id" value="{{ $selectedCycle->id }}">
                                    <input type="hidden" name="modality_id" value="{{ $selectedModalityId }}">
                                    <label for="group_id">Agregar grupo al ciclo</label>
                                    <div class="form-row">
                                        <div class="col-md-8 mb-2">
                                            <select name="group_id" id="group_id" class="form-control" required>
                                            <option value="">Seleccione grupo</option>
                                            @foreach($availableGroups as $group)
                                                @php($alreadyInCycle = collect($plannedGroupIds ?? [])->contains((int) $group->id))
                                                <option value="{{ $group->id }}" data-subjects='@json($group->subjects->pluck("name")->values())'>
                                                    {{ $group->name }}{{ $alreadyInCycle ? ' (ya agregado al ciclo)' : '' }}
                                                </option>
                                            @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <button class="btn btn-success btn-block" type="submit">Agregar</button>
                                        </div>
                                    </div>
                                    <small id="group-subjects-preview" class="text-muted d-block mt-2"></small>
                                </form>
                            </div>
                            <div class="col-md-6 mb-3">
                                <form method="POST" action="{{ route('coordination.cycle-planning.groups.new') }}">
                                    @csrf
                                    <input type="hidden" name="school_cycle_id" value="{{ $selectedCycle->id }}">
                                    <input type="hidden" name="modality_id" value="{{ $selectedModalityId }}">
                                    <label>Crear grupo nuevo en este ciclo</label>
                                    <div class="form-row">
                                        <div class="col-md-4 mb-2">
                                            <select name="level_id" class="form-control" required>
                                                <option value="">Nivel</option>
                                                @foreach($levels as $level)
                                                    <option value="{{ $level->id }}">{{ $level->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <input type="text" name="group_name" class="form-control" placeholder="Nombre del grupo" required>
                                        </div>
                                        <div class="col-md-2 mb-2">
                                            <input type="number" name="capacity" class="form-control" placeholder="Cap." min="1">
                                        </div>
                                        <div class="col-md-2 mb-2">
                                            <button class="btn btn-success btn-block" type="submit">Crear</button>
                                        </div>
                                    </div>
                                    <small class="text-muted">Se agrega al ciclo y se asignan materias activas del nivel automaticamente.</small>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($selectedCycle)
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Grupos y materias del ciclo</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Grupo</th>
                                        <th>Nivel</th>
                                        <th>Editar grupo</th>
                                        <th>Materias del ciclo</th>
                                        <th width="320">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($plannedGroups as $cycleGroup)
                                        @php($subjectsForLevel = $subjectsByLevel->get($cycleGroup->group->level_id, collect()))
                                        <tr>
                                            <td>{{ $cycleGroup->group->name }}</td>
                                            <td>{{ $cycleGroup->group->level->name }}</td>
                                            <td>
                                                <form method="POST" action="{{ route('coordination.cycle-planning.groups.update', $cycleGroup) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="form-row">
                                                        <div class="col-md-5 mb-1">
                                                            <input type="text"
                                                                   name="group_name"
                                                                   class="form-control form-control-sm"
                                                                   value="{{ $cycleGroup->group->name }}"
                                                                   required>
                                                        </div>
                                                        <div class="col-md-2 mb-1">
                                                            <input type="number"
                                                                   name="capacity"
                                                                   class="form-control form-control-sm"
                                                                   min="1"
                                                                   placeholder="Cap."
                                                                   value="{{ $cycleGroup->group->capacity }}">
                                                        </div>
                                                        <div class="col-md-5 mb-1">
                                                            <button type="submit" class="btn btn-sm btn-outline-primary btn-block">Ok</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </td>
                                            <td>
                                                <form method="POST" action="{{ route('coordination.cycle-planning.groups.subjects.update', $cycleGroup) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <select class="form-control" name="subjects[]" multiple size="6">
                                                        @foreach($subjectsForLevel as $subject)
                                                            <option value="{{ $subject->id }}" {{ $cycleGroup->subjects->contains('id', $subject->id) ? 'selected' : '' }}>
                                                                {{ $subject->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <small class="text-muted">Usa Ctrl/Cmd para seleccionar varias.</small>
                                            </td>
                                            <td>
                                                    <button type="submit" class="btn btn-sm btn-primary mb-2">Guardar materias</button>
                                                </form>
                                                <a href="{{ route('coordination.schedules.index', ['school_cycle_id' => $selectedCycle->id, 'group_id' => $cycleGroup->group_id]) }}"
                                                   class="btn btn-sm btn-outline-info mb-2">
                                                    Ver horario
                                                </a>
                                                <form method="POST" action="{{ route('coordination.cycle-planning.groups.deactivate', $cycleGroup) }}" onsubmit="return confirm('Eliminar este grupo del ciclo?')">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar del ciclo</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">No hay grupos configurados para este ciclo.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@section('page_scripts')
<script>
    (function () {
        const cycleSelect = document.getElementById('school_cycle_id');
        const modalitySelect = document.getElementById('modality_id');
        const groupSelect = document.getElementById('group_id');
        const groupSubjectsPreview = document.getElementById('group-subjects-preview');

        if (!cycleSelect || !modalitySelect) {
            return;
        }

        const previouslySelected = "{{ (string) request('modality_id', $selectedModalityId ?? '') }}";

        function refreshModalities() {
            const selectedCycleOption = cycleSelect.options[cycleSelect.selectedIndex];
            const raw = selectedCycleOption ? selectedCycleOption.getAttribute('data-modalities') : null;
            let modalities = [];

            if (raw) {
                try {
                    modalities = JSON.parse(raw) || [];
                } catch (error) {
                    modalities = [];
                }
            }

            modalitySelect.innerHTML = '<option value="">Seleccione modalidad</option>';
            modalities.forEach((modality) => {
                const option = document.createElement('option');
                option.value = String(modality.id);
                option.textContent = modality.name;
                if (String(modality.id) === previouslySelected) {
                    option.selected = true;
                }
                modalitySelect.appendChild(option);
            });
        }

        cycleSelect.addEventListener('change', function () {
            modalitySelect.dataset.userChanged = '1';
            refreshModalities();
        });

        function refreshGroupSubjectsPreview() {
            if (!groupSelect || !groupSubjectsPreview) {
                return;
            }

            const selectedOption = groupSelect.options[groupSelect.selectedIndex];
            if (!selectedOption || !selectedOption.value) {
                groupSubjectsPreview.textContent = '';
                return;
            }

            const raw = selectedOption.getAttribute('data-subjects');
            let subjects = [];
            if (raw) {
                try {
                    subjects = JSON.parse(raw) || [];
                } catch (error) {
                    subjects = [];
                }
            }

            groupSubjectsPreview.textContent = subjects.length
                ? ('Materias asociadas: ' + subjects.join(', '))
                : 'Este grupo no tiene materias asociadas.';
        }

        if (groupSelect) {
            groupSelect.addEventListener('change', refreshGroupSubjectsPreview);
            refreshGroupSubjectsPreview();
        }

        refreshModalities();
    })();
</script>
@endsection
@endsection
