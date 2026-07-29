<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Acciones de coordinación</h5>
            <span class="text-muted small">Atajos a pantallas operativas disponibles para coordinacion</span>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <a href="{{ route('coordination.follow-ups.index') }}" class="btn btn-outline-primary btn-block text-left">
            <i class="fas fa-clipboard-list mr-2"></i>Seguimientos
        </a>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <a href="{{ route('attendance_justifications.index') }}" class="btn btn-outline-primary btn-block text-left">
            <i class="fas fa-user-clock mr-2"></i>Justificantes
        </a>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <a href="{{ route('coordination.reports.index') }}" class="btn btn-outline-primary btn-block text-left">
            <i class="fas fa-flag mr-2"></i>Reportes docentes
        </a>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <a href="{{ route('coordination.prefect-reports.index') }}" class="btn btn-outline-primary btn-block text-left">
            <i class="fas fa-user-shield mr-2"></i>Reportes prefectura
        </a>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <a href="{{ route('coordination.teacher-documents.index') }}" class="btn btn-outline-secondary btn-block text-left">
            <i class="fas fa-folder-open mr-2"></i>Expediente docente
        </a>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <a href="{{ route('coordination.suspensions.index') }}" class="btn btn-outline-secondary btn-block text-left">
            <i class="fas fa-user-slash mr-2"></i>Suspensiones
        </a>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <a href="{{ route('students.index') }}" class="btn btn-outline-secondary btn-block text-left">
            <i class="fas fa-exchange-alt mr-2"></i>Cambio de grupos
        </a>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <a href="{{ route('coordination.schedules.groups-calendar') }}" class="btn btn-outline-secondary btn-block text-left">
            <i class="fas fa-calendar-alt mr-2"></i>Calendario de grupos
        </a>
    </div>
</div>
