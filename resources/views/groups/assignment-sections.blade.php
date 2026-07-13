@extends('layouts.app')

@section('title', 'Secciones por materia')

@section('content')
    @if(session('info'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <strong>{{ session('info') }}</strong>
        </div>
    @endif

    <div class="app-content">
        <div class="container-fluid">
            <div class="row mt-3">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-1">Asignar alumnos por sección</h4>
                            <div class="text-muted">
                                Grupo: <strong>{{ $group->name }}</strong> |
                                Materia: <strong>{{ $subject->name }}</strong> |
                                Secciones: <strong>{{ $sectionCount }}</strong>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('groups.assignments.sections.update', [$group, $subject]) }}">
                            @csrf
                            @method('PUT')
                            <div class="card-body">
                                <div class="alert alert-warning">
                                    Un alumno solo puede estar en una sección de la misma materia.
                                </div>
                                <div class="row">
                                    @for($section = 1; $section <= $sectionCount; $section++)
                                        @php
                                            $assignment = $assignments->get($section);
                                            $selectedIds = $assignment ? $assignment->students->pluck('id')->map(fn($id) => (int) $id)->all() : [];
                                        @endphp
                                        <div class="col-md-4 mb-3">
                                            <div class="card h-100 border">
                                                <div class="card-header bg-light">
                                                    <strong>{{ $assignment?->section_display ?? 'Seccion '.$section }}</strong>
                                                    <div class="small text-muted mt-1">
                                                        {{ optional(optional($assignment)->teacher)->user->name ?? 'Sin docente asignado' }}
                                                    </div>
                                                </div>
                                                <div class="card-body">
                                                    @if($assignment)
                                                        <select class="form-control" name="sections[{{ $section }}][]" multiple size="15">
                                                            @foreach($students as $student)
                                                                <option value="{{ $student->id }}" {{ in_array((int) $student->id, $selectedIds, true) ? 'selected' : '' }}>
                                                                    {{ $student->user->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    @else
                                                        <p class="text-muted mb-0">
                                                            Asigna primero un docente en esta sección.
                                                        </p>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endfor
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">Guardar secciones</button>
                                <a href="{{ route('groups.assignments.edit', $group) }}" class="btn btn-secondary">Regresar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
