@extends('layouts.app')

@section('title', 'Estudiantes')

@section('content')
    @if(session('info'))
        <div class="alert alert-primary" role="alert">
            <strong>{{ session('info') }}</strong>
        </div>
    @endif
    <div class="content px-3">
        <div class="clearfix"></div>
        <div class="row">
            <div class="col-md-12 mt-3">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-sm-6">
                                <h4>Estudiantes</h4>
                            </div>
                            <div class="col-sm-6">
                                <a class="btn btn-primary float-right"href="{{ route('students.create') }}"> Nuevo estudiante</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <h3 class="text-center">Listado de estudiantes</h3>
                        <hr>
                        <table id="students" class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Matrícula</th>
                                    <th>Nombre</th>
                                    <th>Grupo</th>
                                    <th>Estatus</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($students as $student)
                                    <tr>
                                        <td>{{ $student->id }}</td>
                                        <td>{{ $student->enrollment_number }}</td>
                                        <td>{{ $student->user->name }}</td>
                                        <td>{{ $student->group->name }}</td>
                                        <td>{{ $student->is_active ? 'Activo' : 'Baja' }}</td>
                                        <td>
                                            @if($student->is_active)
                                                <form action="{{ route('students.deactivate', $student->id) }}"method="POST" style="display:inline">
                                                    @csrf
                                                    <a href="{{ route('students.show', $student->id) }}"class="btn btn-primary btn-sm"><i class="far fa-eye"></i></a>
                                                    <a href="{{ route('students.edit', $student->id) }}"class="btn btn-warning btn-sm"><i class="far fa-edit"></i></a>
                                                    <button class="btn btn-danger btn-sm" type="submit"><i class="fa fa-times"></i></button>
                                                </form>
                                                <button
                                                    class="btn btn-warning btn-sm btn-change-group"
                                                    data-toggle="modal"
                                                    data-target="#changeGroupModal"
                                                    data-student-id="{{ $student->id }}"
                                                    data-student-name="{{ $student->user->name }}"
                                                    data-group-name="{{ $student->group->name }}"
                                                >
                                                    Cambiar de grupo
                                                </button>
                                            @else
                                                <form action="{{ route('students.activate', $student->id) }}"method="POST" style="display:inline">
                                                    @csrf
                                                    <a href="{{ route('students.show', $student->id) }}"class="btn btn-primary btn-sm"><i class="far fa-eye"></i></a>
                                                    <a href="{{ route('students.edit', $student->id) }}"class="btn btn-warning btn-sm"><i class="far fa-edit"></i></a>
                                                    <button class="btn btn-success btn-sm" type="submit"><i class="fa fa-check"></i></button>
                                                </form>
                                                <button
                                                    class="btn btn-warning btn-sm btn-change-group"
                                                    data-toggle="modal"
                                                    data-target="#changeGroupModal"
                                                    data-student-id="{{ $student->id }}"
                                                    data-student-name="{{ $student->user->name }}"
                                                    data-group-name="{{ $student->group->name }}"
                                                >
                                                    Cambiar de grupo
                                                </button>
                                            @endif
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

    <div class="modal fade" id="changeGroupModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form method="POST" action="{{ route('students.change-group') }}">
                @csrf

                <input type="hidden" name="student_id" id="modalStudentId">

                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            Cambio de grupo
                        </h5>
                        <button type="button" class="btn-close" data-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">

                        {{-- Información del alumno --}}
                        <p class="mb-1">
                            <strong>Alumno:</strong>
                            <span id="modalStudentName"></span>
                        </p>

                        <p class="mb-3">
                            <strong>Grupo actual:</strong>
                            <span id="modalCurrentGroup"></span>
                        </p>

                        <div class="mb-2" id="modalAcademicBadges"></div>

                        {{-- Historial reciente --}}
                        <div class="mb-3">
                            <strong>Historial reciente</strong>

                            <ul class="mt-2 mb-0" id="modalGroupHistory">
                            </ul>
                        </div>

                        {{-- Advertencia académica --}}
                        <div class="alert alert-warning">
                            <strong>Aviso académico</strong><br>
                            El cambio de grupo:
                            <ul class="mb-0">
                                <li>se aplicará a partir de la fecha indicada</li>
                                <li>no borra calificaciones</li>
                                <li>afecta el cálculo final de la boleta</li>
                            </ul>
                        </div>

                        <hr>

                        {{-- Formulario --}}
                        <div class="form-group">
                            <label for="group_id">Nuevo grupo</label>
                            <select name="group_id"
                                    class="form-control"
                                    required>
                                <option value="">Seleccione un grupo</option>
                                @foreach($groups as $group)
                                    <option value="{{ $group->id }}">
                                        {{ $group->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="start_date">Fecha efectiva del cambio</label>
                            <input type="date"
                                name="start_date"
                                class="form-control"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="reason">Motivo</label>
                            <input type="text"
                                name="reason"
                                class="form-control"
                                placeholder="Ej. Reubicación académica"
                                required>
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button type="button"
                                class="btn btn-secondary"
                                data-dismiss="modal">
                            Cancelar
                        </button>

                        <button type="submit"
                                class="btn btn-warning">
                            Confirmar cambio de grupo
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

@endsection

@section('page_scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('.btn-change-group').forEach(btn => {

        btn.addEventListener('click', function () {

            const studentId = this.dataset.studentId;
            // =========================
            // 1️⃣ Rellenar datos básicos
            // =========================
            const studentIdInput = document.getElementById('modalStudentId');
            const studentNameSpan = document.getElementById('modalStudentName');
            const groupNameSpan   = document.getElementById('modalCurrentGroup');

            if (studentIdInput) studentIdInput.value = studentId;
            if (studentNameSpan) studentNameSpan.textContent = this.dataset.studentName;
            if (groupNameSpan) groupNameSpan.textContent = this.dataset.groupName;

            // =========================
            // 2️⃣ Historial de grupos
            // =========================
            const historyList = document.getElementById('modalGroupHistory');
            const badgeContainer = document.getElementById('modalAcademicBadges');

            historyList.innerHTML = `<li class="text-muted">Cargando historial...</li>`;
            badgeContainer.innerHTML = '';

            fetch(`/students/${studentId}/group-history`)
                .then(res => {
                    if (!res.ok) throw new Error('Error group-history');
                    return res.json();
                })
                .then(data => {

                    // Badges
                    if (data.has_level_change) {
                        badgeContainer.innerHTML += `
                            <span class="badge bg-danger me-1">
                                Cambio de nivel
                            </span>
                        `;
                    }

                    if (data.changes_count > 1) {
                        badgeContainer.innerHTML += `
                            <span class="badge bg-warning text-dark">
                                ${data.changes_count} cambios de grupo
                            </span>
                        `;
                    }

                    historyList.innerHTML = '';

                    if (data.histories.length === 0) {
                        historyList.innerHTML = `
                            <li class="text-muted">
                                No hay historial de cambios de grupo.
                            </li>
                        `;
                        return;
                    }

                    data.histories.forEach(item => {
                        historyList.innerHTML += `
                            <li class="mb-2">
                                <strong>${item.group}</strong> (${item.level})<br>
                                ${item.from} → ${item.to}<br>
                                <small class="text-muted">${item.reason}</small>
                            </li>
                        `;
                    });
                })
                .catch(err => {
                    historyList.innerHTML = `
                        <li class="text-danger">
                            No se pudo cargar el historial.
                        </li>
                    `;
                    console.error(err);
                });

            // =========================
            // 3️⃣ Impacto académico
            // =========================
            fetch(`/students/${studentId}/group-impact`, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}`);
                }
                return res.json();
            })
            .then(data => {
                if (!data.has_data) {
                    console.log('Sin impacto académico aún');
                    return;
                }

                console.log('Asistencia:', data.attendance);
                console.log('Final:', data.final);
            })
            .catch(err => {
                console.error('Impacto académico:', err);
            });
        });
    });
});
</script>

@endsection