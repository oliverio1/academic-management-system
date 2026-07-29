<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Pulso académico</h5>
            <span class="text-muted small">Indicadores para seguimiento semanal</span>
        </div>
    </div>

    <div class="col-lg col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="text-muted">Asistencia global</h6>
                <h3>{{ $metrics['global_attendance'] }}%</h3>
                <small class="text-muted">Promedio reciente sin justificantes activos</small>
            </div>
        </div>
    </div>

    <div class="col-lg col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="text-muted">Alumnos en riesgo</h6>
                <h3>{{ $metrics['students_at_risk'] }}%</h3>
                <small class="text-muted">Proporcion de alumnos con alerta activa</small>
            </div>
        </div>
    </div>

    <div class="col-lg col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="text-muted">Seguimientos activos</h6>
                <h3>{{ $metrics['active_followups'] }}</h3>
                <small class="text-muted">Casos abiertos en coordinacion</small>
            </div>
        </div>
    </div>

    <div class="col-lg col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="text-muted">Justificantes activos</h6>
                <h3>{{ $metrics['active_justifications'] }}</h3>
                <small class="text-muted">Ausencias justificadas vigentes</small>
            </div>
        </div>
    </div>

    <div class="col-lg col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="text-muted">Grupos en alerta</h6>
                <h3>{{ $metrics['groups_in_alert'] }}</h3>
                <small class="text-muted">Rendimiento y asistencia por debajo del umbral</small>
            </div>
        </div>
    </div>
</div>
