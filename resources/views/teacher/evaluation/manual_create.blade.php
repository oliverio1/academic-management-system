@extends('layouts.app')

@section('title', 'Nueva actividad manual')

@section('content')
    <div class="content px-3">
        <div class="clearfix"></div>
        <div class="row">
            <div class="col-md-10 mt-3">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Nueva actividad manual</h4>
                        <small class="text-muted">
                            {{ $assignment->subject->name }} - {{ $assignment->group->name }}
                        </small>
                    </div>
                    <div class="card-body">
                        @if(!($hasAnyCriteria ?? false))
                            <div class="alert alert-warning">
                                Primero debes configurar los rubros de esta materia para crear actividades manuales.
                            </div>
                        @endif

                        <form method="POST" action="{{ route('teacher.evaluation.manual.store', $assignment) }}">
                            @csrf

                            <div class="form-group">
                                <label for="title">Titulo</label>
                                <input type="text" id="title" name="title" class="form-control" value="{{ old('title') }}" required>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="academic_period_id">Parcial</label>
                                    <select id="academic_period_id" name="academic_period_id" class="form-control" required>
                                        <option value="">Seleccione...</option>
                                        @foreach($periods as $period)
                                            <option value="{{ $period->id }}" {{ (string) old('academic_period_id') === (string) $period->id ? 'selected' : '' }}>
                                                {{ $period->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="evaluation_criterion_id">Rubro</label>
                                    <select id="evaluation_criterion_id" name="evaluation_criterion_id" class="form-control" required>
                                        <option value="">Seleccione...</option>
                                        @foreach($criteria as $criterion)
                                            <option value="{{ $criterion['id'] }}" {{ (string) old('evaluation_criterion_id') === (string) $criterion['id'] ? 'selected' : '' }}>
                                                {{ $criterion['label'] ?? $criterion['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="max_score">Puntaje maximo</label>
                                    <input type="number" id="max_score" name="max_score" step="0.01" min="0.01" class="form-control" value="{{ old('max_score', '10') }}" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="due_date">Fecha de entrega (opcional)</label>
                                    <input type="date" id="due_date" name="due_date" class="form-control" value="{{ old('due_date') }}">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="description">Descripcion (opcional)</label>
                                <textarea id="description" name="description" rows="3" class="form-control">{{ old('description') }}</textarea>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <a href="{{ route('teacher.evaluation.activities', $assignment) }}" class="btn btn-secondary">
                                    Volver
                                </a>
                                <button class="btn btn-primary" {{ !($hasAnyCriteria ?? false) ? 'disabled' : '' }}>
                                    Guardar actividad
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('page_css')
@endsection

@section('page_scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const criteriaByPeriod = @json($criteriaByPeriod);
    const periodSelect = document.getElementById('academic_period_id');
    const criterionSelect = document.getElementById('evaluation_criterion_id');

    function refreshCriteria() {
        const periodId = periodSelect.value || '';
        const list = criteriaByPeriod[periodId] || [];
        const previous = criterionSelect.value;

        criterionSelect.innerHTML = '<option value="">Seleccione...</option>';
        list.forEach(item => {
            const option = document.createElement('option');
            option.value = String(item.id);
            option.textContent = item.label || item.name;
            if (String(item.id) === String(previous)) {
                option.selected = true;
            }
            criterionSelect.appendChild(option);
        });
    }

    periodSelect.addEventListener('change', refreshCriteria);
    refreshCriteria();
});
</script>
@endsection
