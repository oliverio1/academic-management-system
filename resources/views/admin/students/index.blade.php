@extends('layouts.app')

@section('title', 'Alumnos – Detección de seguimiento')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4>Detección de casos</h4>
                    <small class="text-muted">
                        Identifique alumnos que requieren apertura de seguimiento.
                    </small>
                </div>

                <div class="card-body">
                    <table class="table" id="students-followup">
                        <thead>
                            <tr>
                                <th>Alumno</th>
                                <th>Grupo</th>
                                <th>Indicadores</th>
                                <th>Seguimiento</th>
                                <th>Acción</th>
                            </tr>
                        </thead>

                        <tbody>
                        @forelse($students as $student)
                            @php
                                // Flags esperadas (pueden venir del controlador)
                                $hasActiveFollowUp = $student->has_active_follow_up ?? false;
                                $flags = $student->followup_flags ?? [];
                                $rowClass = match($student->priority) {
                                    'high'   => 'tr-priority-high',
                                    'medium' => 'tr-priority-medium',
                                    'low'    => 'tr-priority-low',
                                    default  => '',
                                };
                            @endphp

                            <tr class="{{ $rowClass }}">
                                {{-- Alumno --}}
                                <td>
                                    <strong>{{ $student->user->name }}</strong><br>
                                    <small class="text-muted">
                                        {{ $student->enrollment_number }}
                                    </small>
                                </td>

                                {{-- Grupo --}}
                                <td>
                                    {{ $student->group->name ?? '—' }}
                                </td>

                                {{-- Indicadores --}}
                                <td>
                                    {{-- Badge de prioridad --}}
                                    @switch($student->priority)
                                        @case('high')
                                            <span class="badge bg-danger me-1">Alta</span>
                                            @break
                                        @case('medium')
                                            <span class="badge bg-warning text-dark me-1">Media</span>
                                            @break
                                        @case('low')
                                            <span class="badge bg-success me-1">Baja</span>
                                            @break
                                    @endswitch

                                    {{-- Flags --}}
                                    @foreach($student->followup_flags as $flag)
                                        @switch($flag)
                                            @case('academic_risk')
                                                <span class="badge bg-danger me-1">Académico</span>
                                                @break
                                            @case('low_attendance')
                                                <span class="badge bg-primary me-1">Asistencia</span>
                                                @break
                                            @case('behavioral')
                                                <span class="badge bg-warning text-dark me-1">Conductual</span>
                                                @break
                                            @case('group_change')
                                                <span class="badge bg-secondary me-1">Cambio</span>
                                                @break
                                        @endswitch
                                    @endforeach

                                    @if(empty($student->followup_flags))
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>

                                {{-- Seguimiento activo --}}
                                <td>
                                    @if($hasActiveFollowUp)
                                        <span class="badge bg-success">Sí</span>
                                    @else
                                        <span class="badge bg-secondary">No</span>
                                    @endif
                                </td>

                                {{-- Acción --}}
                                <td>
                                    @if(! $hasActiveFollowUp && ! empty($flags))
                                        <a href="{{ route('coordination.follow-ups.create', ['student' => $student->id]) }}"
                                           class="btn btn-sm btn-warning">
                                            Solicitar seguimiento
                                        </a>
                                    @elseif($hasActiveFollowUp)
                                        <a href="{{ route('coordination.students.show', $student->id) }}"
                                           class="btn btn-sm btn-primary">
                                            Ver expediente
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    No hay alumnos para mostrar.
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
@endsection

@section('page_css')
<style>
    .tr-priority-high {
        background-color: #f8d7da !important; /* rojo suave */
    }
    
    .tr-priority-medium {
        background-color: #fff3cd !important; /* amarillo suave */
    }
    
    .tr-priority-low {
        background-color: #d1e7dd !important; /* verde suave */
    }
</style>
@endsection

@section('page_scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    $('#students-followup').DataTable({
        order: [],
        language: {
            url: '/datatables.json'
        }
    });
});
</script>
@endsection
