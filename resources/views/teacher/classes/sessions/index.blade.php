
@extends('layouts.app')

@section('title', 'Sesiones')

@section('content')
    @if(session('info'))
        <div class="alert alert-primary" role="alert">
            <strong>{{ session('info') }}</strong>
        </div>    
    @endif
    <div class="content px-3">
        <div class="clearfix"></div>
        <div class="row">
            <div class="col-md-12 mt-3">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-sm-6">
                                <h4 class="mb-0">
                                    {{ $assignment->subject->name }} - {{ $assignment->group->name }}
                                </h4>
                            </div>
                            <div class="col-sm-6">
                                <a href="{{ route('teacher.classes.index') }}"
                                    class="btn btn-secondary float-right">
                                        Volver
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Horario</th>
                                    <th class="text-center">Asistencia</th>
                                    <th class="text-center">Actividad</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sessions as $session)
                                    <tr>
                                        <td>
                                            {{ $session->session_date->translatedFormat('l j \\d\\e F') }}
                                        </td>
                                        <td>
                                            {{ substr($session->start_time, 0, 5) }}
                                            –
                                            {{ substr($session->end_time, 0, 5) }}
                                        </td>

                                        {{-- ASISTENCIA --}}
                                        <td class="text-center">
                                            @if($session->attendance_closed_at)
                                                <span class="text-muted">🔒</span>
                                            @elseif($session->attendances_count > 0)
                                                <span class="text-success">✔</span>
                                            @else
                                                <a href="{{ route('attendance.take', $session->id) }}"
                                                class="btn btn-warning btn-sm">
                                                    Tomar
                                                </a>
                                            @endif
                                        </td>

                                        {{-- ACTIVIDAD --}}
                                        <td class="text-center">
                                            @if($session->attendance_closed_at)
                                                <span class="text-muted">🔒</span>
                                            @elseif($session->session_activity_count > 0)
                                                <span class="text-success">✔</span>
                                            @else
                                                <a href="{{ route('session.activities.create', $session->id) }}"
                                                class="btn btn-outline-primary btn-sm">
                                                    Asignar
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>


@endsection

@section('page_css')
@endsection

@section('page_scripts')
@endsection
