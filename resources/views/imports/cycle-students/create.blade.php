@extends('layouts.app')

@section('title', 'Cargar alumnos a ciclo')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-9">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Cargar alumnos a ciclo</h3>
                </div>

                <form method="GET" action="{{ route('imports.cycle-students.template') }}">
                    <div class="card-body">
                        <div class="alert alert-info">
                            Selecciona campus y ciclo para descargar un archivo maestro con los grupos activos ya precargados.
                        </div>

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="form-group">
                            <label for="campus_id">Campus</label>
                            <select id="campus_id" name="campus_id" class="form-control" required>
                                <option value="">Seleccione...</option>
                                @foreach($campuses as $campus)
                                    <option value="{{ $campus->id }}" {{ (int) old('campus_id', $activeCampusId) === (int) $campus->id ? 'selected' : '' }}>
                                        {{ $campus->name }} ({{ $campus->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="school_cycle_id">Ciclo escolar</label>
                            <select id="school_cycle_id" name="school_cycle_id" class="form-control" required>
                                <option value="">Seleccione...</option>
                                @foreach($cycles as $cycle)
                                    <option value="{{ $cycle->id }}" data-campus-id="{{ $cycle->campus_id }}" {{ (string) old('school_cycle_id') === (string) $cycle->id ? 'selected' : '' }}>
                                        {{ $cycle->name }} ({{ $cycle->code }}) - {{ $cycle->active_groups_count }} grupos
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="border rounded p-3">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-file-excel text-success mt-1 mr-2"></i>
                                <div>
                                    <strong>El archivo incluye:</strong>
                                    <div class="text-muted">
                                        Hoja ALUMNOS con filas por grupo, hoja CATALOGOS e instrucciones para conservar el formato.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-download mr-1"></i> Descargar archivo maestro
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_scripts')
<script>
    (function () {
        const campusSelect = document.getElementById('campus_id');
        const cycleSelect = document.getElementById('school_cycle_id');

        function refreshCycles() {
            const campusId = campusSelect.value;
            let firstVisible = null;

            cycleSelect.querySelectorAll('option[data-campus-id]').forEach((option) => {
                const visible = campusId === '' || option.dataset.campusId === campusId;
                option.hidden = !visible;

                if (visible && firstVisible === null) {
                    firstVisible = option;
                }
            });

            if (cycleSelect.selectedOptions.length && cycleSelect.selectedOptions[0].hidden) {
                cycleSelect.value = firstVisible ? firstVisible.value : '';
            }
        }

        campusSelect.addEventListener('change', refreshCycles);
        refreshCycles();
    })();
</script>
@endsection
