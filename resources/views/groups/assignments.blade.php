@extends('layouts.app')

@section('title', 'Asignaciones')

@section('content')
    @if(session('info'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <strong>{{ session('info') }}</strong>
        </div>
    @endif

    <div class="app-content-header">
        <div class="container-fluid"></div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12 connectedSortable mt-3">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="mb-0">
                                Asignación de profesores para el grupo {{ $group->name }}
                            </h3>
                            <hr>
                            <p class="text-muted mb-0">
                                Asigna profesor y NRC por materia/sección para el ciclo activo.
                            </p>
                            @if(!empty($cycleGroup?->schoolCycle))
                                <small class="text-muted d-block mt-2">
                                    Ciclo: {{ $cycleGroup->schoolCycle->name }} ({{ $cycleGroup->schoolCycle->code }})
                                </small>
                            @endif
                            <small class="text-muted d-block mt-1">
                                Las secciones se configuran por materia.
                            </small>
                        </div>

                        <form method="POST" action="{{ route('groups.assignments.update', $group) }}">
                            @csrf
                            <div class="card-body p-3">
                                <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th style="width: 22%">Materia</th>
                                            <th style="width: 8%">Sección</th>
                                            <th style="width: 30%">Profesor asignado</th>
                                            <th style="width: 18%">NRC</th>
                                            <th style="width: 10%">Alumnos</th>
                                            <th style="width: 12%"># Secciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($subjects as $subject)
                                            @php
                                                $sectionsForSubject = (int) ($subjectSectionCounts[$subject->id] ?? 1);
                                            @endphp
                                            @for($section = 1; $section <= 3; $section++)
                                                @php
                                                    $assignmentForSection = $assignments->get($subject->id.'-'.$section);
                                                    $hiddenBySections = $section > $sectionsForSubject;
                                                @endphp
                                                <tr class="subject-row-{{ $subject->id }} {{ $hiddenBySections ? 'd-none' : '' }} {{ $assignmentForSection?->teacher_id ? '' : 'table-warning' }}">
                                                    <td>
                                                        <strong>{{ $subject->name }}</strong>
                                                    </td>
                                                    <td>Sección {{ $section }}</td>
                                                    <td>
                                                        <select name="assignments[{{ $subject->id }}][{{ $section }}]" class="form-control form-control-sm">
                                                            <option value="">-- Sin asignar --</option>
                                                            @foreach($teachers as $teacher)
                                                                @if($teacher->subjects->contains($subject))
                                                                    <option value="{{ $teacher->id }}"
                                                                        {{ (int) ($assignmentForSection?->teacher_id ?? 0) === (int) $teacher->id ? 'selected' : '' }}>
                                                                        {{ $teacher->user->name }}
                                                                    </option>
                                                                @endif
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input
                                                            type="text"
                                                            class="form-control form-control-sm"
                                                            name="nrcs[{{ $subject->id }}][{{ $section }}]"
                                                            maxlength="30"
                                                            placeholder="Ej. 12345"
                                                            value="{{ old('nrcs.'.$subject->id.'.'.$section, $assignmentForSection?->nrc) }}"
                                                        >
                                                    </td>
                                                    <td>
                                                        @if($assignmentForSection)
                                                            <a href="{{ route('groups.assignments.sections.edit', [$group, $subject]) }}"
                                                               class="btn btn-outline-primary btn-sm">
                                                                Gestionar
                                                            </a>
                                                        @else
                                                            <span class="text-muted small">Primero asigna docente</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($section === 1)
                                                            <select name="subject_sections[{{ $subject->id }}]"
                                                                    class="form-control form-control-sm js-subject-sections"
                                                                    data-subject="{{ $subject->id }}">
                                                                <option value="1" {{ $sectionsForSubject === 1 ? 'selected' : '' }}>1</option>
                                                                <option value="2" {{ $sectionsForSubject === 2 ? 'selected' : '' }}>2</option>
                                                                <option value="3" {{ $sectionsForSubject === 3 ? 'selected' : '' }}>3</option>
                                                            </select>
                                                        @else
                                                            <span class="text-muted small">—</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endfor
                                        @endforeach
                                    </tbody>
                                </table>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button class="btn btn-primary">Guardar</button>
                                <a href="{{ route('groups.index') }}" class="btn btn-secondary">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('page_scripts')
<script>
    (function () {
        const selectors = document.querySelectorAll('.js-subject-sections');
        if (!selectors.length) return;

        const refreshRows = (subjectId, count) => {
            const rows = document.querySelectorAll('.subject-row-' + subjectId);
            rows.forEach((row, index) => {
                if ((index + 1) <= count) {
                    row.classList.remove('d-none');
                } else {
                    row.classList.add('d-none');
                }
            });
        };

        selectors.forEach((select) => {
            const subjectId = select.dataset.subject;
            refreshRows(subjectId, parseInt(select.value || '1', 10));
            select.addEventListener('change', () => {
                refreshRows(subjectId, parseInt(select.value || '1', 10));
            });
        });
    })();
</script>
@endsection
