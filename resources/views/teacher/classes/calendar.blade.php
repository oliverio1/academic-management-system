@extends('layouts.app')

@section('content')
<div class="container-fluid">

    <h4 class="mb-3">Calendario semanal</h4>

    @forelse($sessions->groupBy('session_date') as $date => $daySessions)

        <div class="card mb-3">
            <div class="card-header">
                {{ \Carbon\Carbon::parse($date)->translatedFormat('l j \\d\\e F') }}
            </div>

            <ul class="list-group list-group-flush">
                @foreach($daySessions as $session)
                    <li class="list-group-item">

                        <strong>
                            {{ $session->teachingAssignment->subject->name }}
                        </strong>
                        <span class="text-muted">
                            ({{ $session->teachingAssignment->group->name }})
                        </span>

                        <div class="small text-muted">
                            {{ substr($session->start_time, 0, 5) }}
                            –
                            {{ substr($session->end_time, 0, 5) }}
                        </div>

                        <div class="mt-2">
                            @if($session->attendance_closed_at)
                                <span class="text-muted small">🔒 Semana cerrada</span>
                            @elseif($session->attendances_count > 0)
                                <span class="text-success small">✔ Asistencia registrada</span>
                            @else
                                <span class="text-warning small">Pendiente</span>
                            @endif
                        </div>

                    </li>
                @endforeach
            </ul>
        </div>

    @empty
        <div class="alert alert-info">
            No hay sesiones esta semana.
        </div>
    @endforelse

</div>
@endsection
