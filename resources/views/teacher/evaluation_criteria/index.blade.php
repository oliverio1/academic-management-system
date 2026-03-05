@extends('layouts.app')

@section('title', 'Criterios de evaluación')

@section('content')
<div class="container-fluid">

    <div class="card">
        <div class="card-header">
            <h4>
                {{ $assignment->subject->name }}
                <small class="text-muted">
                    — Grupo {{ $assignment->group->name }}
                </small>
            </h4>
        </div>

        <div class="card-body">

            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @error('percentage')
                <div class="alert alert-danger">
                    {{ $message }}
                </div>
            @enderror

            <form method="POST"
                action="{{ $criteria->isNotEmpty()
                        ? route('teacher.classes.evaluation.update', $assignment)
                        : route('teacher.classes.evaluation.store', $assignment) }}">
                @csrf

                @if($criteria->isNotEmpty())
                    @method('PUT')
                @endif

                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Tipo de actividad</th>
                            <th width="120">Porcentaje</th>
                            <th width="60"></th>
                        </tr>
                    </thead>
                    <tbody id="criteria-table">
                        @php
                            $attendance = $criteria->firstWhere('name', 'Asistencia');
                        @endphp
                        <tr class="table-light">
                            <td>
                                <input type="hidden"
                                    name="criteria[attendance][name]"
                                    value="Asistencia">
                                <strong>Asistencia</strong>
                                <small class="text-muted d-block">
                                    Rubro obligatorio
                                </small>
                            </td>
                            <td>
                                <input type="number"
                                    name="criteria[attendance][percentage]"
                                    class="form-control text-center"
                                    value="{{ $attendance ? $attendance->percentage : 0 }}"
                                    step="0.1"
                                    min="0"
                                    required>
                            </td>
                            <td class="text-center text-muted">
                                —
                            </td>
                        </tr>
                        @foreach($criteria as $criterion)
                            @if($criterion->name !== 'Asistencia')
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
                                            ✕
                                        </button>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                        </tbody>
                </table>
                <button type="button"
                        class="btn btn-outline-secondary btn-sm"
                        id="add-row">
                    + Agregar criterio
                </button>

                <hr>

                <p>
                    <strong>Total:</strong>
                    <span id="total">{{ $total }}</span> %
                </p>

                <button class="btn btn-primary">
                    Guardar esquema
                </button>
            </form>

        </div>
    </div>
</div>
@endsection

@section('page_scripts')
<script>
let index = {{ $criteria->count() }};

document.getElementById('add-row').addEventListener('click', () => {
    const row = `
        <tr>
            <td>
                <input type="text"
                       name="criteria[${index}][name]"
                       class="form-control"
                       required
                       placeholder="Ej. Examen, Proyecto, Actividades">
            </td>
            <td>
                <input type="number"
                       name="criteria[${index}][percentage]"
                       class="form-control text-center"
                       step="0.1"
                       required>
            </td>
            <td class="text-center">
                <button type="button"
                        class="btn btn-danger btn-sm remove-row">
                    ✕
                </button>
            </td>
        </tr>
    `;
    document.getElementById('criteria-table')
        .insertAdjacentHTML('beforeend', row);

    index++;
});

document.addEventListener('click', function (e) {
    if (e.target.classList.contains('remove-row')) {
        e.target.closest('tr').remove();
    }
});
</script>
<script>
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

document.addEventListener('click', function (e) {
    if (e.target.classList.contains('remove-row')) {
        e.target.closest('tr').remove();
        updateTotal();
    }
});

// Llamada inicial
updateTotal();
</script>
@endsection
