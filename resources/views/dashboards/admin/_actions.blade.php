<div class="row">
    <div class="col-12">
        <h5 class="mb-3">Accesos rápidos</h5>
    </div>

    <div class="col-md-3 mb-3">
        <a href="{{ route('coordination.follow-ups.index') }}"
           class="btn btn-outline-primary btn-block">
            Seguimientos
        </a>
    </div>

    <div class="col-md-3 mb-3">
        <a href="{{ route('attendance_justifications.index') }}"
           class="btn btn-outline-secondary btn-block">
            Justificantes
        </a>
    </div>

    <div class="col-md-3 mb-3">
        <a href="{{ route('admin.announcements.index') }}"
           class="btn btn-outline-success btn-block">
            Avisos
        </a>
    </div>

    <div class="col-md-3 mb-3">
        <a href="{{ route('academic-calendar-days.index') }}"
           class="btn btn-outline-info btn-block">
            Feriados y vacaciones
        </a>
    </div>
</div>
