@extends('layouts.app')

@section('title', 'Alumnos del ciclo activo')

@section('content')
<div class="content px-3">
    @if(session('success'))
        <div class="alert alert-success mt-3">{{ session('success') }}</div>
    @endif
    @if(session('info'))
        <div class="alert alert-info mt-3">{{ session('info') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger mt-3">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Alumnos del ciclo activo</h4>
                    <a href="{{ route('students.create', ['school_cycle_id' => $activeCycle?->id, 'source' => 'active-cycle']) }}" class="btn btn-primary">
                        Nuevo alumno
                    </a>
                </div>
                <div class="card-body">
                    @if(!$activeCycle)
                        <div class="alert alert-warning mb-0">
                            No hay un ciclo activo configurado.
                        </div>
                    @else
                        <div class="mb-3">
                            <span class="badge badge-primary">Ciclo activo</span>
                            <strong>{{ $activeCycle->name }}</strong> ({{ $activeCycle->code }})
                        </div>

                        <form method="GET" action="{{ route('coordination.students.active-cycle') }}" class="mb-3">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label for="group_id">Grupo</label>
                                    <select name="group_id" id="group_id" class="form-control">
                                        <option value="">Todos</option>
                                        @foreach($groups as $group)
                                            <option value="{{ $group->id }}" {{ (int) $selectedGroupId === (int) $group->id ? 'selected' : '' }}>
                                                {{ $group->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label for="status">Estatus</label>
                                    <select name="status" id="status" class="form-control">
                                        <option value="all" {{ $status === 'all' ? 'selected' : '' }}>Todos</option>
                                        <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Activos</option>
                                        <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Baja</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-outline-primary w-100">Filtrar</button>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table data-datatable="true" class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>MatrÃ­cula</th>
                                        <th>Alumno</th>
                                        <th>Grupo</th>
                                        <th>Estatus</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($students as $student)
                                        <tr>
                                            <td>{{ $student->enrollment_number ?: '---' }}</td>
                                            <td>{{ $student->user->name ?? 'N/D' }}</td>
                                            <td>{{ $student->group->name ?? '---' }}</td>
                                            <td>
                                                @if($student->is_active)
                                                    <span class="badge badge-success">Activo</span>
                                                @else
                                                    <span class="badge badge-secondary">Baja</span>
                                                @endif
                                            </td>
                                            <td class="d-flex flex-wrap" style="gap: .25rem;">
                                                <a href="{{ route('students.show', $student) }}" class="btn btn-primary btn-sm">
                                                    Ver
                                                </a>
                                                <a href="{{ route('students.edit', $student) }}" class="btn btn-warning btn-sm">
                                                    Editar
                                                </a>

                                                @if($student->is_active)
                                                    <form action="{{ route('students.deactivate', $student) }}" method="POST" class="js-deactivate-student-form">
                                                        @csrf
                                                        <input type="hidden" name="deactivation_reason" value="">
                                                        <button type="submit" class="btn btn-danger btn-sm">Dar de baja</button>
                                                    </form>
                                                @else
                                                    <form action="{{ route('students.activate', $student) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="btn btn-success btn-sm">Activar</button>
                                                    </form>
                                                @endif

                                                <button
                                                    class="btn btn-info btn-sm btn-change-group"
                                                    data-toggle="modal"
                                                    data-target="#changeGroupModal"
                                                    data-student-id="{{ $student->id }}"
                                                    data-student-name="{{ $student->user->name }}"
                                                    data-group-name="{{ $student->group->name ?? '' }}"
                                                >
                                                    Cambiar grupo
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">
                                                No hay alumnos para los filtros seleccionados.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif
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
                    <h5 class="modal-title">Cambio de grupo</h5>
                    <button type="button" class="btn-close" data-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1"><strong>Alumno:</strong> <span id="modalStudentName"></span></p>
                    <p class="mb-3"><strong>Grupo actual:</strong> <span id="modalCurrentGroup"></span></p>

                    <div class="form-group">
                        <label for="group_id">Nuevo grupo</label>
                        <select name="group_id" class="form-control" required>
                            <option value="">Seleccione un grupo</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="start_date">Fecha efectiva del cambio</label>
                        <input type="date" name="start_date" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="reason">Motivo</label>
                        <input type="text" name="reason" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-info">Confirmar cambio</button>
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
            const studentIdInput = document.getElementById('modalStudentId');
            const studentNameSpan = document.getElementById('modalStudentName');
            const groupNameSpan = document.getElementById('modalCurrentGroup');

            if (studentIdInput) studentIdInput.value = this.dataset.studentId;
            if (studentNameSpan) studentNameSpan.textContent = this.dataset.studentName || '';
            if (groupNameSpan) groupNameSpan.textContent = this.dataset.groupName || '';
        });
    });

    document.querySelectorAll('.js-deactivate-student-form').forEach(form => {
        form.addEventListener('submit', function (event) {
            const reason = window.prompt('Motivo de baja del alumno:');
            if (reason === null) {
                event.preventDefault();
                return;
            }

            const trimmed = reason.trim();
            if (!trimmed) {
                event.preventDefault();
                window.alert('Debes capturar un motivo para dar de baja.');
                return;
            }

            const input = form.querySelector('input[name=\"deactivation_reason\"]');
            if (input) {
                input.value = trimmed;
            }
        });
    });
});
</script>
@endsection

