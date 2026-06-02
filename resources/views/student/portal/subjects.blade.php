@extends('layouts.app')

@section('title', 'Mi horario')

@section('content')
<div class="content px-3">
    <div class="clearfix"></div>
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Mi horario</h3>
                    <small class="text-muted">Grupo: {{ $student->group->name ?? 'Sin grupo' }}</small>
                </div>
                <div class="card-body">
                    @if($assignments->isEmpty())
                        <div class="alert alert-info mb-0">
                            No tienes materias asignadas actualmente.
                        </div>
                    @else
                        @php
                            $days = ['lunes','martes','miercoles','jueves','viernes'];

                            $timeSlots = [
                                '07:00-07:50',
                                '07:50-08:40',
                                '08:40-09:30',
                                '09:30-10:00',
                                '10:00-10:50',
                                '10:50-11:40',
                                '11:40-12:10',
                                '12:10-13:00',
                                '13:00-13:50',
                                '13:50-14:40',
                            ];

                            $breaks = [
                                '09:30-10:00',
                                '11:40-12:10',
                            ];
                        @endphp

                        <div class="card mb-0">
                            <div class="card-header">Horario semanal</div>
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
                                @if(in_array($slot, $breaks))
                                    <tr>
                                        <td class="text-muted small font-weight-bold">{{ $slot }}</td>
                                        <td colspan="{{ count($days) }}" class="p-0">
                                            <div class="schedule-break text-center py-2">
                                                DESCANSO
                                            </div>
                                        </td>
                                    </tr>
                                    @continue
                                @endif

                                <tr>
                                    <td class="text-muted small">{{ $slot }}</td>
                                    @foreach($days as $day)
                                        @php
                                            $block = $scheduleBlocks->first(function ($entry) use ($day, $slot) {
                                                return $entry['day'] === $day
                                                    && $entry['slot'] === $slot;
                                            });
                                        @endphp

                                        <td class="p-0 align-middle">
                                            @if($block)
                                                <a href="{{ route('student.subjects.show', $block['assignment']) }}"
                                                   class="d-block h-100 w-100 text-dark text-decoration-none">
                                                    <div class="h-100 w-100 d-flex flex-column justify-content-center text-center"
                                                         style="background-color: {{ subjectColor($block['assignment']->subject_id) }};">
                                                        <strong>{{ $block['assignment']->subject->name }}</strong>
                                                        <div class="small text-muted">
                                                            {{ $block['assignment']->teacher->user->name }}
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
                    @endif
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
