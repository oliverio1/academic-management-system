<div class="row">
    <div class="col-md-6">
        <p><strong>Nombre:</strong> {{ $student->user->name }}</p>
        <p><strong>Matrícula:</strong> {{ $student->enrollment_number ?? '—' }}</p>
        <p><strong>Teléfono:</strong> {{ $student->phone ?? '—' }}</p>
    </div>

    <div class="col-md-6">
        <p><strong>Grupo:</strong> {{ $student->group->name ?? '—' }}</p>
        <p><strong>Nivel:</strong> {{ $student->group->level->name ?? '—' }}</p>
        <p><strong>Modalidad:</strong> {{ $student->group->modality->name ?? '—' }}</p>
    </div>
</div>