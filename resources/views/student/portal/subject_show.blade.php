@extends('layouts.app')

@section('title', 'Detalle de materia')

@php
    $statusLabel = function (?string $status) {
        return match ($status) {
            'present' => ['Asistencia', 'success'],
            'late' => ['Retardo', 'warning'],
            'absent' => ['Falta', 'danger'],
            'justified' => ['Justificado', 'info'],
            default => ['Sin registro', 'secondary'],
        };
    };
@endphp

@section('content')
<div class="content px-3">
    <div class="clearfix"></div>
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-start">
                    <div>
                        <h3 class="mb-0">{{ $assignment->subject->name }}</h3>
                        <small class="text-muted">
                            Profesor: {{ $assignment->teacher->user->name }} |
                            Grupo: {{ $assignment->group->name }}
                            @if($period)
                                | Periodo: {{ $period->name }}
                            @endif
                        </small>
                    </div>
                    <a href="{{ route('student.subjects') }}" class="btn btn-outline-secondary btn-sm">Volver a mis materias</a>
                </div>
                <div class="card-body">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="mb-2">Porcentaje de asistencia</h5>
                <div class="progress" style="height: 22px;">
                    <div class="progress-bar" role="progressbar" style="width: {{ $attendancePercentage }}%;" aria-valuenow="{{ $attendancePercentage }}" aria-valuemin="0" aria-valuemax="100">
                        {{ number_format((float) $attendancePercentage, 0) }}%
                    </div>
                </div>
                <div class="mt-3">
                    <button
                        type="button"
                        class="btn btn-outline-primary btn-sm"
                        data-toggle="collapse"
                        data-target="#attendanceDetails"
                        aria-expanded="false"
                        aria-controls="attendanceDetails">
                        Ver detalle de sesiones
                    </button>
                </div>
            </div>
        </div>

        <div class="collapse mb-3" id="attendanceDetails">
            <div class="card">
                <div class="card-header">
                    <strong>Detalle de sesiones y asistencia</strong>
                </div>
                <div class="card-body table-responsive p-3">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Horario</th>
                                <th>Asistencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sessions as $session)
                                @php
                                    $attendance = $session->attendances->first();
                                    [$label, $badge] = $statusLabel(optional($attendance)->status);
                                @endphp
                                <tr>
                                    <td>{{ optional($session->session_date)->format('d/m/Y') }}</td>
                                    <td>{{ substr((string) $session->start_time, 0, 5) }} - {{ substr((string) $session->end_time, 0, 5) }}</td>
                                    <td><span class="badge badge-{{ $badge }}">{{ $label }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">No hay sesiones registradas para este periodo.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <strong>Calificacion final por rubros</strong>
            </div>
            <div class="card-body">
                <h4 class="mb-3">
                    Calificacion final:
                    @if($finalGrade !== null)
                        <span class="badge badge-primary">{{ number_format((float) $finalGrade, 1) }}</span>
                    @else
                        <span class="badge badge-secondary">Sin calcular</span>
                    @endif
                </h4>
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Parcial</th>
                            <th>Rubro</th>
                            <th>Porcentaje</th>
                            <th>Calificacion</th>
                            <th>Aporta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($gradeBreakdownRows as $row)
                            <tr>
                                <td>
                                    @if(!empty($row['partial']))
                                        <span class="badge badge-info">{{ $row['partial'] }}</span>
                                    @else
                                        <span class="text-muted">Sin parcial</span>
                                    @endif
                                </td>
                                <td>{{ $row['criterion'] }}</td>
                                <td>{{ number_format((float) $row['percentage'], 2) }}%</td>
                                <td>{{ $row['average'] !== null ? number_format((float) $row['average'], 1) : '-' }}</td>
                                <td>{{ $row['contribution'] !== null ? number_format((float) $row['contribution'], 1) : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No hay rubros o calificaciones para este periodo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-3">
                    <button
                        type="button"
                        class="btn btn-outline-primary btn-sm"
                        data-toggle="collapse"
                        data-target="#gradesDetails"
                        aria-expanded="false"
                        aria-controls="gradesDetails">
                        Ver detalle de calificaciones
                    </button>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <strong>Entregables de la materia</strong>
            </div>
            <div class="card-body table-responsive p-3">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Título</th>
                            <th>Entrega</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($practices ?? collect()) as $practice)
                            @php
                                $studentSubmission = $practice->submissions
                                    ->filter(function ($submission) use ($student) {
                                        return $submission->team
                                            && $submission->team->students->contains('id', $student->id);
                                    })
                                    ->sortBy(function ($submission) {
                                        return match ($submission->status) {
                                            'reviewed' => 0,
                                            'submitted' => 1,
                                            'draft' => 2,
                                            default => 3,
                                        };
                                    })
                                    ->first();
                                $isPastDue = $practice->due_date
                                    ? $practice->due_date->copy()->endOfDay()->isPast()
                                    : false;
                                $canCapture = ! $isPastDue && $studentSubmission?->status !== 'reviewed';
                            @endphp
                            <tr>
                                <td>{{ $practice->kind_label }}</td>
                                <td>{{ $practice->title }}</td>
                                <td>{{ optional($practice->due_date)->format('d/m/Y') ?? '-' }}</td>
                                <td>
                                    @if($canCapture)
                                        <a href="{{ route('student.practices.show', $practice) }}" class="btn btn-sm btn-outline-primary">
                                            Capturar
                                        </a>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                                            Captura cerrada
                                        </button>
                                    @endif
                                    <a href="{{ route('student.practices.report', $practice) }}" class="btn btn-sm btn-outline-secondary">
                                        Ver reporte
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No hay entregables registrados para esta materia.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <strong>Documentos de la materia</strong>
            </div>
            <div class="card-body table-responsive p-3">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Última actualización</th>
                            <th>Archivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($visibleDocs ?? collect()) as $doc)
                            <tr>
                                <td>{{ $docTypeLabels[optional($doc->item)->document_type] ?? optional($doc->item)->document_type ?? 'Documento' }}</td>
                                <td>{{ optional($doc->submitted_at)->format('d/m/Y H:i') }}</td>
                                <td>
                                    <a href="{{ asset('storage/'.$doc->file_path) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                        Ver PDF
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">No hay documentos visibles para alumnos en esta materia.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="collapse" id="gradesDetails">
            <div class="card">
                <div class="card-header">
                    <strong>Detalle de actividades y calificaciones</strong>
                </div>
                <div class="card-body table-responsive p-3">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Actividad</th>
                                <th>Fecha limite</th>
                                <th>Valor maximo</th>
                                <th>Calificacion</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($activities as $activity)
                                @php $grade = $grades->get($activity->id); @endphp
                                <tr>
                                    <td>{{ $activity->title }}</td>
                                    <td>{{ optional($activity->due_date)->format('d/m/Y') ?? '-' }}</td>
                                    <td>{{ $activity->max_score }}</td>
                                    <td>
                                        @if($grade)
                                            <strong>{{ number_format((float) $grade->score, 1) }}</strong>
                                        @else
                                            <span class="text-muted">Sin calificar</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No hay actividades registradas para este periodo.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

