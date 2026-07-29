@php
    $prefill = $prefill ?? [];
    $fieldValue = fn ($name, $default = null) => old($name, $prefill[$name] ?? $default);
@endphp

@if($errors->any())
    <div class="alert alert-danger">
        <strong>Revisa la informacion capturada.</strong>
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@foreach([
    'source_report_type',
    'source_report_id',
    'source_type',
    'source_user_id',
    'student_id',
    'group_id',
    'teacher_id',
    'guardian_user_id',
    'location',
] as $hiddenField)
    <input type="hidden" name="{{ $hiddenField }}" value="{{ $fieldValue($hiddenField, $hiddenField === 'source_type' ? 'coordination' : '') }}">
@endforeach

@if(!empty($prefill['source_report_type']))
    <div class="alert alert-info">
        <strong>Reporte de origen:</strong>
        {{ ucfirst($prefill['source_report_type']) }}
        #{{ $prefill['source_report_id'] ?? '' }}.
        Los datos relacionados se cargaron automaticamente; coordinacion solo debe definir el seguimiento.
    </div>
@endif

<div class="row">
    <div class="col-md-8 mb-3">
        <label>Asunto del caso</label>
        <input name="subject" value="{{ $fieldValue('subject') }}" class="form-control" maxlength="180" required>
    </div>
    <div class="col-md-4 mb-3">
        <label>Tipo de asunto</label>
        <select name="target_type" class="form-control" required>
            @foreach($targetTypes as $value => $label)
                <option value="{{ $value }}" {{ $fieldValue('target_type', 'general') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="row">
    <div class="col-md-3 mb-3">
        <label>Categoria</label>
        <select name="category" class="form-control" required>
            @foreach($categories as $value => $label)
                <option value="{{ $value }}" {{ $fieldValue('category', 'communication') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 mb-3">
        <label>Prioridad</label>
        <select name="priority" class="form-control" required>
            @foreach($priorities as $value => $label)
                <option value="{{ $value }}" {{ $fieldValue('priority', 'medium') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 mb-3">
        <label>Estatus inicial</label>
        <select name="status" class="form-control" required>
            @foreach($statuses as $value => $label)
                <option value="{{ $value }}" {{ $fieldValue('status', 'new') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 mb-3">
        <label>Fecha compromiso</label>
        <input type="datetime-local" name="due_at" value="{{ $fieldValue('due_at') }}" class="form-control">
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label>Responsable</label>
        <select name="assigned_to" class="form-control">
            <option value="">Sin asignar</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" {{ (string) $fieldValue('assigned_to') === (string) $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-8 mb-3">
        <label>Descripcion del seguimiento</label>
        <textarea name="description" rows="5" class="form-control" required>{{ $fieldValue('description') }}</textarea>
        <small class="form-text text-muted">
            Resume que ocurrio, a quienes involucra y que debe revisar coordinacion. Puedes editar la informacion precargada del reporte.
        </small>
    </div>
</div>

<div class="row">
    <div class="col-md-12 mb-3">
        <label>Nota interna</label>
        <textarea name="initial_note" rows="3" class="form-control">{{ $fieldValue('initial_note') }}</textarea>
    </div>
</div>

<h5>Primera accion de seguimiento</h5>
<div class="table-responsive">
    <table class="table table-bordered table-sm">
        <thead class="thead-light">
            <tr>
                <th>Accion</th>
                <th style="width: 260px;">Responsable</th>
                <th style="width: 220px;">Fecha compromiso</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <input name="action_title" value="{{ $fieldValue('action_title') }}" class="form-control" maxlength="180" placeholder="Ej. Llamar al tutor, revisar salon, citar alumno">
                </td>
                <td>
                    <select name="action_assigned_to" class="form-control">
                        <option value="">Sin asignar</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ (string) $fieldValue('action_assigned_to') === (string) $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <input type="datetime-local" name="action_due_at" value="{{ $fieldValue('action_due_at') }}" class="form-control">
                </td>
            </tr>
        </tbody>
    </table>
</div>
