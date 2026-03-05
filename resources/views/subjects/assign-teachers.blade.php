@extends('layouts.app')

@section('title', 'Asignar profesores')

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
                            Asignación de profesores para la materia {{ $subject->name }}
                        </h3>
                        <hr>
                        <p class="text-muted mb-0">Asigne los profesores que pueden impartir la materia</p>
                    </div>
                    <div class="card-body">
                    <form method="POST" action="{{ route('subjects.teachers.update', $subject) }}">
                        @csrf
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted">
                                    Total de profesores: {{ $teachers->count() }}
                                </span>
                                <button type="button" class="btn btn-sm btn-info" id="toggleAll">
                                    Seleccionar todos
                                </button>
                            </div>
                            <div class="row">
                                @foreach($teachers as $teacher)
                                    <div class="col-md-6 col-lg-4">
                                        <div class="custom-control custom-checkbox mb-3">
                                            <input type="checkbox"
                                                class="custom-control-input teacher-checkbox"
                                                name="teachers[]"
                                                value="{{ $teacher->id }}"
                                                id="teacher{{ $teacher->id }}"
                                                {{ in_array($teacher->id, $assigned) ? 'checked' : '' }}>

                                            <label class="custom-control-label"
                                                for="teacher{{ $teacher->id }}">
                                                {{ $teacher->user->name }}
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary">Guardar</button>
                        <a href="{{ route('subjects.index') }}" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</div>
@endsection

@section('page_scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('toggleAll');
    const checkboxes = document.querySelectorAll('.teacher-checkbox');

    function allChecked() {
        return [...checkboxes].every(cb => cb.checked);
    }

    toggleBtn.addEventListener('click', function () {
        const shouldCheck = !allChecked();

        checkboxes.forEach(cb => cb.checked = shouldCheck);

        toggleBtn.textContent = shouldCheck
            ? 'Deseleccionar todos'
            : 'Seleccionar todos';
    });

    if (allChecked()) {
        toggleBtn.textContent = 'Deseleccionar todos';
    }
});
</script>
@endsection
