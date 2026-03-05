@extends('layouts.app')

@section('title', 'Asignaciones')

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
                                Asignación de profesores para el grupo {{ $group->name }}
                            </h3>
                            <hr>
                            <p class="text-muted mb-0">Asigna un profesor a cada materia del grupo</p>
                        </div>
                        <div class="card-body">
                            <form method="POST"
                                action="{{ route('groups.assignments.update', $group) }}">
                                @csrf
                                <div class="card-body p-0">
                                    <table class="table table-hover mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th style="width: 40%">Materia</th>
                                                <th>Profesor asignado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($subjects as $subject)
                                                <tr class="{{ optional($group->assignments->firstWhere('subject_id', $subject->id))->teacher_id ? '' : 'table-warning' }}">
                                                    <td>
                                                        <strong>{{ $subject->name }}</strong>
                                                    </td>
                                                    <td>
                                                        <select name="assignments[{{ $subject->id }}]" class="form-control form-control-sm">
                                                            @foreach($teachers as $teacher)
                                                                @if($teacher->subjects->contains($subject))
                                                                    <option value="{{ $teacher->id }}"
                                                                        {{ optional(
                                                                            $group->assignments
                                                                                ->firstWhere('subject_id', $subject->id)
                                                                        )->teacher_id == $teacher->id ? 'selected' : '' }}>
                                                                        {{ $teacher->user->name }}
                                                                    </option>
                                                                @endif
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
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