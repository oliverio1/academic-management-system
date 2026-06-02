@if(request('tab') === 'attendance')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        Asistencias
        @if($activePeriod)
            <small class="text-muted">({{ $activePeriod->name }})</small>
        @endif
    </h5>

    <a href="{{ route('teacher.classes.sessions.index', $teachingAssignment) }}" class="btn btn-primary">
        Pasar lista
    </a>
</div>

@if(!$activePeriod)
    <div class="alert alert-warning">
        No hay periodo activo.
    </div>
@elseif($totalSessions === 0)
    <div class="alert alert-info">
        Aun no se han registrado sesiones de clase.
    </div>
@else

@if($attendanceSessions->isNotEmpty())
    <div class="mb-3">
        <label class="form-label">Asistencia registrada</label>
        <ul class="list-inline">
            @foreach($attendanceSessions as $session)
                <li class="list-inline-item">
                    <a href="{{ route('attendance.edit', $session) }}" class="badge bg-light text-dark">
                        {{ $session->session_date->format('d/m/Y') }}
                        |
                        {{ substr($session->start_time, 0, 5) }}
                        -
                        {{ substr($session->end_time, 0, 5) }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif

<table class="table table-sm align-middle">
    <thead>
        <tr>
            <th>Alumno</th>
            <th>Asistencias</th>
            <th class="text-end">%</th>
        </tr>
    </thead>
    <tbody>
        @foreach($attendanceSummary as $row)
            <tr>
                <td>{{ $row['student']->user->name }}</td>
                <td>{{ $row['attended'] }} / {{ $row['total'] }}</td>
                <td class="text-end">
                    <span class="badge {{ $row['percentage'] >= 80 ? 'bg-success' : 'bg-warning' }}">
                        {{ $row['percentage'] }} %
                    </span>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

@endif
@endif
