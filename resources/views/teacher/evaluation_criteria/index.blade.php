@extends('layouts.app')

@section('title', 'Criterios de evaluacion')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
        <div class="card-header">
            <h4>
                {{ $assignment->subject->name }}
                <small class="text-muted">
                    - Grupo {{ $assignment->group->name }}
                </small>
            </h4>
        </div>

        <div class="card-body">

            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @error('criteria')
                <div class="alert alert-danger">
                    {{ $message }}
                </div>
            @enderror

            @if($partials->isEmpty())
                <div class="alert alert-warning">
                    No hay parciales configurados para el ciclo activo de esta materia.
                </div>
            @endif

            <div class="row mb-3">
                <div class="col-md-12">
                    <label class="form-label"><strong>Parcial</strong></label>
                    <select class="form-control" id="partial-select" {{ $partials->isEmpty() ? 'disabled' : '' }}>
                        @foreach($partials as $partial)
                            <option value="{{ $partial->id }}" @selected((int) $selectedPartialId === (int) $partial->id)>
                                {{ $partial->name }}
                                @if($partial->academicPeriod)
                                    ({{ $partial->academicPeriod->start_date?->format('d/m/Y') }} - {{ $partial->academicPeriod->end_date?->format('d/m/Y') }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <form method="POST"
                action="{{ $criteria->isNotEmpty()
                        ? route('teacher.classes.evaluation.update', $assignment)
                        : route('teacher.classes.evaluation.store', $assignment) }}">
                @csrf

                @if($criteria->isNotEmpty())
                    @method('PUT')
                @endif

                <input type="hidden" name="cycle_partial_id" value="{{ $selectedPartialId }}">

                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Tipo de actividad</th>
                            <th width="120">Porcentaje</th>
                            <th width="60"></th>
                        </tr>
                    </thead>
                    <tbody id="criteria-table">
                        @foreach($criteria as $criterion)
                            <tr>
                                <td>
                                    <input type="text"
                                        name="criteria[{{ $criterion->id }}][name]"
                                        class="form-control"
                                        value="{{ $criterion->name }}"
                                        required>
                                </td>
                                <td>
                                    <input type="number"
                                        name="criteria[{{ $criterion->id }}][percentage]"
                                        class="form-control text-center"
                                        value="{{ $criterion->percentage }}"
                                        step="0.1"
                                        required>
                                </td>
                                <td class="text-center">
                                    <button type="button"
                                            class="btn btn-danger btn-sm remove-row">
                                        x
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <button type="button"
                        class="btn btn-outline-secondary btn-sm"
                        id="add-row"
                        {{ $partials->isEmpty() ? 'disabled' : '' }}>
                    + Agregar criterio
                </button>

                <hr>

                <p>
                    <strong>Total:</strong>
                    <span id="total">{{ $total }}</span> %
                </p>

                <button class="btn btn-primary" {{ $partials->isEmpty() ? 'disabled' : '' }}>
                    Guardar esquema
                </button>
            </form>

        </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_scripts')
<script>
let newIndex = 0;
const partialSelect = document.getElementById('partial-select');

if (partialSelect) {
    partialSelect.addEventListener('change', function () {
        const url = new URL(window.location.href);
        url.searchParams.set('partial_id', this.value);
        window.location.href = url.toString();
    });
}

document.getElementById('add-row').addEventListener('click', () => {
    const key = `new_${newIndex}`;
    const row = `
        <tr>
            <td>
                <input type="text"
                       name="criteria[${key}][name]"
                       class="form-control"
                       required
                       placeholder="Ej. Examen, Proyecto, Actividades">
            </td>
            <td>
                <input type="number"
                       name="criteria[${key}][percentage]"
                       class="form-control text-center"
                       step="0.1"
                       required>
            </td>
            <td class="text-center">
                <button type="button"
                        class="btn btn-danger btn-sm remove-row">
                    x
                </button>
            </td>
        </tr>
    `;

    document.getElementById('criteria-table').insertAdjacentHTML('beforeend', row);
    newIndex++;
    updateTotal();
});

document.addEventListener('click', function (e) {
    if (e.target.classList.contains('remove-row')) {
        e.target.closest('tr').remove();
        updateTotal();
    }
});

function updateTotal() {
    let total = 0;

    document.querySelectorAll('input[name$="[percentage]"]').forEach(input => {
        const value = parseFloat(input.value);
        if (!isNaN(value)) {
            total += value;
        }
    });

    document.getElementById('total').textContent = total.toFixed(2);
}

document.addEventListener('input', function (e) {
    if (e.target.name && e.target.name.endsWith('[percentage]')) {
        updateTotal();
    }
});

updateTotal();
</script>
@endsection
