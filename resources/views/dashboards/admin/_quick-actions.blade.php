<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-tools"></i>
            Gestión rápida
        </h5>
    </div>

    <div class="card-body">

        <a href="{{ route('groups.index') }}"
           class="btn btn-outline-primary btn-block">
            <i class="fas fa-layer-group"></i>
            Administrar grupos
        </a>

        <a href="{{ route('teachers.index') }}"
           class="btn btn-outline-info btn-block">
            <i class="fas fa-chalkboard-teacher"></i>
            Profesores
        </a>

        <a href="{{ route('academic-periods.index') }}"
           class="btn btn-outline-secondary btn-block">
            <i class="fas fa-calendar-alt"></i>
            Periodos académicos
        </a>

    </div>
</div>
