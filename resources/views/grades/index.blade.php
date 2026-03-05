@extends('layouts.app')

@section('title', 'Calificaciones')

@section('content')
<div class="content px-3">
    <div class="card">

        <div class="card-header">
            <h4>
                Calificaciones —
                {{ $activity->title }}
            </h4>
        </div>

        <div class="card-body">

            @if(session('info'))
                <div class="alert alert-primary">{{ session('info') }}</div>
            @endif

            <form method="POST"
                  action="{{ route('grades.store', $activity) }}">
                @csrf

                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Alumno</th>
                            <th>Calificación</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($students as $student)
                            <tr>
                                <td>{{ $student->user->name }}</td>
                                <td>
                                    <input type="number"
                                           name="grades[{{ $student->id }}]"
                                           class="form-control"
                                           step="0.01"
                                           max="{{ $activity->max_score }}"
                                           value="{{ optional($grades->get($student->id))->score }}">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <button class="btn btn-primary">
                    Guardar calificaciones
                </button>
            </form>

        </div>
    </div>
</div>
@endsection
