@extends('layouts.app')

@section('title', 'Importar asistencias')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Importar asistencias historicas</h3>
                </div>

                <form method="POST"
                      action="{{ route('imports.attendances.store') }}"
                      enctype="multipart/form-data">
                    @csrf

                    <div class="card-body">
                        <div class="alert alert-info mb-3">
                            <strong>Formato esperado:</strong> cada hoja del Excel debe llamarse
                            <code>GRUPO-MATERIA</code> (ejemplo <code>1110-QUIMICA I</code>).
                            Valores validos por celda: <code>1</code> asistencia,
                            <code>0</code> falta, <code>2</code> justificada,
                            <code>x</code> sin sesion (se omite).
                        </div>

                        <div class="form-group">
                            <label for="school_cycle_id">Ciclo escolar</label>
                            <select id="school_cycle_id"
                                    name="school_cycle_id"
                                    class="form-control"
                                    required>
                                <option value="">Seleccione...</option>
                                @foreach($schoolCycles as $cycle)
                                    <option value="{{ $cycle->id }}">
                                        {{ $cycle->name }} ({{ $cycle->modality->name ?? 'Sin modalidad' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="academic_period_id">Periodo academico (parcial)</label>
                            <select id="academic_period_id"
                                    name="academic_period_id"
                                    class="form-control"
                                    required>
                                <option value="">Seleccione...</option>
                                @foreach($schoolCycles as $cycle)
                                    @foreach($cycle->partials as $partial)
                                        @if($partial->academicPeriod)
                                            <option value="{{ $partial->academicPeriod->id }}"
                                                    data-cycle-id="{{ $cycle->id }}">
                                                {{ $partial->name }} - {{ $partial->academicPeriod->name }}
                                            </option>
                                        @endif
                                    @endforeach
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="file">Archivo Excel</label>
                            <input id="file"
                                   type="file"
                                   name="file"
                                   class="form-control"
                                   accept=".xlsx,.xls,.ods"
                                   required>
                        </div>

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            Importar asistencias
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
        const cycleSelect = document.getElementById('school_cycle_id');
        const periodSelect = document.getElementById('academic_period_id');

        function filterPeriods() {
            const cycleId = cycleSelect.value;
            const options = periodSelect.querySelectorAll('option[data-cycle-id]');

            periodSelect.value = '';
            options.forEach((option) => {
                option.hidden = cycleId !== '' && option.dataset.cycleId !== cycleId;
            });
        }

        cycleSelect.addEventListener('change', filterPeriods);
        filterPeriods();
    })();
</script>
@endsection
