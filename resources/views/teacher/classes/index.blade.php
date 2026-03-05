
@extends('layouts.app')

@section('title', 'Asistencia')

@section('content')
    @if(session('warning'))
        <div class="alert alert-warning">
            {{ session('warning') }}
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            Revisa la información antes de guardar.
        </div>
    @endif
    <div class="content px-3">
        <div class="clearfix"></div>
        <div class="row">
            <div class="col-md-12 mt-3">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-3">Mis clases</h4>
                    </div>
                    <div class="card-body">
                        @if($assignments->isEmpty())
                            <div class="alert alert-info">
                                No tienes clases asignadas actualmente.
                            </div>
                        @else
                            <div class="row">
                                @foreach($assignments as $assignment)
                                    <div class="col-md-4 mt-3 mb-3">
                                        <div class="card mb-3 h-100">
                                            <div class="card-body">
                                                <h5 class="card-title mb-1">{{ $assignment->subject->name }}</h5>
                                                <p class="text-muted mb-3"> ({{ $assignment->group->name }})</p>
                                                <a href="{{ route('teacher.classes.sessions.index', $assignment) }}" class="btn btn-primary btn-sm btn-block">Ver sesiones</a>
                                                @if($assignment->evaluationCriteria()->exists())
                                                    <a href="{{ route('teacher.classes.evaluation.index', $assignment) }}"
                                                    class="btn btn-outline-primary btn-sm btn-block">
                                                        Editar rubros
                                                    </a>
                                                @else
                                                    <a href="{{ route('teacher.classes.evaluation.index', $assignment) }}"
                                                    class="btn btn-primary btn-sm btn-block">
                                                        Configurar rubros
                                                    </a>
                                                @endif                                            
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <hr>
                    <div class="m-3">
                        <div class="card mt-4">
                            <div class="card-header">
                                Horario semanal
                            </div>
                            @php
                                $days = ['lunes','martes','miercoles','jueves','viernes'];

                                $timeSlots = [
                                    '07:00-07:50',
                                    '07:50-08:40',
                                    '08:40-09:30',
                                    '09:30-10:00', // descanso
                                    '10:00-10:50',
                                    '10:50-11:40',
                                    '11:40-12:10', // descanso
                                    '12:10-13:00',
                                    '13:00-13:50',
                                    '13:50-14:40',
                                ];
                                $breaks = [
                                    '09:30-10:00',
                                    '11:40-12:10',
                                ];
                            @endphp
                            <div class="card-body p-0">
                                <table class="table table-bordered text-center mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th></th>
                                            @foreach($days as $day)
                                                <th>{{ strtoupper($day) }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @foreach($timeSlots as $slot)

                                            {{-- DESCANSO --}}
                                            @if(in_array($slot, $breaks))
                                                <tr>
                                                    <td class="text-muted small font-weight-bold">
                                                        {{ $slot }}
                                                    </td>
                                                    <td colspan="{{ count($days) }}" class="p-0">
                                                        <div class="schedule-break text-center py-2">
                                                            DESCANSO
                                                        </div>
                                                    </td>
                                                </tr>
                                                @continue
                                            @endif

                                            {{-- BLOQUES NORMALES --}}
                                            <tr>
                                                <td class="text-muted small">
                                                    {{ $slot }}
                                                </td>

                                                @foreach($days as $day)
                                                    @php
                                                        $block = $schedules->first(function ($schedule) use ($day, $slot) {
                                                            return $schedule->day_of_week === $day
                                                                && substr($schedule->start_time,0,5).'-'.substr($schedule->end_time,0,5) === $slot;
                                                        });
                                                    @endphp

                                                    <td class="p-0 align-middle">
                                                        @if($block)
                                                            <a href="{{ route('teacher.classes.sessions.index', $block->assignment) }}"
                                                                class="d-block h-100 w-100 text-dark text-decoration-none">

                                                                    <div
                                                                        class="h-100 w-100 d-flex flex-column justify-content-center text-center"
                                                                        style="background-color: {{ subjectColor($block->assignment->subject_id) }};"
                                                                    >
                                                                        <strong>
                                                                            {{ $block->assignment->subject->name }}
                                                                        </strong>

                                                                        <div class="small text-muted">
                                                                            {{ ucfirst($block->type) }}
                                                                        </div>
                                                                    </div>

                                                            </a>
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>

                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('page_css')
<style>
    .schedule-break {
        background-color: #f1f3f5;
        color: #6c757d;
        font-weight: bold;
        letter-spacing: 0.1em;
        font-size: 0.8rem;
    }
</style>
@endsection

@section('page_scripts')
@endsection


