@extends('layouts.app')

@section('title', 'Alumnos - Detección de seguimiento')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex flex-wrap justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1">Detección de casos</h4>
                            <small class="text-muted">Prioridades calculadas con el ciclo y campus activo.</small>
                        </div>
                        <span class="badge badge-info px-3 py-2 mt-2 mt-md-0">
                            Ciclo activo: {{ $activeCycle->name ?? 'Sin ciclo activo' }}
                        </span>
                    </div>
                </div>

                <div class="card-body">
                    @php
                        $highCount = $students->where('priority', 'high')->count();
                        $mediumCount = $students->where('priority', 'medium')->count();
                        $lowCount = $students->where('priority', 'low')->count();
                        $noneCount = $students->where('priority', 'none')->count();
                    @endphp

                    <div class="row mb-3">
                        <div class="col-md-3 col-6 mb-2 mb-md-0">
                            <div class="small-box bg-light border mb-0">
                                <div class="inner"><h3>{{ $highCount }}</h3><p>Prioridad alta</p></div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-2 mb-md-0">
                            <div class="small-box bg-light border mb-0">
                                <div class="inner"><h3>{{ $mediumCount }}</h3><p>Prioridad media</p></div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="small-box bg-light border mb-0">
                                <div class="inner"><h3>{{ $lowCount }}</h3><p>Prioridad baja</p></div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="small-box bg-light border mb-0">
                                <div class="inner"><h3>{{ $noneCount }}</h3><p>Sin riesgo</p></div>
                            </div>
                        </div>
                    </div>

                    <table class="table table-hover table-bordered" id="students-followup">
                        <thead>
                            <tr>
                                <th>Alumno</th>
                                <th>Grupo</th>
                                <th>Indicadores</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($students as $student)
                            @php
                                $hasActiveFollowUp = $student->has_active_follow_up ?? false;
                                $flags = $student->followup_flags ?? [];
                                $rowClass = match($student->priority) {
                                    'high' => 'tr-priority-high',
                                    'medium' => 'tr-priority-medium',
                                    'low' => 'tr-priority-low',
                                    default => '',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('coordination.students.academic-summary', [
                                        'school_cycle_id' => $activeCycleId,
                                        'group_id' => $student->group_id,
                                        'student_id' => $student->id,
                                    ]) }}" class="text-dark">
                                        {{ $student->user->name ?? 'Sin usuario' }}
                                    </a>
                                    <small class="text-muted d-block">{{ $student->enrollment_number }}</small>
                                </td>
                                <td>{{ $student->group->name ?? '—' }}</td>
                                <td>
                                    @switch($student->priority)
                                        @case('high') <span class="badge badge-pill badge-soft mr-1">Alta</span> @break
                                        @case('medium') <span class="badge badge-pill badge-soft mr-1">Media</span> @break
                                        @case('low') <span class="badge badge-pill badge-soft mr-1">Baja</span> @break
                                        @default <span class="badge badge-pill badge-soft mr-1">Sin riesgo</span>
                                    @endswitch

                                    @foreach($flags as $flag)
                                        @switch($flag)
                                            @case('academic_risk') <span class="badge badge-pill badge-soft mr-1">Académico</span> @break
                                            @case('low_attendance') <span class="badge badge-pill badge-soft mr-1">Asistencia</span> @break
                                            @case('behavioral') <span class="badge badge-pill badge-soft mr-1">Conductual</span> @break
                                            @case('group_change') <span class="badge badge-pill badge-soft mr-1">Cambio</span> @break
                                        @endswitch
                                    @endforeach

                                    @if(empty($flags))
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center flex-wrap">
                                        <a href="{{ route('coordination.students.academic-summary', [
                                            'school_cycle_id' => $activeCycleId,
                                            'group_id' => $student->group_id,
                                            'student_id' => $student->id,
                                        ]) }}" class="btn btn-sm btn-outline-info mr-2">
                                            Revisión académica
                                        </a>
                                        @if(! $hasActiveFollowUp && ! empty($flags))
                                            <a href="{{ route('coordination.follow-ups.create', ['student' => $student->id]) }}" class="btn btn-sm btn-outline-info">
                                                Solicitar seguimiento
                                            </a>
                                        @elseif($hasActiveFollowUp)
                                            <a href="{{ route('coordination.students.show', $student->id) }}" class="btn btn-sm btn-outline-info">
                                                Ver expediente
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">No hay alumnos para mostrar.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_css')
<style>
    #students-followup td, #students-followup th { vertical-align: middle; }
    .badge-soft {
        background: #f1f3f5;
        color: #495057;
        border: 1px solid #dee2e6;
        font-weight: 500;
    }
    #students-followup thead th {
        background: #f8f9fa;
        color: #495057;
        border-bottom: 1px solid #dee2e6;
    }
</style>
@endsection

@section('page_scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    $('#students-followup').DataTable({
        order: [],
        language: { url: '/datatables.json' }
    });
});
</script>
@endsection
