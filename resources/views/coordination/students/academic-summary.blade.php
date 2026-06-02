@extends('layouts.app')

@section('title', 'Alumnos')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Alumnos</h4>
                    <small class="text-muted">Seleccione grupo y alumno para consultar asistencia y calificaciones por materia.</small>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('coordination.students.academic-summary') }}" class="mb-3">
                        <div class="form-row">
                            <div class="col-md-4 mb-2">
                                <label>Ciclo</label>
                                <select name="school_cycle_id" id="school_cycle_id" class="form-control">
                                    <option value="">Seleccione ciclo</option>
                                    @foreach($schoolCycles as $cycle)
                                        <option value="{{ $cycle->id }}" {{ (int) $selectedSchoolCycleId === (int) $cycle->id ? 'selected' : '' }}>
                                            {{ $cycle->name }} ({{ $cycle->code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label>Grupo</label>
                                <select name="group_id" id="group_id" class="form-control">
                                    <option value="">Todos</option>
                                    @foreach($groups as $group)
                                        <option value="{{ $group->id }}" {{ (int) $selectedGroupId === (int) $group->id ? 'selected' : '' }}>
                                            {{ $group->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label>Alumno</label>
                                <select name="student_id" id="student_id" class="form-control">
                                    <option value="">Seleccione alumno</option>
                                    @foreach($students as $student)
                                        <option value="{{ $student->id }}" {{ (int) $selectedStudentId === (int) $student->id ? 'selected' : '' }}>
                                            {{ $student->user->name }} ({{ $student->enrollment_number }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-1 mb-2 d-flex align-items-end">
                                <button class="btn btn-primary btn-block">Consultar</button>
                            </div>
                        </div>
                    </form>

                    @if($selectedStudent)
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="mb-0">{{ $selectedStudent->user->name }}</h5>
                                @if(!empty($activeCycle))
                                    <small class="text-muted">Ciclo activo: {{ $activeCycle->name }} ({{ $activeCycle->code }})</small>
                                @elseif($activePeriod)
                                    <small class="text-muted">Periodo activo: {{ $activePeriod->name }}</small>
                                @endif
                            </div>
                            <a href="{{ route('coordination.students.report-card.pdf', $selectedStudent) }}"
                               class="btn btn-sm btn-danger">
                                <i class="fas fa-file-pdf"></i> Descargar boleta
                            </a>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th rowspan="2">Materia</th>
                                        @foreach(($partialColumns ?? collect()) as $partial)
                                            <th colspan="2" class="text-center">
                                                {{ $partial['name'] }}
                                                @if(!empty($partial['status_label']))
                                                    <small class="d-block text-muted">{{ $partial['status_label'] }}</small>
                                                @endif
                                            </th>
                                        @endforeach
                                        <th rowspan="2" class="text-center">Asistencia final</th>
                                        <th rowspan="2" class="text-center">Calificación final</th>
                                        <th rowspan="2" class="text-center">Detalle</th>
                                    </tr>
                                    <tr>
                                        @foreach(($partialColumns ?? collect()) as $partial)
                                            <th class="text-center">Asistencia</th>
                                            <th class="text-center">Calificación</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="table-info">
                                        <td><strong>Global</strong></td>
                                        @foreach(($partialColumns ?? collect()) as $partial)
                                            @php
                                                $partialGlobal = $globalByPartial[$partial['id']] ?? ['attendance' => null, 'grade' => null];
                                            @endphp
                                            <td class="text-center">
                                                <strong>{{ $partialGlobal['attendance'] !== null ? number_format((float) $partialGlobal['attendance'], 0).'%' : '-' }}</strong>
                                            </td>
                                            <td class="text-center">
                                                <strong>{{ $partialGlobal['grade'] !== null ? number_format((float) $partialGlobal['grade'], 1) : '-' }}</strong>
                                            </td>
                                        @endforeach
                                        <td class="text-center">
                                            <strong>{{ $globalAttendance !== null ? number_format((float) $globalAttendance, 1).'%' : '-' }}</strong>
                                        </td>
                                        <td class="text-center">
                                            <strong>{{ $globalAverage !== null ? number_format((float) $globalAverage, 1) : '-' }}</strong>
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('coordination.students.prefect-attendance-detail', [
                                                'student' => $selectedStudent->id,
                                                'school_cycle_id' => $selectedSchoolCycleId,
                                                'group_id' => $selectedGroupId,
                                            ]) }}"
                                               class="btn btn-sm btn-outline-primary">
                                                Ver prefectura
                                            </a>
                                        </td>
                                    </tr>
                                    @forelse($subjectRows as $row)
                                        @php
                                            $isFailedSubject = collect($partialColumns ?? collect())->contains(function ($partial) use ($row) {
                                                $partialId = $partial['id'] ?? null;
                                                if (! $partialId) {
                                                    return false;
                                                }

                                                $grade = $row['partials'][$partialId]['grade'] ?? null;
                                                return $grade !== null && (float) $grade < 6.0;
                                            });
                                        @endphp
                                        <tr class="{{ $isFailedSubject ? 'table-warning' : '' }}">
                                            <td>{{ $row['subject_name'] }}</td>
                                            @foreach(($partialColumns ?? collect()) as $partial)
                                                @php
                                                    $partialRow = $row['partials'][$partial['id']] ?? ['attendance' => null, 'grade' => null];
                                                    $isFailedPartial = $partialRow['grade'] !== null && (float) $partialRow['grade'] < 6.0;
                                                @endphp
                                                <td class="text-center">
                                                    {{ $partialRow['attendance'] !== null ? number_format((float) $partialRow['attendance'], 0).'%' : '-' }}
                                                </td>
                                                <td class="text-center {{ $isFailedPartial ? 'font-weight-bold text-warning' : '' }}">
                                                    {{ $partialRow['grade'] !== null ? number_format((float) $partialRow['grade'], 1) : '-' }}
                                                </td>
                                            @endforeach
                                            <td class="text-center">
                                                {{ isset($row['attendance']) ? number_format((float) $row['attendance'], 1).'%' : '-' }}
                                            </td>
                                            @php
                                                $isFailedFinal = isset($row['final_grade']) && $row['final_grade'] !== null && (float) $row['final_grade'] < 6.0;
                                            @endphp
                                            <td class="text-center {{ $isFailedFinal ? 'font-weight-bold text-danger' : '' }}">
                                                {{ $row['final_grade'] !== null ? number_format((float) $row['final_grade'], 1) : '-' }}
                                            </td>
                                            <td class="text-center">
                                                @if(!empty($row['assignment']) && isset($row['assignment']->id))
                                                    <a href="{{ route('coordination.students.subject-detail', [$selectedStudent, $row['assignment']]) }}"
                                                       class="btn btn-sm btn-outline-primary">
                                                        Ver detalle
                                                    </a>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ 4 + (($partialColumns ?? collect())->count() * 2) }}" class="text-center text-muted">No hay materias asignadas para este alumno.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @else
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_scripts')
<script>
    (function () {
        const cycleSelect = document.getElementById('school_cycle_id');
        const groupSelect = document.getElementById('group_id');
        const form = cycleSelect ? cycleSelect.closest('form') : null;

        if (!form) return;

        if (cycleSelect) {
            cycleSelect.addEventListener('change', function () {
                const studentSelect = document.getElementById('student_id');
                if (studentSelect) {
                    studentSelect.value = '';
                }
                form.submit();
            });
        }

        if (groupSelect) {
            groupSelect.addEventListener('change', function () {
                const studentSelect = document.getElementById('student_id');
                if (studentSelect) {
                    studentSelect.value = '';
                }
                form.submit();
            });
        }
    })();
</script>
@endsection
