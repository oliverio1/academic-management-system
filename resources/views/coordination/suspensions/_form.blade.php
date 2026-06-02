@php
    $selectedGroupId = old('group_id', $suspension->group_id ?? null);
    $selectedStudentId = old('student_id', $suspension->student_id ?? null);
@endphp

<div class="form-row">
    <div class="form-group col-md-4">
        <label>Grupo</label>
        <select name="group_id" id="group_id" class="form-control" required>
            <option value="">Seleccione grupo</option>
            @foreach($groups as $group)
                <option value="{{ $group->id }}" {{ (string) $selectedGroupId === (string) $group->id ? 'selected' : '' }}>
                    {{ $group->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="form-group col-md-8">
        <label>Alumno</label>
        <select name="student_id" id="student_id" class="form-control" required>
            <option value="">Seleccione alumno</option>
            @foreach($groups as $group)
                @foreach($group->students as $student)
                    <option
                        value="{{ $student->id }}"
                        data-group-id="{{ $group->id }}"
                        {{ (string) $selectedStudentId === (string) $student->id ? 'selected' : '' }}>
                        {{ $student->user->name }} ({{ $student->enrollment_number }})
                    </option>
                @endforeach
            @endforeach
        </select>
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-3">
        <label>Fecha inicio</label>
        <input type="date" name="start_date" class="form-control" value="{{ old('start_date', optional($suspension->start_date ?? null)->toDateString()) }}" required>
    </div>
    <div class="form-group col-md-3">
        <label>Fecha termino</label>
        <input type="date" name="end_date" class="form-control" value="{{ old('end_date', optional($suspension->end_date ?? null)->toDateString()) }}" required>
    </div>
    <div class="form-group col-md-6">
        <label>Motivo</label>
        <textarea name="reason" class="form-control" rows="3" required>{{ old('reason', $suspension->reason ?? '') }}</textarea>
    </div>
</div>

<script>
    (function() {
        const groupSelect = document.getElementById('group_id');
        const studentSelect = document.getElementById('student_id');
        const selectedStudent = "{{ (string) $selectedStudentId }}";

        function filterStudents() {
            const groupId = groupSelect.value;
            let hasVisibleSelected = false;

            Array.from(studentSelect.options).forEach((opt, idx) => {
                if (idx === 0) {
                    opt.hidden = false;
                    return;
                }

                const match = !groupId || opt.dataset.groupId === groupId;
                opt.hidden = !match;

                if (match && opt.value === studentSelect.value) {
                    hasVisibleSelected = true;
                }
            });

            if (!hasVisibleSelected) {
                const firstVisible = Array.from(studentSelect.options).find((opt, idx) => idx !== 0 && !opt.hidden);
                studentSelect.value = firstVisible ? firstVisible.value : '';
            }
        }

        groupSelect.addEventListener('change', filterStudents);
        filterStudents();

        if (selectedStudent) {
            studentSelect.value = selectedStudent;
            filterStudents();
        }
    })();
</script>

