@extends('layouts.app')

@section('title', 'Cargar alumnos a ciclo')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Cargar alumnos a ciclo</h3>
                </div>

                <form method="GET" action="{{ route('imports.cycle-students.template') }}">
                    <div class="card-body">
                        <div class="alert alert-info">
                            Selecciona campus y ciclo para descargar el archivo maestro. Cuando coordinacion lo complete, subelo en el bloque inferior para validar e importar.
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
                            <label for="template_campus_id">Campus</label>
                            <select id="template_campus_id" name="campus_id" class="form-control js-campus-select" required>
                                <option value="">Seleccione...</option>
                                @foreach($campuses as $campus)
                                    <option value="{{ $campus->id }}" {{ (int) old('campus_id', $activeCampusId) === (int) $campus->id ? 'selected' : '' }}>
                                        {{ $campus->name }} ({{ $campus->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="template_school_cycle_id">Ciclo escolar</label>
                            <select id="template_school_cycle_id" name="school_cycle_id" class="form-control js-cycle-select" required>
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

            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Importar archivo completado</h3>
                </div>

                <form method="POST" action="{{ route('imports.cycle-students.preview') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="card-body">
                        <div class="form-group">
                            <label for="import_campus_id">Campus</label>
                            <select id="import_campus_id" name="campus_id" class="form-control js-campus-select" required>
                                <option value="">Seleccione...</option>
                                @foreach($campuses as $campus)
                                    <option value="{{ $campus->id }}" {{ (int) old('campus_id', $activeCampusId) === (int) $campus->id ? 'selected' : '' }}>
                                        {{ $campus->name }} ({{ $campus->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="import_school_cycle_id">Ciclo escolar</label>
                            <select id="import_school_cycle_id" name="school_cycle_id" class="form-control js-cycle-select" required>
                                <option value="">Seleccione...</option>
                                @foreach($cycles as $cycle)
                                    <option value="{{ $cycle->id }}" data-campus-id="{{ $cycle->campus_id }}" {{ (string) old('school_cycle_id') === (string) $cycle->id ? 'selected' : '' }}>
                                        {{ $cycle->name }} ({{ $cycle->code }}) - {{ $cycle->active_groups_count }} grupos
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="file">Archivo de alumnos</label>
                            <input id="file" type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
                        </div>

                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" id="deactivate_missing" name="deactivate_missing" value="1" class="custom-control-input">
                            <label for="deactivate_missing" class="custom-control-label">
                                Inactivar alumnos del ciclo/campus que no aparezcan en el archivo
                            </label>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check mr-1"></i> Validar archivo
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
        function refreshCycles(campusSelect, cycleSelect) {
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

        document.querySelectorAll('.js-campus-select').forEach((campusSelect) => {
            const wrapper = campusSelect.closest('form');
            const cycleSelect = wrapper ? wrapper.querySelector('.js-cycle-select') : null;
            if (!cycleSelect) return;

            campusSelect.addEventListener('change', () => refreshCycles(campusSelect, cycleSelect));
            refreshCycles(campusSelect, cycleSelect);
        });
    })();
</script>
@endsection
