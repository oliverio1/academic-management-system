@extends('layouts.app')

@section('title', 'Nuevo equipo')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">

                <div class="card-header">
                    <h4>Nuevo equipo</h4>
                </div>

                <div class="card-body">
                    @php
                        $assignedStudentIds = $assignment->teams
                            ->flatMap->students
                            ->pluck('id')
                            ->unique();

                        // Si estamos editando, permitir los alumnos del equipo actual
                        if (isset($team)) {
                            $assignedStudentIds = $assignedStudentIds
                                ->diff($team->students->pluck('id'));
                        }
                    @endphp
                    <form method="POST" action="{{ route('teams.store', $assignment) }}">
                        @csrf

                        @include('teams._form')

                        <button class="btn btn-primary">Guardar</button>
                        <a href="{{ route('teams.index', $assignment) }}" class="btn btn-secondary">
                            Cancelar
                        </a>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
