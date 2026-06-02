<div class="card mb-3">
    <div class="card-header"><strong>Asignación masiva</strong></div>
    <div class="card-body">
        <form method="POST" action="{{ route('coordination.tutor-assignments.bulk') }}">
            @csrf
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="guardian_user_id">Tutor</label>
                    <select name="guardian_user_id" id="guardian_user_id" class="form-control" required>
                        <option value="">Seleccione tutor</option>
                        @foreach($tutors as $tutor)
                            <option value="{{ $tutor->id }}">{{ $tutor->name }} ({{ $tutor->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-8 mb-3">
                    <label for="student_ids">Alumnos</label>
                    <select name="student_ids[]" id="student_ids" class="form-control" multiple size="8" required>
                        @foreach($students as $student)
                            <option value="{{ $student->id }}">{{ $student->user->name }} - {{ $student->group->name ?? '-' }} - {{ $student->enrollment_number }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">Mantén Ctrl (o Cmd) para seleccionar varios alumnos.</small>
                </div>
            </div>
            <button class="btn btn-primary">Asignar tutor a seleccionados</button>
        </form>
    </div>
</div>

<div class="card"><div class="card-body table-responsive p-3"><table data-datatable="true" class="table table-hover mb-0"><thead><tr><th>Alumno</th><th>Grupo</th><th>Matrícula</th><th>Tutor asignado</th><th>Acción</th></tr></thead><tbody>@forelse($students as $student)<tr><td>{{ $student->user->name }}</td><td>{{ $student->group->name ?? '-' }}</td><td>{{ $student->enrollment_number }}</td><td><form method="POST" action="{{ route('coordination.tutor-assignments.update', $student) }}" class="form-inline">@csrf @method('PATCH')<select name="guardian_user_id" class="form-control form-control-sm mr-2"><option value="">Sin tutor</option>@foreach($tutors as $tutor)<option value="{{ $tutor->id }}" {{ (string) $student->guardian_user_id === (string) $tutor->id ? 'selected' : '' }}>{{ $tutor->name }} ({{ $tutor->email }})</option>@endforeach</select><button class="btn btn-sm btn-primary">Guardar</button></form></td><td>@if($student->guardian)<span class="badge badge-success">Asignado</span>@else<span class="badge badge-secondary">Sin asignar</span>@endif</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">No hay alumnos registrados.</td></tr>@endforelse</tbody></table></div></div>

