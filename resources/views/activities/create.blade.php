@extends('layouts.app')

@section('title', 'Registro de actividades')

@section('content')

<div class="container-fluid">

    {{-- ===============================
        SELECCIÓN DE PERIODO
       =============================== --}}
    <div class="card mb-3">
        <div class="card-body">
            <label class="form-label"><strong>Periodo académico</strong></label>
            <select id="period-select" class="form-control">
                <option value="">Seleccione un periodo…</option>
                @foreach($periods as $period)
                    <option value="{{ $period->id }}">
                        {{ $period->name }}
                        ({{ $period->start_date->format('d/m/Y') }}
                        – {{ $period->end_date->format('d/m/Y') }})
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- ===============================
        CONTENEDOR DE ACTIVIDADES
       =============================== --}}
    <div id="sessions-container"></div>

</div>

@endsection

@section('page_scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {

    /* ===============================
       1️⃣ CARGAR ACTIVIDADES POR PERIODO
       =============================== */

    const periodSelect = document.getElementById('period-select');
    const container = document.getElementById('sessions-container');

    periodSelect.addEventListener('change', () => {
        const periodId = periodSelect.value;
        container.innerHTML = '';

        if (!periodId) return;

        fetch(`/assignments/{{ $assignment->id }}/activities/period/${periodId}`)
            .then(res => res.text())
            .then(html => {
                container.innerHTML = html;
            })
            .catch(() => {
                alert('Error al cargar las actividades del periodo');
            });
    });

    /* ===============================
       2️⃣ EDITAR / GUARDAR ACTIVIDAD
       =============================== */

    document.addEventListener('click', async (e) => {

        const btn = e.target.closest('.btn-edit');
        if (!btn) return;

        const row = btn.closest('tr');
        const isEditing = btn.dataset.editing === '1';
        const periodId = periodSelect.value;

        if (!periodId) {
            alert('Selecciona un periodo académico.');
            return;
        }

        /* ===============================
           ENTRAR EN MODO EDICIÓN
           =============================== */
        if (!isEditing) {
            toggleRow(row, true);
            btn.textContent = 'Guardar';
            btn.classList.replace('btn-outline-primary', 'btn-success');
            btn.dataset.editing = '1';
            return;
        }

        /* ===============================
           GUARDAR
           =============================== */
        const payload = {
            session_date: row.dataset.date,
            academic_period_id: periodId
        };

        row.querySelectorAll('.cell-edit').forEach(input => {
            payload[input.dataset.field] = input.value;
        });

        if (
            !payload.title ||
            !payload.evaluation_criterion_id ||
            !payload.evaluation_mode
        ) {
            alert('Debes capturar el nombre, el criterio y el modo de evaluación.');
            return;
        }

        const response = await fetch(
            "{{ route('activities.store', $assignment) }}",
            {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            }
        );

        const data = await response.json();

        if (!data.ok) {
            alert(data.message ?? 'Error al guardar la actividad');
            return;
        }

        updateDisplay(row, payload);
        toggleRow(row, false);

        btn.textContent = 'Editar';
        btn.classList.replace('btn-success', 'btn-outline-primary');
        btn.dataset.editing = '0';
    });

});

/* ===============================
   FUNCIONES AUXILIARES
   =============================== */

function toggleRow(row, editing) {
    row.querySelectorAll('.cell-display').forEach(el =>
        el.classList.toggle('d-none', editing)
    );
    row.querySelectorAll('.cell-edit').forEach(el =>
        el.classList.toggle('d-none', !editing)
    );
}

function updateDisplay(row, data) {

    row.querySelector('[data-field="title"]')
        .previousElementSibling.textContent = data.title;

    row.querySelector('[data-field="max_score"]')
        .previousElementSibling.textContent = data.max_score ?? '10';

    row.querySelector('[data-field="description"]')
        .previousElementSibling.textContent =
            data.description || '—';

    const criterionSelect =
        row.querySelector('[data-field="evaluation_criterion_id"]');

    criterionSelect.previousElementSibling.textContent =
        criterionSelect.options[criterionSelect.selectedIndex].text;

    const modeMap = {
        individual: 'Individual',
        team: 'Por equipo'
    };

    row.querySelector('[data-field="evaluation_mode"]')
        .previousElementSibling.textContent =
            modeMap[data.evaluation_mode];
}
</script>
@endsection
