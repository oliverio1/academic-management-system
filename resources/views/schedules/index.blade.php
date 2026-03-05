@extends('layouts.app')

@section('title', 'Horarios')

@section('content')
<div class="content px-3">
    <div class="card">

        <div class="card-header">
            <h4>
                Horarios —
                {{ $assignment->subject->name }}
                ({{ $assignment->teacher->user->name }})
            </h4>
        </div>

        <div class="card-body">

            @if(session('info'))
                <div class="alert alert-primary">{{ session('info') }}</div>
            @endif

            <form method="POST"
                  action="{{ route('schedules.store', [$group, $assignment]) }}">
                @csrf

                <div class="row">
                    <div class="col-md-3">
                        <select name="day_of_week" class="form-control" required>
                            <option value="">Día</option>
                            <option value="monday">Lunes</option>
                            <option value="tuesday">Martes</option>
                            <option value="wednesday">Miércoles</option>
                            <option value="thursday">Jueves</option>
                            <option value="friday">Viernes</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <input type="time" name="start_time"
                               class="form-control" required>
                    </div>

                    <div class="col-md-3">
                        <input type="time" name="end_time"
                               class="form-control" required>
                    </div>

                    <div class="col-md-3">
                        <button class="btn btn-primary btn-block">
                            Agregar
                        </button>
                    </div>
                </div>
            </form>

            <hr>

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Día</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($schedules as $schedule)
                        <tr>
                            <td>{{ ucfirst($schedule->day_of_week) }}</td>
                            <td>{{ $schedule->start_time }}</td>
                            <td>{{ $schedule->end_time }}</td>
                            <td>
                                <form method="POST"
                                      action="{{ route('schedules.deactivate', $schedule) }}">
                                    @csrf
                                    <button class="btn btn-danger btn-sm">
                                        <i class="fa fa-times"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

        </div>
    </div>
</div>
@endsection
