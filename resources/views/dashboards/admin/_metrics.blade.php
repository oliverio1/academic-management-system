<div class="row mb-4">
    <div class="col-12">
        <h5 class="mb-3">Estado académico general</h5>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <h6 class="text-muted">Asistencia global</h6>
                <h3>{{ $metrics['global_attendance'] }}%</h3>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <h6 class="text-muted">Alumnos en riesgo</h6>
                <h3>{{ $metrics['students_at_risk'] }}%</h3>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <h6 class="text-muted">Seguimientos activos</h6>
                <h3>{{ $metrics['active_followups'] }}</h3>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <h6 class="text-muted">Justificantes activos</h6>
                <h3>{{ $metrics['active_justifications'] }}</h3>
            </div>
        </div>
    </div>
</div>
