<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Semaforo operativo</h5>
            <span class="text-muted small">Casos que requieren revision de coordinacion</span>
        </div>
    </div>

    <div class="col-xl col-md-4 mb-3">
        <div class="card border-danger h-100">
            <div class="card-body">
                <h6 class="text-danger">Seguimientos criticos</h6>
                <h3 class="mb-0">{{ $alerts['critical_followups'] }}</h3>
                <small class="text-muted">Sin respuesta docente por mas de 7 dias</small>
            </div>
            <div class="card-footer bg-transparent border-0">
                <a href="{{ route('coordination.follow-ups.critical') }}" class="text-danger">Ver casos</a>
            </div>
        </div>
    </div>

    <div class="col-xl col-md-4 mb-3">
        <div class="card border-danger h-100">
            <div class="card-body">
                <h6 class="text-danger">Asistencia en riesgo</h6>
                <h3 class="mb-0">{{ $alerts['students_attendance_risk'] }}</h3>
                <small class="text-muted">Alumnos con faltas recurrentes no justificadas</small>
            </div>
            <div class="card-footer bg-transparent border-0">
                <a href="{{ route('coordination.students.attendances-risk') }}" class="text-danger">Ver alumnos</a>
            </div>
        </div>
    </div>

    <div class="col-xl col-md-4 mb-3">
        <div class="card border-warning h-100">
            <div class="card-body">
                <h6 class="text-warning">Sesiones saltadas</h6>
                <h3 class="mb-0">{{ $alerts['students_class_skips'] }}</h3>
                <small class="text-muted">Presentes en plantel, ausentes en clase</small>
            </div>
            <div class="card-footer bg-transparent border-0">
                <a href="{{ route('coordination.students.class-skips') }}" class="text-warning">Ver reporte</a>
            </div>
        </div>
    </div>

    <div class="col-xl col-md-6 mb-3">
        <div class="card border-warning h-100">
            <div class="card-body">
                <h6 class="text-warning">Registro docente bajo</h6>
                <h3 class="mb-0">{{ $alerts['teachers_low_attendance'] }}</h3>
                <small class="text-muted">Docentes con captura incompleta de asistencia o actividad</small>
            </div>
            <div class="card-footer bg-transparent border-0">
                <a href="{{ route('admin.alerts.teachers-low-registration') }}" class="text-warning">Ver docentes</a>
            </div>
        </div>
    </div>

    <div class="col-xl col-md-6 mb-3">
        <div class="card border-info h-100">
            <div class="card-body">
                <h6 class="text-info">Grupos en alerta</h6>
                <h3 class="mb-0">{{ $alerts['groups_in_alert'] }}</h3>
                <small class="text-muted">Promedio bajo combinado con asistencia debil</small>
            </div>
            <div class="card-footer bg-transparent border-0">
                <a href="{{ route('admin.alerts.groups-in-alert') }}" class="text-info">Ver grupos</a>
            </div>
        </div>
    </div>
</div>
