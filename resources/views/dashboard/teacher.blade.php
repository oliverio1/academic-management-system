@extends('layouts.app')

@section('title', 'Dashboard Profesor')

@section('content')
<div class="content px-3">
    <div class="card">
        <div class="card-header">
            <h4>Mis clases</h4>
        </div>

        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Grupo</th>
                        <th>Materia</th>
                        <th>Horarios</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(auth()->user()->teacher->assignments as $assignment)
                        <tr>
                            <td>{{ $assignment->group->name }}</td>
                            <td>{{ $assignment->subject->name }}</td>
                            <td>
                                @foreach($assignment->schedules as $schedule)
                                    <span class="badge badge-info">
                                        {{ ucfirst($schedule->day_of_week) }}
                                        {{ $schedule->start_time }}
                                    </span>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
