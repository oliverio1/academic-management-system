@extends('layouts.app')

@section('title', 'Materias asignadas')

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
                                Asignación de materias para el grupo {{ $group->name }}
                            </h3>
                            <hr>
                            <p class="text-muted mb-0">Selecciona las materias que se impartirán en este grupo</p>
                        </div>
                        <div class="card-body">
                            <form method="POST"
                                action="{{ route('groups.subjects.update', $group) }}">
                                @csrf
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <button type="button" class="btn btn-sm btn-info btn-block" id="toggleAll">Seleccionar todas</button>
                                    </div>
                                    <div class="row">
                                        @foreach($subjects as $subject)
                                            <div class="col-md-6 col-lg-4">
                                                <div class="custom-control custom-checkbox mb-3">
                                                    <input type="checkbox"
                                                        class="custom-control-input subject-checkbox"
                                                        name="subjects[]"
                                                        value="{{ $subject->id }}"
                                                        id="subject{{ $subject->id }}"
                                                        {{ in_array($subject->id, $assignedSubjects) ? 'checked' : '' }}>
                                                    <label class="custom-control-label"
                                                        for="subject{{ $subject->id }}">
                                                        <strong>{{ $subject->name }}</strong><br>
                                                        <small class="text-muted">
                                                            {{ $subject->hours_per_week }} horas por semana
                                                        </small>
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
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

@section('page_scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggleBtn = document.getElementById('toggleAll');
        const checkboxes = document.querySelectorAll('.subject-checkbox');

        function allChecked() {
            return [...checkboxes].every(cb => cb.checked);
        }

        toggleBtn.addEventListener('click', function () {
            const shouldCheck = !allChecked();

            checkboxes.forEach(cb => cb.checked = shouldCheck);

            toggleBtn.textContent = shouldCheck
                ? 'Deseleccionar todas'
                : 'Seleccionar todas';
        });

        // Estado inicial del botón
        if (allChecked()) {
            toggleBtn.textContent = 'Deseleccionar todas';
        }
    });
    </script>
@endsection
