@extends('layouts.app')

@section('title', $readOnly ? 'Ver reporte' : ($report ? 'Editar reporte' : 'Nuevo reporte'))

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        {{ $readOnly ? 'Ver reporte de alumno' : ($report ? 'Editar reporte de alumno para coordinación' : 'Reporte de alumno para coordinación') }}
                    </h4>
                </div>
                <div class="card-body">
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ $report ? route('teacher.reports.update', $report) : route('teacher.reports.store') }}">
                        @csrf
                        @if($report)
                            @method('PUT')
                        @endif

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="group_id">Grupo</label>
                                <select name="group_id" id="group_id" class="form-control" required {{ $readOnly ? 'disabled' : '' }}>
                                    <option value="">Seleccione grupo</option>
                                    @foreach($groups as $group)
                                        <option value="{{ $group->id }}" {{ (string) old('group_id', $report?->group_id) === (string) $group->id ? 'selected' : '' }}>
                                            {{ $group->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="student_id">Alumno</label>
                                <select name="student_id" id="student_id" class="form-control" required {{ $readOnly ? 'disabled' : '' }}>
                                    <option value="">Seleccione alumno</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="report_type">Tipo de reporte</label>
                                <select name="report_type" id="report_type" class="form-control" required {{ $readOnly ? 'disabled' : '' }}>
                                    <option value="">Seleccione tipo</option>
                                    @foreach($typeOptions as $value => $label)
                                        <option value="{{ $value }}" {{ old('report_type', $report?->report_type) === $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="severity">Gravedad</label>
                                <select name="severity" id="severity" class="form-control" required {{ $readOnly ? 'disabled' : '' }}>
                                    <option value="">Seleccione gravedad</option>
                                    <option value="1" {{ old('severity', $report?->severity) == '1' ? 'selected' : '' }}>1 - Baja</option>
                                    <option value="2" {{ old('severity', $report?->severity) == '2' ? 'selected' : '' }}>2 - Media</option>
                                    <option value="3" {{ old('severity', $report?->severity) == '3' ? 'selected' : '' }}>3 - Alta</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="reason">Motivo de reporte</label>
                            <textarea name="reason" id="reason" rows="5" class="form-control" required {{ $readOnly ? 'readonly' : '' }}>{{ old('reason', $report?->reason) }}</textarea>
                        </div>

                        @if(! $readOnly)
                            <button class="btn btn-primary">{{ $report ? 'Guardar cambios' : 'Enviar reporte' }}</button>
                        @endif
                        <a href="{{ route('teacher.reports.index') }}" class="btn btn-secondary">Cancelar</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_scripts')
<script>
    (function () {
        const studentsMap = @json($studentsMap);
        const oldStudentId = @json(old('student_id', $report?->student_id));
        const groupSelect = document.getElementById('group_id');
        const studentSelect = document.getElementById('student_id');

        function refreshStudents() {
            const groupId = groupSelect.value;
            const students = studentsMap[groupId] || [];
            studentSelect.innerHTML = '<option value="">Seleccione alumno</option>';

            students.forEach((student) => {
                const option = document.createElement('option');
                option.value = student.id;
                option.textContent = student.name;
                if (String(oldStudentId) === String(student.id)) {
                    option.selected = true;
                }
                studentSelect.appendChild(option);
            });
        }

        if (!@json($readOnly)) {
            groupSelect.addEventListener('change', refreshStudents);
        }
        refreshStudents();
    })();
</script>
@endsection
