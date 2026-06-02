@extends('layouts.app')

@section('title', 'Registro de actividades')

@section('content')

<div class="container-fluid">

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Registrar actividades</h5>
                <button type="button"
                   id="add-activity-btn"
                   class="btn btn-primary btn-sm">
                    + Agregar nueva actividad
                </button>
            </div>
            <label class="form-label"><strong>Periodo academico</strong></label>
            <select id="period-select" class="form-control">
                <option value="">Seleccione un periodo...</option>
                @foreach($periods as $period)
                    <option value="{{ $period->id }}">
                        {{ $period->name }}
                        ({{ $period->start_date->format('d/m/Y') }}
                        - {{ $period->end_date->format('d/m/Y') }})
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div id="sessions-container"></div>

</div>

@endsection

@section('page_scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const periodSelect = document.getElementById('period-select');
    const container = document.getElementById('sessions-container');
    const addActivityBtn = document.getElementById('add-activity-btn');
    let criteriaOptionsHtml = '';

    const loadSessions = async () => {
        const periodId = periodSelect.value;
        container.innerHTML = '';

        if (!periodId) {
            return;
        }

        try {
            const res = await fetch(`/assignments/{{ $assignment->id }}/activities/period/${periodId}`);
            const html = await res.text();
            container.innerHTML = html;
            const criterionSelect = container.querySelector('select[data-field="evaluation_criterion_id"]');
            criteriaOptionsHtml = criterionSelect ? criterionSelect.innerHTML : '';
        } catch (_) {
            alert('Error al cargar las actividades del periodo');
        }
    };

    periodSelect.addEventListener('change', () => {
        loadSessions();
    });

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-edit');
        if (!btn) return;

        const row = btn.closest('tr');
        const isEditing = btn.dataset.editing === '1';
        const periodId = periodSelect.value;

        if (!periodId) {
            alert('Selecciona un periodo academico.');
            return;
        }

        if (!isEditing) {
            toggleRow(row, true);
            btn.textContent = 'Guardar';
            btn.classList.replace('btn-outline-primary', 'btn-success');
            btn.dataset.editing = '1';
            return;
        }

        const payload = { academic_period_id: periodId };
        const activityId = row.dataset.activityId ? parseInt(row.dataset.activityId, 10) : null;
        if (activityId) {
            payload.activity_id = activityId;
        }

        const sessionDateInput = row.querySelector('[data-field="session_date"]');
        payload.session_date = sessionDateInput
            ? (sessionDateInput.value || row.dataset.date)
            : row.dataset.date;

        row.querySelectorAll('.cell-edit').forEach(input => {
            if (input.dataset.field === 'session_date') return;
            payload[input.dataset.field] = input.value;
        });

        if (!payload.session_date || !payload.title || !payload.evaluation_criterion_id) {
            alert('Debes capturar fecha, nombre y rubro de evaluacion.');
            return;
        }

        const response = await fetch("{{ route('activities.store', $assignment) }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (!data.ok) {
            alert(data.message ?? 'Error al guardar la actividad');
            return;
        }

        updateDisplay(row, payload);
        if (data.activity_id) {
            row.dataset.activityId = data.activity_id;
        }
        toggleRow(row, false);

        btn.textContent = 'Editar';
        btn.classList.replace('btn-success', 'btn-outline-primary');
        btn.dataset.editing = '0';
    });

    addActivityBtn.addEventListener('click', async () => {
        if (!periodSelect.value) {
            alert('Selecciona un periodo academico.');
            return;
        }

        if (!container.querySelector('table')) {
            await loadSessions();
        }

        if (!criteriaOptionsHtml) {
            alert('Primero configura al menos un rubro de evaluacion.');
            return;
        }

        const tbody = container.querySelector('tbody');
        if (!tbody) {
            alert('No se pudo preparar la tabla para agregar actividad.');
            return;
        }

        const manualKey = `manual_${Date.now()}`;
        const rowHtml = `
            <tr data-date="" data-manual="${manualKey}">
                <td>
                    <span class="cell-display d-none">-</span>
                    <input type="date"
                        class="form-control form-control-sm cell-edit"
                        data-field="session_date">
                </td>
                <td>
                    <span class="cell-display d-none">-</span>
                    <input type="text"
                        class="form-control form-control-sm cell-edit"
                        data-field="title"
                        value="">
                </td>
                <td>
                    <span class="cell-display d-none">-</span>
                    <select class="form-control form-control-sm cell-edit"
                            data-field="evaluation_criterion_id">
                        ${criteriaOptionsHtml}
                    </select>
                </td>
                <td>
                    <span class="cell-display d-none">10</span>
                    <input type="number"
                        class="form-control form-control-sm cell-edit"
                        data-field="max_score"
                        value="10">
                </td>
                <td>
                    <span class="cell-display d-none">-</span>
                    <textarea class="form-control form-control-sm cell-edit"
                            data-field="description"></textarea>
                </td>
                <td>
                    <button class="btn btn-sm btn-success btn-edit" data-editing="1">Guardar</button>
                </td>
            </tr>
        `;

        tbody.insertAdjacentHTML('afterbegin', rowHtml);

        const newRow = tbody.querySelector(`tr[data-manual="${manualKey}"]`);
        if (newRow) {
            newRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            const titleInput = newRow.querySelector('input[data-field="title"]');
            if (titleInput) titleInput.focus();
        }
    });

    if (periodSelect.value) {
        loadSessions();
    }
});

function toggleRow(row, editing) {
    row.querySelectorAll('.cell-display').forEach(el =>
        el.classList.toggle('d-none', editing)
    );
    row.querySelectorAll('.cell-edit').forEach(el =>
        el.classList.toggle('d-none', !editing)
    );
}

function updateDisplay(row, data) {
    const dateInput = row.querySelector('[data-field="session_date"]');
    if (dateInput) {
        const dateSpan = dateInput.previousElementSibling;
        const parts = (data.session_date || '').split('-');
        dateSpan.textContent = parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : '-';
        row.dataset.date = data.session_date || '';
    }

    row.querySelector('[data-field="title"]').previousElementSibling.textContent = data.title;
    row.querySelector('[data-field="max_score"]').previousElementSibling.textContent = data.max_score ?? '10';
    row.querySelector('[data-field="description"]').previousElementSibling.textContent = data.description || '-';

    const criterionSelect = row.querySelector('[data-field="evaluation_criterion_id"]');
    criterionSelect.previousElementSibling.textContent = criterionSelect.options[criterionSelect.selectedIndex].text;
}
</script>
@endsection
