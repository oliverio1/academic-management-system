@extends('layouts.app')

@section('title', 'Configuración de evaluación')

@section('content')

@if(session('info'))
    <div class="alert alert-primary">
        <strong>{{ session('info') }}</strong>
    </div>
@endif

<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">

                <div class="card-header">
                    <h3 class="mb-0">Configuración de evaluación</h3>
                </div>

                <form action="{{ route('teacher.evaluation.update', $teachingAssignment) }}" method="POST">
                    @csrf
                    @method('PATCH')

                    <div class="card-body">

                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Criterio</th>
                                    <th style="width: 180px;">Porcentaje</th>
                                    <th style="width: 80px;"></th>
                                </tr>
                            </thead>
                            <tbody id="criteria-table">
                                @foreach($teachingAssignment->evaluationCriteria as $criterion)
                                    <tr>
                                        <td>
                                            <input type="text"
                                                name="criteria[{{ $criterion->id }}][name]"
                                                value="{{ $criterion->name }}"
                                                class="form-control"
                                                required>
                                        </td>

                                        <td>
                                            <input type="number"
                                                name="criteria[{{ $criterion->id }}][percentage]"
                                                value="{{ $criterion->percentage }}"
                                                class="form-control text-center"
                                                step="0.1"
                                                required>
                                        </td>

                                        <td class="text-center">
                                            @if($criterion->activities->count() === 0)
                                                <button type="button"
                                                        class="btn btn-danger btn-sm remove-row">
                                                    ✕
                                                </button>
                                            @else
                                                <span class="text-muted" title="Tiene actividades">
                                                    🔒
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <button type="button"
                                class="btn btn-outline-success btn-sm"
                                id="add-row">
                            + Agregar criterio
                        </button>

                        <hr>

                        <div class="fw-bold">
                            Total: <span id="total">0.00</span> %
                        </div>

                    </div>

                    <div class="card-footer">
                        <button class="btn btn-primary" type="submit">
                            Guardar
                        </button>

                        <a href="{{ route('assignments.show', $teachingAssignment) }}"
                           class="btn btn-secondary">
                            Cancelar
                        </a>
                    </div>

                </form>

            </div>
        </div>
    </div>
</div>

@endsection

@section('page_scripts')
<script>
    let newIndex = -1; // 🔑 claves temporales

    function updateTotal() {
        let total = 0;

        document.querySelectorAll('input[name$="[percentage]"]').forEach(input => {
            const value = parseFloat(input.value);
            if (!isNaN(value)) total += value;
        });

        document.getElementById('total').textContent = total.toFixed(2);
    }

    document.getElementById('add-row').addEventListener('click', () => {
        const row = `
            <tr>
                <td>
                    <input type="text"
                           name="criteria[${newIndex}][name]"
                           class="form-control"
                           required>
                </td>
                <td>
                    <input type="number"
                           name="criteria[${newIndex}][percentage]"
                           class="form-control text-center"
                           step="0.1"
                           min="0"
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

        document
            .getElementById('criteria-table')
            .insertAdjacentHTML('beforeend', row);

        newIndex--; // siguiente key negativa
    });

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

    updateTotal();
</script>
@endsection
