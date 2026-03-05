<div class="card-body">
    <div class="form-group">
        <label>Nombre completo</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $user->name ?? '') }}" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="form-group">
        <label>Correo electrónico</label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $user->email ?? '') }}" required>
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    @if($mode === 'create')
        <div class="form-group">
            <label>Contraseña</label>
            <input type="password" name="password" class="form-control" required>
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @else
        <div class="form-group">
            <label>Nueva contraseña (opcional)</label>
            <input type="password" name="password" class="form-control">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @endif
    <div class="form-group">
        <label>Rol</label>
        <select name="role" id="role" class="form-control" required>
            <option value="">Seleccione un rol</option>
            @foreach($roles as $role)
                <option value="{{ $role }}"
                    {{ old('role', $user?->getRoleNames()->first()) === $role ? 'selected' : '' }}>{{ ucfirst($role) }}
                </option>
            @endforeach
        </select>
        @error('role')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <hr>
    <div id="student-fields" class="role-fields d-none">
    <h5>Datos del alumno</h5>
    <div class="form-group">
        <label>Matrícula</label>
        <input type="text" name="student[enrollment_number]" class="form-control" value="{{ old('student.enrollment_number', $user?->student?->enrollment_number) }}">
        @error('enrollment_number')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="form-group">
        <label>Grupo</label>
        <select name="student[group_id]" class="form-control">
            <option value="">Seleccione grupo</option>
            @foreach($groups as $group)
                <option value="{{ $group->id }}"
                    {{ old('student.group_id', $user?->student?->group_id) == $group->id ? 'selected' : '' }}>{{ $group->name }}
                </option>
            @endforeach
        </select>
        @error('group_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="form-group">
        <label>Teléfono</label>
        <input type="text" name="student[phone]" class="form-control" value="{{ old('student.phone', $user?->student?->phone) }}">
        @error('phone')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="form-group">
        <label class="form-label">Dirección</label>
        <textarea class="form-control" name="student[address]" rows="4">{{ old('student.address', $user?->student?->address) }}</textarea>
        @error('address')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div id="teacher-fields" class="role-fields d-none">
    <h5>Datos del docente</h5>
    <div class="form-group">
        <label>Teléfono</label>
        <input type="text" name="teacher[phone]" class="form-control" value="{{ old('teacher.phone', $user?->teacher?->phone) }}">
        @error('phone')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="form-group">
        <label class="form-label">Dirección</label>
        <textarea class="form-control" name="teacher[address]" rows="4">{{ old('teacher.address', $user?->teacher?->address) }}</textarea>
        @error('address')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div id="coordination-fields" class="role-fields d-none">
    <h5>Datos de coordinación</h5>
    <div class="form-group">
        <label>Área</label>
        <input type="text" name="coordinator[area]" class="form-control" value="{{ old('coordinator.area', $user?->coordinator?->area) }}">
        @error('emergency_contact_email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="form-group">
        <label>Teléfono</label>
        <input type="text" name="coordinator[phone]" class="form-control" value="{{ old('coordinator.phone', $user?->coordinator?->area) }}">
        @error('emergency_contact_email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>
</div>
