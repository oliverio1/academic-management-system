@php
    $assignment = $schedule->assignment ?? null;
    $selectedCycle = old('school_cycle_id', $schedule->school_cycle_id ?? $selectedCycle->id ?? null);
    $selectedGroup = old('group_id', $assignment->group_id ?? null);
    $selectedSubject = old('subject_id', $assignment->subject_id ?? null);
    $selectedTeacher = old('teacher_id', $assignment->teacher_id ?? null);
    $selectedSection = (int) old('section_number', $schedule->section_number ?? $assignment->section_number ?? 1);
    $selectedDay = old('day_of_week', $schedule->day_of_week ?? null);
@endphp

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="school_cycle_id" class="form-label">Ciclo escolar</label>
        <select name="school_cycle_id" id="school_cycle_id" class="form-control" required>
            <option value="">Seleccione ciclo</option>
            @foreach($cycles as $cycle)
                <option value="{{ $cycle->id }}" {{ (string) $selectedCycle === (string) $cycle->id ? 'selected' : '' }}>
                    {{ $cycle->name }} ({{ $cycle->code }})
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4 mb-3">
        <label for="group_id" class="form-label">Grupo</label>
        <select name="group_id" id="group_id" class="form-control" required>
            <option value="">Seleccione grupo</option>
            @foreach($groups as $group)
                <option value="{{ $group->id }}" {{ (string) $selectedGroup === (string) $group->id ? 'selected' : '' }}>
                    {{ $group->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4 mb-3">
        <label for="subject_id" class="form-label">Materia</label>
        <select name="subject_id" id="subject_id" class="form-control" required>
            <option value="">Seleccione materia</option>
            @foreach($subjects as $subject)
                <option value="{{ $subject->id }}" {{ (string) $selectedSubject === (string) $subject->id ? 'selected' : '' }}>
                    {{ $subject->name }}
                </option>
            @endforeach
        </select>
        <small class="text-muted">Debe ser una materia previamente asignada al grupo.</small>
    </div>

    <div class="col-md-4 mb-3">
        <label for="teacher_id" class="form-label">Profesor</label>
        <select name="teacher_id" id="teacher_id" class="form-control" required>
            <option value="">Seleccione profesor</option>
            @foreach($teachers as $teacher)
                <option value="{{ $teacher->id }}" {{ (string) $selectedTeacher === (string) $teacher->id ? 'selected' : '' }}>
                    {{ $teacher->user->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4 mb-3">
        <label for="section_number" class="form-label">Sección</label>
        <select name="section_number" id="section_number" class="form-control" required>
            @for($i = 1; $i <= 3; $i++)
                <option value="{{ $i }}" {{ $selectedSection === $i ? 'selected' : '' }}>Sección {{ $i }}</option>
            @endfor
        </select>
        <small class="text-muted">Debe existir en la configuración del grupo para el ciclo.</small>
    </div>
</div>

<div class="row">
    <div class="col-md-3 mb-3">
        <label for="day_of_week" class="form-label">Dia</label>
        <select name="day_of_week" id="day_of_week" class="form-control" required>
            <option value="">Seleccione dia</option>
            @foreach($dayOptions as $dayCode => $dayLabel)
                <option value="{{ $dayCode }}" {{ $selectedDay === $dayCode ? 'selected' : '' }}>
                    {{ $dayLabel }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3 mb-3">
        <label for="start_time" class="form-label">Hora inicio</label>
        <input type="time" name="start_time" id="start_time" class="form-control" value="{{ old('start_time', isset($schedule) ? \Illuminate\Support\Str::of($schedule->start_time)->substr(0, 5) : '') }}" required>
    </div>

    <div class="col-md-3 mb-3">
        <label for="end_time" class="form-label">Hora fin</label>
        <input type="time" name="end_time" id="end_time" class="form-control" value="{{ old('end_time', isset($schedule) ? \Illuminate\Support\Str::of($schedule->end_time)->substr(0, 5) : '') }}" required>
    </div>

    <div class="col-md-3 mb-3">
        <label for="type" class="form-label">Tipo (opcional)</label>
        <input type="text" name="type" id="type" class="form-control" value="{{ old('type', $schedule->type ?? '') }}" placeholder="Clase, laboratorio, taller">
    </div>
</div>
