@extends('layouts.app')

@section('title', 'Importar horario maestro')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Importar horario maestro</h3>
                </div>

                <form method="POST" action="{{ route('imports.master-schedule.preview') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="card-body">
                        <div class="alert alert-info">
                            Selecciona campus, ciclo y archivo. La siguiente pantalla hara una validacion sin guardar horarios; la importacion real ocurre hasta confirmar.
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
                            <label for="file">Libro maestro Excel</label>
                            <input id="file" type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
                        </div>

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
                            <label>Ciclo escolar destino</label>
                            <div class="custom-control custom-radio">
                                <input id="cycle_mode_new" name="cycle_mode" type="radio" value="new" class="custom-control-input" {{ old('cycle_mode', 'new') === 'new' ? 'checked' : '' }}>
                                <label for="cycle_mode_new" class="custom-control-label">Crear ciclo nuevo</label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input id="cycle_mode_existing" name="cycle_mode" type="radio" value="existing" class="custom-control-input" {{ old('cycle_mode') === 'existing' ? 'checked' : '' }}>
                                <label for="cycle_mode_existing" class="custom-control-label">Usar ciclo existente</label>
                            </div>
                        </div>

                        <div id="existingCycleFields" class="border rounded p-3 mb-3">
                            <div class="form-group mb-0">
                                <label for="school_cycle_id">Ciclo existente</label>
                                <select id="school_cycle_id" name="school_cycle_id" class="form-control">
                                    <option value="">Seleccione...</option>
                                    @foreach($cycles as $cycle)
                                        <option value="{{ $cycle->id }}" data-campus-id="{{ $cycle->campus_id }}" {{ (string) old('school_cycle_id') === (string) $cycle->id ? 'selected' : '' }}>
                                            {{ $cycle->name }} ({{ $cycle->code }}) - {{ $cycle->campus->code ?? 'Sin campus' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div id="newCycleFields" class="border rounded p-3 mb-3">
                            <div class="form-row">
                                <div class="form-group col-md-8">
                                    <label for="name">Nombre del ciclo</label>
                                    <input id="name" name="name" class="form-control" value="{{ old('name', 'Preparatoria 2026-2027') }}">
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="code">Codigo</label>
                                    <input id="code" name="code" class="form-control" value="{{ old('code', 'PREPA-2026-2027') }}">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label for="modality_id">Modalidad</label>
                                    <select id="modality_id" name="modality_id" class="form-control">
                                        <option value="">Seleccione...</option>
                                        @foreach($modalities as $modality)
                                            <option value="{{ $modality->id }}" {{ (string) old('modality_id') === (string) $modality->id ? 'selected' : '' }}>
                                                {{ $modality->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="start_date">Inicio</label>
                                    <input id="start_date" type="date" name="start_date" class="form-control" value="{{ old('start_date', '2026-08-01') }}">
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="end_date">Fin</label>
                                    <input id="end_date" type="date" name="end_date" class="form-control" value="{{ old('end_date', '2027-07-31') }}">
                                </div>
                            </div>
                        </div>

                        <div class="border rounded p-3">
                            <label class="d-block">Catalogos faltantes</label>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" id="create_missing_groups" name="create_missing_groups" value="1" class="custom-control-input" checked>
                                <label for="create_missing_groups" class="custom-control-label">Crear grupos faltantes desde Grado + Grupo</label>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" id="create_missing_subjects" name="create_missing_subjects" value="1" class="custom-control-input" checked>
                                <label for="create_missing_subjects" class="custom-control-label">Crear materias faltantes usando Materias por grupo</label>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" id="create_missing_teachers" name="create_missing_teachers" value="1" class="custom-control-input" checked>
                                <label for="create_missing_teachers" class="custom-control-label">Crear docentes faltantes con correo @ula.local</label>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" id="deactivate_existing" name="deactivate_existing" value="1" class="custom-control-input">
                                <label for="deactivate_existing" class="custom-control-label">Inactivar horarios existentes del mismo ciclo/campus antes de importar</label>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" id="generate_sessions" name="generate_sessions" value="1" class="custom-control-input" checked>
                                <label for="generate_sessions" class="custom-control-label">Generar sesiones academicas del ciclo despues de importar</label>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Validar archivo</button>
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
        const newRadio = document.getElementById('cycle_mode_new');
        const existingRadio = document.getElementById('cycle_mode_existing');
        const newFields = document.getElementById('newCycleFields');
        const existingFields = document.getElementById('existingCycleFields');
        const campusSelect = document.getElementById('campus_id');
        const cycleSelect = document.getElementById('school_cycle_id');

        function refreshMode() {
            newFields.hidden = !newRadio.checked;
            existingFields.hidden = !existingRadio.checked;
        }

        function refreshCycles() {
            const campusId = campusSelect.value;
            cycleSelect.querySelectorAll('option[data-campus-id]').forEach((option) => {
                option.hidden = campusId !== '' && option.dataset.campusId !== campusId;
            });
        }

        newRadio.addEventListener('change', refreshMode);
        existingRadio.addEventListener('change', refreshMode);
        campusSelect.addEventListener('change', refreshCycles);
        refreshMode();
        refreshCycles();
    })();
</script>
@endsection
