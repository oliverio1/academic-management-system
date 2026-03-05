@extends('layouts.app')

@section('title', 'Detalle de asistencia')

@section('content')
<div class="content px-3">
    <div class="clearfix"></div>
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-sm-6">
                            <h4>
                                {{ $student->user->name }} —
                                {{ $assignment->subject->name }}
                            </h4>
                        </div>
                        <div class="col-sm-6">
                            <a href="{{ url()->previous() }}"
                            class="btn btn-outline-secondary">
                                ← Volver
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Estatus</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($records as $r)
                                @php
                                    $statusLabels = [
                                        'present'     => 'Presente',
                                        'absent'      => 'Ausente',
                                        'late'        => 'Retardo',
                                        'justified'   => 'Justificada',
                                    ];
                                @endphp
                                <tr>
                                    <td>
                                        {{ \Carbon\Carbon::parse($r->class_date)->format('d/m/Y') }}
                                        <br>
                                        <small class="text-muted">
                                            {{ $r->academicSession->start_time }} – {{ $r->academicSession->end_time }}
                                        </small>
                                    </td>
                                    @php
                                        $statusClasses = [
                                            'present'   => 'text-success',
                                            'absent'    => 'text-danger',
                                            'late'      => 'text-warning',
                                            'justified' => 'text-primary',
                                        ];
                                    @endphp

                                    <td class="attendance-cell text-center attendance-{{ $r->status }}"
                                        data-attendance-id="{{ $r->id }}"
                                        data-status="{{ $r->status }}">
                                        {{ $statusLabels[$r->status] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_css')
    <style>
        /* Transición base */
        .attendance-cell {
            transition: background-color 0.4s ease, transform 0.2s ease;
            position: relative;
        }

        /* Animación al guardar */
        .attendance-updated {
            transform: scale(1.05);
        }

        /* Colores por estatus */
        .attendance-present {
            background-color: #e6f4ea;
        }
        .attendance-absent {
            background-color: #fdecea;
        }
        .attendance-late {
            background-color: #fff4e5;
        }
        .attendance-justified {
            background-color: #e8f0fe;
        }

        /* Check visual */
        .attendance-check {
            position: absolute;
            top: 2px;
            right: 6px;
            font-size: 12px;
            color: #2e7d32;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .attendance-check.show {
            opacity: 1;
        }
    </style>
@endsection

@section('page_scripts')
<script>
const STATUS_MAP = {
    present: 'Presente',
    absent: 'Ausente',
    late: 'Retardo',
    justified: 'Justificada'
};

const STATUS_CLASS = {
    present: 'attendance-present',
    absent: 'attendance-absent',
    late: 'attendance-late',
    justified: 'attendance-justified'
};

document.querySelectorAll('.attendance-cell').forEach(cell => {

    cell.addEventListener('dblclick', () => {

        if (cell.querySelector('select')) return;

        const currentValue = cell.dataset.status;

        const select = document.createElement('select');
        select.classList.add('form-control', 'form-control-sm');

        Object.entries(STATUS_MAP).forEach(([value, label]) => {
            const opt = document.createElement('option');
            opt.value = value;
            opt.textContent = label;
            if (value === currentValue) opt.selected = true;
            select.appendChild(opt);
        });

        cell.textContent = '';
        cell.appendChild(select);
        select.focus();

        select.addEventListener('change', () => {
            saveAttendance(cell, select.value);
        });

        select.addEventListener('blur', () => {
            cell.textContent = STATUS_MAP[currentValue];
        });
    });
});

function saveAttendance(cell, status) {

    fetch("{{ route('attendance.adjustInline') }}", {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            attendance_id: cell.dataset.attendanceId,
            status: status
        })
    })
    .then(() => {

        // limpiar clases previas
        Object.values(STATUS_CLASS).forEach(c => cell.classList.remove(c));
        cell.classList.remove('attendance-updated');

        // actualizar texto y estado
        cell.textContent = STATUS_MAP[status];
        cell.dataset.status = status;

        // aplicar color + animación
        cell.classList.add(STATUS_CLASS[status]);
        cell.classList.add('attendance-updated');

        // check visual
        const check = document.createElement('span');
        check.textContent = '✔';
        check.className = 'attendance-check show';
        cell.appendChild(check);

        // limpiar animación
        setTimeout(() => {
            cell.classList.remove('attendance-updated');
            check.classList.remove('show');
        }, 200);

        // quitar check
        setTimeout(() => {
            check.remove();
        }, 900);
    });
}
</script>
@endsection
