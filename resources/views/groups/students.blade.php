@extends('layouts.app')

@section('title', 'Asignar alumnos')

@section('content')
    @if(session('info'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <strong>{{ session('info') }}</strong>
        </div>
    @endif
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12 connectedSortable mt-3">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="mb-0">
                                Asignación de alumnos para el grupo {{ $group->name }}
                            </h3>
                            <hr>
                            <p class="text-muted mb-0">Selecciona los alumnos que pertenecen a este grupo</p>
                        </div>
                        <div class="card-body">
                            <form method="POST"
                                action="{{ route('groups.students.update', $group) }}">
                                @csrf
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted">
                                        Total de alumnos: {{ $students->count() }}
                                    </span>
                                </div>
                                <div class="row">
                                    @foreach($students as $student)
                                        <div class="col-md-6 col-lg-4">
                                            <div class="custom-control custom-checkbox mb-3">
                                                <input type="checkbox"
                                                    class="custom-control-input student-checkbox"
                                                    name="students[]"
                                                    value="{{ $student->id }}"
                                                    id="student{{ $student->id }}"
                                                    {{ in_array($student->id, $assigned) ? 'checked' : '' }}>
                                                <label class="custom-control-label"
                                                    for="student{{ $student->id }}">
                                                    {{ $student->user->name }}
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="card-footer">
                                <button class="btn btn-primary">Guardar</button>
                                <a href="{{ route('groups.index') }}" class="btn btn-secondary">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection