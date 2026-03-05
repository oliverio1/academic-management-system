<div class="row mb-4">
    <div class="col-12">
        <h5 class="mb-3">Alertas académicas</h5>
    </div>

    {{-- Seguimientos críticos --}}
    <div class="col-md-3 mb-3">
        <div class="card border-danger h-100">
            <div class="card-body">
                <h6 class="text-danger">Seguimientos críticos</h6>
                <h3 class="mb-0">{{ $alerts['critical_followups'] }}</h3>
                <small class="text-muted">
                    Sin respuesta &gt; 7 días
                </small>
            </div>
            <div class="card-footer bg-transparent border-0">
                <a href="{{ route('coordination.follow-ups.critical') }}"
                   class="text-danger">
                    Ver casos
                </a>
            </div>
        </div>
    </div>

    {{-- Asistencia en riesgo --}}
    <div class="col-md-3 mb-3">
        <div class="card border-danger h-100">
            <div class="card-body">
                <h6 class="text-danger">Asistencia en riesgo</h6>
                <h3 class="mb-0">{{ $alerts['students_attendance_risk'] }}</h3>
                <small class="text-muted">
                    Alumnos con faltas recurrentes
                </small>
            </div>
            {{--Ruta por definir--}}
            <div class="card-footer bg-transparent border-0">
                <a href="{{ route('coordination.students.attendances-risk') }}"
                   class="text-danger">
                    Ver alumnos
                </a>
            </div>
        </div>
    </div>

    {{-- Profesores con bajo registro --}}
    <div class="col-md-3 mb-3">
        <div class="card border-danger h-100">
            <div class="card-body">
                <h6 class="text-danger">Registro docente bajo</h6>
                <h3 class="mb-0">{{ $alerts['teachers_low_attendance'] }}</h3>
                <small class="text-muted">
                    &lt; 80% de sesiones registradas
                </small>
            </div>
            <div class="card-footer bg-transparent border-0">
                <small class="text-muted">
                    Reporte detallado pendiente de habilitar
                </small>
            </div>
        </div>
    </div>

    {{-- Grupos en alerta --}}
    <div class="col-md-3 mb-3">
        <div class="card border-warning h-100">
            <div class="card-body">
                <h6 class="text-warning">Grupos en alerta</h6>
                <h3 class="mb-0">{{ $alerts['groups_in_alert'] }}</h3>
                <small class="text-muted">
                    Asistencia &lt; 80%
                </small>
            </div>
            <div class="card-footer bg-transparent border-0">
                <small class="text-muted">
                    Reporte detallado pendiente de habilitar
                </small>
            </div>
        </div>
    </div>
</div>