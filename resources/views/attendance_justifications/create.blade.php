@extends('layouts.app')

@section('title', 'Justificantes')

@section('content')
    @if(session('success'))
        <div class="alert alert-success" role="alert">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            Revisa los datos capturados antes de guardar.
        </div>
    @endif

    <div class="content px-3">
        <div class="clearfix"></div>
        <div class="row">
            <div class="col-md-12 mt-3">
                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0">Alta de justificantes</h3>
                    </div>

                    <div class="card-body">
                        <form method="POST"
                              action="{{ route('attendance_justifications.store') }}"
                              enctype="multipart/form-data">
                            @csrf

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Grupo</label>
                                    <select id="group_id" name="group_id" class="form-control" required>
                                        <option value="">Seleccione un grupo</option>
                                        @foreach($groups as $group)
                                            <option value="{{ $group->id }}" {{ (string) old('group_id') === (string) $group->id ? 'selected' : '' }}>
                                                {{ $group->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Alumno</label>
                                    <select id="student_id" name="student_id" class="form-control" required disabled>
                                        <option value="">Seleccione un alumno</option>
                                        @foreach($groups as $group)
                                            @foreach($group->students as $student)
                                                <option value="{{ $student->id }}"
                                                        data-group-id="{{ $group->id }}"
                                                        {{ (string) old('student_id') === (string) $student->id ? 'selected' : '' }}>
                                                    {{ $student->user->name }} | {{ $student->enrollment_number }}
                                                </option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Desde</label>
                                    <input type="date" name="from_date" class="form-control" value="{{ old('from_date') }}" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Hasta</label>
                                    <input type="date" name="to_date" class="form-control" value="{{ old('to_date') }}" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Motivo</label>
                                <textarea name="reason" class="form-control" rows="3" required>{{ old('reason') }}</textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Receta o evidencia (opcional)</label>
                                <input type="file" name="document" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">Formatos permitidos: PDF, JPG, JPEG, PNG. Maximo 4 MB.</small>
                            </div>

                            <button class="btn btn-primary">
                                Emitir justificante
                            </button>
                        </form>

                        <hr class="my-4">

                        <h5 class="mb-3">Justificantes recientes</h5>

                        @if($justifications->isEmpty())
                            <div class="alert alert-info mb-0">
                                Aun no hay justificantes registrados.
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Alumno</th>
                                            <th>Grupo</th>
                                            <th>Desde</th>
                                            <th>Hasta</th>
                                            <th>Motivo</th>
                                            <th>Evidencia</th>
                                            <th>Emitido por</th>
                                            <th>Fecha de emision</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($justifications as $justification)
                                            <tr>
                                                <td>{{ optional($justification->student->user)->name }}</td>
                                                <td>{{ optional($justification->student->group)->name ?? '-' }}</td>
                                                <td>{{ optional($justification->from_date)->format('d/m/Y') }}</td>
                                                <td>{{ optional($justification->to_date)->format('d/m/Y') }}</td>
                                                <td>{{ $justification->reason }}</td>
                                                <td>
                                                    @if($justification->document_path)
                                                        <a href="{{ asset('storage/'.$justification->document_path) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                                                            Ver archivo
                                                        </a>
                                                    @else
                                                        <span class="text-muted">Sin archivo</span>
                                                    @endif
                                                </td>
                                                <td>{{ optional($justification->issuer)->name ?? '-' }}</td>
                                                <td>{{ optional($justification->issued_at)->format('d/m/Y H:i') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('page_scripts')
<script>
    (function () {
        const groupSelect = document.getElementById('group_id');
        const studentSelect = document.getElementById('student_id');
        const oldStudentId = '{{ old('student_id') }}';

        function filterStudents() {
            const groupId = groupSelect.value;
            const options = Array.from(studentSelect.options);

            options.forEach((option, index) => {
                if (index === 0) {
                    option.hidden = false;
                    return;
                }

                option.hidden = option.dataset.groupId !== groupId;
            });

            if (!groupId) {
                studentSelect.value = '';
                studentSelect.disabled = true;
                return;
            }

            studentSelect.disabled = false;

            const selectedOption = Array.from(studentSelect.options).find(
                (opt) => opt.value === studentSelect.value && !opt.hidden
            );

            if (!selectedOption) {
                studentSelect.value = '';
            }

            if (oldStudentId) {
                const oldOption = Array.from(studentSelect.options).find(
                    (opt) => opt.value === oldStudentId && !opt.hidden
                );

                if (oldOption) {
                    studentSelect.value = oldStudentId;
                }
            }
        }

        groupSelect.addEventListener('change', filterStudents);
        filterStudents();
    })();
</script>
@endsection
