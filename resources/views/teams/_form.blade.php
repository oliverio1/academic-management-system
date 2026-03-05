<div class="form-group">
    <label>Nombre del equipo</label>
    <input type="text" name="name"
           class="form-control"
           value="{{ old('name', $team->name ?? '') }}">
</div>

<div class="form-group">
    <label>Integrantes</label>
    @foreach($students as $student)
        @php
            $isDisabled = $assignedStudentIds->contains($student->id);
            $isChecked = isset($team) && $team->students->contains($student->id);
        @endphp

        <div class="form-check">
            <input type="checkbox"
                   name="students[]"
                   value="{{ $student->id }}"
                   class="form-check-input"
                   {{ $isChecked ? 'checked' : '' }}
                   {{ $isDisabled ? 'disabled' : '' }}>

            <label class="form-check-label
                {{ $isDisabled ? 'text-muted' : '' }}">
                {{ $student->user->name }}

                @if($isDisabled)
                    <small class="text-danger">
                        (ya pertenece a otro equipo)
                    </small>
                @endif
            </label>
        </div>
    @endforeach
</div>