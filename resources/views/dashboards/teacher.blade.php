@extends('layouts.app')

@section('title', 'Dashboard profesor')

@section('content')
@foreach (['success', 'info', 'warning', 'danger'] as $type)
    @if(session($type))
        <div class="alert alert-{{ $type }} alert-dismissible fade show" role="alert">
            <strong>{{ session($type) }}</strong>
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif
@endforeach
<div class="content px-3">
    <div class="clearfix"></div>
        <div class="row">
            <div class="col-md-12 mt-3">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <h4 class="font-weight-bold mb-1">
                                        Buen día, {{ auth()->user()->name }} 👋
                                    </h4>
                                    <div class="text-muted small">
                                        {{ now()->translatedFormat('l d \\d\\e F') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-12 mt-3">
                <div class="card">
                    <div class="card-header">
                        <h3>Actividades del día</h3>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card-body">
                                <div class="mb-4">
                                    @forelse($todayClasses as $class)
                                        <div class="col-md-12 col-md-6 mb-3">
                                            <div class="card h-100 shadow-sm border-0">
                                                <div class="card-body d-flex flex-column">

                                                    <div class="mb-3">
                                                        <h6 class="font-weight-bold mb-1">
                                                            {{ $class->subject }}
                                                        </h6>
                                                        <div class="small text-muted">
                                                            {{ $class->group }} · {{ $class->time }}
                                                        </div>
                                                    </div>

                                                    <div class="mt-auto">
                                                        <div class="row">

                                                            {{-- ASISTENCIA --}}
                                                            <div class="col-6">
                                                                <div class="text-center">

                                                                    <small class="text-muted d-block mb-1">
                                                                        Asistencia
                                                                    </small>

                                                                    @if($class->attendance_closed)
                                                                        <span class="text-muted small">
                                                                            🔒 Cerrada
                                                                        </span>

                                                                    @elseif($class->attendance_registered)
                                                                        <span class="text-success small font-weight-bold">
                                                                            ✔ Registrada
                                                                        </span>

                                                                    @else
                                                                        <a href="{{ route('attendance.take', $class->session_id) }}"
                                                                        class="btn btn-warning btn-sm btn-block">
                                                                            Tomar
                                                                        </a>
                                                                    @endif

                                                                </div>
                                                            </div>

                                                            {{-- ACTIVIDAD --}}
                                                            <div class="col-6">
                                                                <div class="text-center">

                                                                    <small class="text-muted d-block mb-1">
                                                                        Actividad
                                                                    </small>

                                                                    @if($class->attendance_closed)
                                                                        <span class="text-muted small">
                                                                            🔒 Cerrada
                                                                        </span>

                                                                    @elseif($class->activity_assigned)
                                                                        <span class="text-success small font-weight-bold">
                                                                            ✔ Asignada
                                                                        </span>

                                                                    @else
                                                                        <a href="{{ route('session.activities.create', $class->session_id) }}"
                                                                        class="btn btn-outline-primary btn-sm btn-block">
                                                                            Asignar
                                                                        </a>
                                                                    @endif

                                                                </div>
                                                            </div>

                                                        </div>
                                                    </div>                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="col-12">
                                            <div class="text-muted small">
                                                No tienes clases programadas para hoy.
                                            </div>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12 mt-3">
                        <div>
                            <hr>
                            @if($pendingAttendances->count())
                                <div class="mb-4">
                                    <div class="text-uppercase small font-weight-bold text-warning mb-3">
                                        ⚠️ Pendientes
                                    </div>
        
                                    @foreach($pendingAttendances as $pending)
                                        <div class="card shadow-sm border-0 mb-2">
                                            <div class="card-body d-flex justify-content-between align-items-center">
                                                <div>
                                                    <div class="font-weight-bold">
                                                        {{ $pending->subject }} – {{ $pending->group }}
                                                    </div>
                                                    <div class="small text-muted">
                                                        Asistencia por cerrar
                                                    </div>
                                                </div>
        
                                                <a href="{{ route('attendance.take', $pending->session_id) }}"
                                                class="btn btn-outline-primary btn-sm">
                                                    Ir
                                                </a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-12 mt-3">
                <div class="card">
                    <div class="card-header">
                        <h3>Avisos institucionales</h3>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card-body">
                                @if($notifications->count())
                                    <div>
                                        <div class="text-uppercase small font-weight-bold text-muted mb-3">
                                            🔔 Avisos institucionales
                                        </div>

                                        @foreach($notifications as $note)
                                            <div class="card bg-light border-0 mb-2">
                                                <div class="card-body py-2">
                                                    <div class="small">
                                                        ✔ {{ $note->message }}
                                                    </div>
                                                    <div class="text-muted small">
                                                        No requiere acción
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
