@extends('layouts.app')

@section('title', 'Alumnos')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Desempeño académico</h4>
                    <small class="text-muted">
                        @if(!empty($activeCycle))
                            Ciclo seleccionado: {{ $activeCycle->name }} ({{ $activeCycle->code }})
                        @else
                            Seleccione un ciclo desde Inicio para consultar el desempeño.
                        @endif
                    </small>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('coordination.students.academic-summary') }}" class="mb-3">
                        <div class="form-row">
                            <div class="col-md-10 mb-2">
                                <label>Grupo</label>
                                <select name="group_id" id="group_id" class="form-control">
                                    <option value="">Seleccione grupo</option>
                                    @foreach($groups as $group)
                                        <option value="{{ $group->id }}" {{ (int) $selectedGroupId === (int) $group->id ? 'selected' : '' }}>
                                            {{ $group->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-2 d-flex align-items-end">
                                <button class="btn btn-primary btn-block">Consultar</button>
                            </div>
                        </div>
                    </form>

                    @if($selectedGroup)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <h5 class="mb-0">Grupo {{ $selectedGroup->name }}</h5>
                                <small class="text-muted">{{ $groupSummaryRows->count() }} alumno(s) en el resumen.</small>
                            </div>
                        </div>

                        <div class="table-responsive mb-4">
                            <table data-datatable="true" class="table table-hover table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th rowspan="2">Alumno</th>
                                        <th rowspan="2">Matrícula</th>
                                        <th rowspan="2" class="text-center">Materias</th>
                                        @foreach(($partialColumns ?? collect()) as $partial)
                                            <th colspan="2" class="text-center">
                                                {{ $partial['name'] }}
                                                @if(!empty($partial['status_label']))
                                                    <small class="d-block text-muted">{{ $partial['status_label'] }}</small>
                                                @endif
                                            </th>
                                        @endforeach
                                        <th rowspan="2" class="text-center">Asistencia</th>
                                        <th rowspan="2" class="text-center">Faltas</th>
                                        <th rowspan="2" class="text-center">Promedio</th>
                                        <th rowspan="2" class="text-center">Actividades</th>
                                    </tr>
                                    <tr>
                                        @foreach(($partialColumns ?? collect()) as $partial)
                                            <th class="text-center">Asist.</th>
                                            <th class="text-center">Prom.</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($groupSummaryRows as $row)
                                        @php
                                            $student = $row['student'];
                                            $lowAttendance = $row['attendance'] !== null && (float) $row['attendance'] < 80;
                                            $lowAverage = $row['average'] !== null && (float) $row['average'] < 6;
                                        @endphp
                                        <tr class="{{ $lowAverage ? 'table-warning' : '' }}">
                                            <td>
                                                <a href="{{ route('coordination.students.academic-summary', ['group_id' => $selectedGroupId, 'student_id' => $student->id]) }}"
                                                   class="font-weight-bold">
                                                    {{ $student->user->name ?? 'Sin nombre' }}
                                                </a>
                                            </td>
                                            <td>{{ $student->enrollment_number }}</td>
                                            <td class="text-center">{{ number_format($row['subjects_count']) }}</td>
                                            @foreach(($partialColumns ?? collect()) as $partial)
                                                @php
                                                    $partialRow = $row['partials'][$partial['id']] ?? ['attendance' => null, 'grade' => null];
                                                    $partialLowAttendance = $partialRow['attendance'] !== null && (float) $partialRow['attendance'] < 80;
                                                    $partialLowGrade = $partialRow['grade'] !== null && (float) $partialRow['grade'] < 6;
                                                @endphp
                                                <td class="text-center {{ $partialLowAttendance ? 'font-weight-bold text-danger' : '' }}">
                                                    {{ $partialRow['attendance'] !== null ? number_format((float) $partialRow['attendance'], 1).'%' : '-' }}
                                                </td>
                                                <td class="text-center {{ $partialLowGrade ? 'font-weight-bold text-danger' : '' }}">
                                                    {{ $partialRow['grade'] !== null ? number_format((float) $partialRow['grade'], 1) : '-' }}
                                                </td>
                                            @endforeach
                                            <td class="text-center {{ $lowAttendance ? 'font-weight-bold text-danger' : '' }}">
                                                {{ $row['attendance'] !== null ? number_format((float) $row['attendance'], 1).'%' : '-' }}
                                            </td>
                                            <td class="text-center">{{ number_format($row['absences']) }}</td>
                                            <td class="text-center {{ $lowAverage ? 'font-weight-bold text-danger' : '' }}">
                                                {{ $row['average'] !== null ? number_format((float) $row['average'], 1) : '-' }}
                                            </td>
                                            <td class="text-center">
                                                {{ number_format($row['graded_activities']) }} / {{ number_format($row['total_activities']) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ 7 + (($partialColumns ?? collect())->count() * 2) }}" class="text-center text-muted py-4">
                                                No hay alumnos para el grupo seleccionado.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-info mb-0">
                            Seleccione un grupo para ver el resumen académico de todos sus alumnos.
                        </div>
                    @endif

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

                        <div class="mt-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="mb-0">Captura docente por materia</h5>
                                <small class="text-muted">Asistencia, actividades y calificaciones registradas por los profesores.</small>
                            </div>
                            <div class="table-responsive">
                                <table data-datatable="true" class="table table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>Materia</th>
                                            <th>Profesor</th>
                                            <th class="text-center">Sesiones</th>
                                            <th class="text-center">Asistencias capturadas</th>
                                            <th class="text-center">Asistencia alumno</th>
                                            <th class="text-center">Actividades</th>
                                            <th class="text-center">Calificadas</th>
                                            <th class="text-center">Promedio</th>
                                            <th class="text-center">Detalle</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($teacherCaptureRows as $row)
                                            <tr>
                                                <td>{{ $row['subject'] }}</td>
                                                <td>{{ $row['teacher'] }}</td>
                                                <td class="text-center">
                                                    {{ number_format($row['closed_sessions']) }} / {{ number_format($row['total_sessions']) }}
                                                </td>
                                                <td class="text-center">
                                                    {{ number_format($row['attendance_rows']) }}
                                                </td>
                                                <td class="text-center">
                                                    {{ $row['attendance_percentage'] !== null ? number_format((float) $row['attendance_percentage'], 1).'%' : '-' }}
                                                </td>
                                                <td class="text-center">{{ number_format($row['total_activities']) }}</td>
                                                <td class="text-center">{{ number_format($row['graded_activities']) }}</td>
                                                <td class="text-center">
                                                    {{ $row['average'] !== null ? number_format((float) $row['average'], 1) : '-' }}
                                                </td>
                                                <td class="text-center">
                                                    <a href="{{ route('coordination.students.subject-detail', [$selectedStudent, $row['assignment']]) }}"
                                                       class="btn btn-sm btn-primary">
                                                        Ver
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="9" class="text-center text-muted py-4">
                                                    No hay registros docentes para este alumno.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-lg-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <strong>Ultimos registros de asistencia por profesor</strong>
                                    </div>
                                    <div class="card-body table-responsive p-0">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Materia</th>
                                                    <th>Profesor</th>
                                                    <th>Estado</th>
                                                    <th>Captura</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($recentTeacherAttendanceRows as $session)
                                                    @php
                                                        $attendance = $session->attendances->first();
                                                        $status = optional($attendance)->status;
                                                        $statusMap = [
                                                            'present' => ['Asistencia', 'success'],
                                                            'late' => ['Retardo', 'warning'],
                                                            'absent' => ['Falta', 'danger'],
                                                            'justified' => ['Justificada', 'info'],
                                                        ];
                                                        [$statusText, $statusBadge] = $statusMap[$status] ?? ['Sin registro', 'secondary'];
                                                    @endphp
                                                    <tr>
                                                        <td>{{ optional($session->session_date)->format('d/m/Y') }}</td>
                                                        <td>{{ $session->teachingAssignment?->subject?->name ?? '-' }}</td>
                                                        <td>{{ $session->teachingAssignment?->teacher?->user?->name ?? '-' }}</td>
                                                        <td><span class="badge badge-{{ $statusBadge }}">{{ $statusText }}</span></td>
                                                        <td>
                                                            @if($session->attendance_closed_at)
                                                                <span class="badge badge-success">Cerrada</span>
                                                            @else
                                                                <span class="badge badge-secondary">Abierta</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted py-4">No hay asistencias registradas.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <strong>Ultimas actividades y calificaciones</strong>
                                    </div>
                                    <div class="card-body table-responsive p-0">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Materia</th>
                                                    <th>Actividad</th>
                                                    <th>Rubro</th>
                                                    <th class="text-center">Calificacion</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($recentActivityRows as $activity)
                                                    @php $grade = $activity->grades->first(); @endphp
                                                    <tr>
                                                        <td>{{ optional($activity->due_date)->format('d/m/Y') ?? '-' }}</td>
                                                        <td>{{ $activity->assignment?->subject?->name ?? '-' }}</td>
                                                        <td>
                                                            <strong>{{ $activity->title }}</strong>
                                                            <div class="small text-muted">{{ $activity->assignment?->teacher?->user?->name ?? '-' }}</div>
                                                        </td>
                                                        <td>{{ $activity->evaluationCriterion?->name ?? '-' }}</td>
                                                        <td class="text-center">
                                                            @if($grade && $grade->score !== null)
                                                                <strong>{{ number_format((float) $grade->score, 1) }}</strong>
                                                            @else
                                                                <span class="text-muted">Sin calificar</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted py-4">No hay actividades registradas.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
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
        const groupSelect = document.getElementById('group_id');
        const form = groupSelect ? groupSelect.closest('form') : null;

        if (!form) return;

        if (groupSelect) {
            groupSelect.addEventListener('change', function () {
                form.submit();
            });
        }
    })();
</script>
@endsection
