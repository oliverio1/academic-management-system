@extends('layouts.app')

@section('title', 'Materias del ciclo')

@section('content')
<div class="content px-3">
    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Materias del ciclo</h4>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('coordination.cycle-subjects.index') }}">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="school_cycle_id">Ciclo escolar</label>
                                <select name="school_cycle_id" id="school_cycle_id" class="form-control" required>
                                    <option value="">Seleccione ciclo</option>
                                    @foreach($cycles as $cycle)
                                        <option value="{{ $cycle->id }}"
                                                data-modalities='@json($cycle->modalities->map(fn($modality) => ["id" => $modality->id, "name" => $modality->name])->values())'
                                                {{ optional($selectedCycle)->id === $cycle->id ? 'selected' : '' }}>
                                            {{ $cycle->name }} ({{ $cycle->code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="modality_id">Modalidad</label>
                                <select name="modality_id" id="modality_id" class="form-control" required>
                                    <option value="">Seleccione modalidad</option>
                                    @foreach(($selectedCycle?->modalities ?? collect()) as $modality)
                                        <option value="{{ $modality->id }}" {{ (int) $selectedModalityId === (int) $modality->id ? 'selected' : '' }}>
                                            {{ $modality->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-block">Consultar</button>
                            </div>
                        </div>
                    </form>

                    @if($selectedCycle)
                        @php
                            $totalSubjects = $subjects->count();
                            $subjectsWithTemario = $subjects->filter(fn($row) => $row['temarios_count'] > 0)->count();
                            $subjectsWithoutTemario = $totalSubjects - $subjectsWithTemario;
                        @endphp
                        <div class="row mt-2">
                            <div class="col-md-4 mb-2">
                                <div class="small-box bg-info mb-0">
                                    <div class="inner">
                                        <h3>{{ $totalSubjects }}</h3>
                                        <p>Materias en el ciclo</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="small-box bg-success mb-0">
                                    <div class="inner">
                                        <h3>{{ $subjectsWithTemario }}</h3>
                                        <p>Con temario</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="small-box bg-warning mb-0">
                                    <div class="inner">
                                        <h3>{{ $subjectsWithoutTemario }}</h3>
                                        <p>Sin temario</p>
                                    </div>
                                </div>
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
                    <div class="card-header d-flex align-items-center">
                        <h5 class="mb-0">Materias configuradas</h5>
                        <a href="{{ route('coordination.cycle-planning.index', ['school_cycle_id' => $selectedCycle->id, 'modality_id' => $selectedModalityId]) }}"
                           class="btn btn-sm btn-outline-primary ml-auto">
                            Editar grupos y materias
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Materia</th>
                                        <th>Nivel</th>
                                        <th>Clave</th>
                                        <th>Tipo</th>
                                        <th>Grupos</th>
                                        <th>Temario</th>
                                        <th>Puntos</th>
                                        <th width="220">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($subjects as $row)
                                        @php
                                            $subject = $row['subject'];
                                            $hasTemario = $row['temarios_count'] > 0;
                                        @endphp
                                        <tr>
                                            <td>
                                                <strong>{{ $subject->name }}</strong>
                                                @if($row['latest_temario'])
                                                    <div class="small text-muted">{{ $row['latest_temario']->title }}</div>
                                                @endif
                                            </td>
                                            <td>{{ optional($row['level'])->name ?? '-' }}</td>
                                            <td>{{ $subject->subject_key ?: '-' }}</td>
                                            <td>{{ $subject->type ?: '-' }}</td>
                                            <td>
                                                <span class="badge badge-light">{{ $row['groups']->count() }} grupo(s)</span>
                                                <div class="small text-muted">{{ $row['groups']->implode(', ') }}</div>
                                            </td>
                                            <td>
                                                @if($hasTemario)
                                                    <span class="badge badge-success">Con temario</span>
                                                @else
                                                    <span class="badge badge-warning">Sin temario</span>
                                                @endif
                                            </td>
                                            <td>{{ $row['points_count'] }}</td>
                                            <td>
                                                <a href="{{ route('temarios.index', $subject) }}" class="btn btn-sm btn-outline-primary mb-1">
                                                    Ver temarios
                                                </a>
                                                @unless($hasTemario)
                                                    <a href="{{ route('temarios.import.form', $subject) }}" class="btn btn-sm btn-outline-secondary mb-1">
                                                        Importar
                                                    </a>
                                                @endunless
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                No hay materias configuradas para el ciclo y modalidad seleccionados.
                                            </td>
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
@endsection

@section('page_scripts')
<script>
    (function () {
        const cycleSelect = document.getElementById('school_cycle_id');
        const modalitySelect = document.getElementById('modality_id');
        const selectedModality = "{{ (string) $selectedModalityId }}";

        if (!cycleSelect || !modalitySelect) {
            return;
        }

        function renderModalities(keepSelection) {
            const option = cycleSelect.options[cycleSelect.selectedIndex];
            const modalities = JSON.parse(option?.getAttribute('data-modalities') || '[]');
            const current = keepSelection ? (modalitySelect.value || selectedModality) : '';

            modalitySelect.innerHTML = '<option value="">Seleccione modalidad</option>';
            modalities.forEach(function (modality) {
                const item = document.createElement('option');
                item.value = modality.id;
                item.textContent = modality.name;
                if (String(modality.id) === String(current)) {
                    item.selected = true;
                }
                modalitySelect.appendChild(item);
            });

            if (!modalitySelect.value && modalities.length === 1) {
                modalitySelect.value = modalities[0].id;
            }
        }

        cycleSelect.addEventListener('change', function () {
            renderModalities(false);
        });

        renderModalities(true);
    })();
</script>
@endsection
