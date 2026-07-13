@php
    function isActive($routes) {
        foreach ((array) $routes as $route) {
            if (request()->routeIs($route)) return 'active';
        }
        return '';
    }

    function isOpen($routes) {
        foreach ((array) $routes as $route) {
            if (request()->routeIs($route)) return 'menu-open';
        }
        return '';
    }
@endphp

@role('coordinator')
<li class="nav-header text-uppercase text-muted">
    Coordinación
</li>

<li class="nav-item">
    <a href="{{ route('dashboard') }}"
       class="nav-link {{ isActive('dashboard') }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Inicio</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('attendance_justifications.index') }}"
       class="nav-link {{ isActive('attendance_justifications.*') }}">
        <i class="nav-icon fas fa-user-clock"></i>
        <p>Justificantes</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('coordination.teacher-documents.index') }}"
       class="nav-link {{ isActive(['coordination.teacher-documents.index','coordination.teacher-documents.create','coordination.teacher-documents.store']) }}">
        <i class="nav-icon fas fa-folder-open"></i>
        <p>Expediente docente</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('coordination.quality.index') }}"
       class="nav-link {{ isActive('coordination.quality.*') }}">
        <i class="nav-icon fas fa-clipboard-check"></i>
        <p>SGC / Calidad</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('coordination.inventory.index') }}"
       class="nav-link {{ isActive('coordination.inventory.*') }}">
        <i class="nav-icon fas fa-boxes"></i>
        <p>Inventario</p>
    </a>
</li>

<li class="nav-item {{ isOpen(['coordination.reports.*', 'coordination.student-incident-reports.*', 'coordination.prefect-reports.*']) }}">
    <a href="#"
       class="nav-link {{ isActive(['coordination.reports.*', 'coordination.student-incident-reports.*', 'coordination.prefect-reports.*']) }}">
        <i class="nav-icon fas fa-flag"></i>
        <p>Revisión de reportes <i class="right fas fa-angle-left"></i></p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="{{ route('coordination.reports.index') }}"
               class="nav-link {{ isActive('coordination.reports.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Docentes</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('coordination.student-incident-reports.index') }}"
               class="nav-link {{ isActive('coordination.student-incident-reports.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Alumnos</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('coordination.prefect-reports.index') }}"
               class="nav-link {{ isActive('coordination.prefect-reports.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Prefectura</p>
            </a>
        </li>
    </ul>
</li>

<li class="nav-item">
    <a href="{{ route('coordination.follow-ups.index') }}"
       class="nav-link {{ isActive('coordination.follow-ups.*') }}">
        <i class="nav-icon fas fa-clipboard-list"></i>
        <p>Seguimientos</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('coordination.suspensions.index') }}"
       class="nav-link {{ isActive('coordination.suspensions.*') }}">
        <i class="nav-icon fas fa-user-slash"></i>
        <p>Suspensiones</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('admin.announcements.index') }}"
       class="nav-link {{ isActive('admin.announcements.*') }}">
        <i class="nav-icon fas fa-bullhorn"></i>
        <p>Avisos</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('students.index') }}"
       class="nav-link {{ isActive(['students.index', 'students.edit']) }}">
        <i class="nav-icon fas fa-exchange-alt"></i>
        <p>Cambio de grupos</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('coordination.schedules.groups-calendar') }}"
       class="nav-link {{ isActive('coordination.schedules.groups-calendar') }}">
        <i class="nav-icon fas fa-calendar-alt"></i>
        <p>Calendario de grupos</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('school-cycles.create') }}"
       class="nav-link {{ isActive('school-cycles.create') }}">
        <i class="nav-icon fas fa-plus-square"></i>
        <p>Nuevo ciclo</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('coordination.paper-exams.schedule') }}"
       class="nav-link {{ isActive('coordination.paper-exams.schedule') }}">
        <i class="nav-icon fas fa-calendar-check"></i>
        <p>Horarios de exámenes</p>
    </a>
</li>

@endrole

@hasrole('admin')
@unlessrole('coordinator')
<li class="nav-header text-uppercase text-muted">
    Coordinació n
</li>

<li class="nav-item">
    <a href="{{ route('dashboard') }}"
       class="nav-link {{ isActive('dashboard') }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Dashboard</p>
    </a>
</li>

<li class="nav-header text-uppercase text-muted">
    Día a día
</li>

<li class="nav-item">
    <a href="{{ route('coordination.students.attendances-risk') }}"
       class="nav-link {{ isActive('coordination.students.attendances-risk') }}">
        <i class="nav-icon fas fa-user-times"></i>
        <p>Faltas consecutivas</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('coordination.students.class-skips') }}"
       class="nav-link {{ isActive('coordination.students.class-skips') }}">
        <i class="nav-icon fas fa-running"></i>
        <p>Sesiones saltadas</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('attendance_justifications.index') }}"
       class="nav-link {{ isActive('attendance_justifications.*') }}">
        <i class="nav-icon fas fa-user-clock"></i>
        <p>Justificantes</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('coordination.students.academic-summary') }}"
       class="nav-link {{ isActive('coordination.students.academic-summary') }}">
        <i class="nav-icon fas fa-book-reader"></i>
        <p>Revisión académica</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('coordination.economic-actas.index') }}"
       class="nav-link {{ isActive('coordination.economic-actas.*') }}">
        <i class="nav-icon fas fa-file-signature"></i>
        <p>Actas económicas</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('coordination.teacher-documents.index') }}"
       class="nav-link {{ isActive(['coordination.teacher-documents.index','coordination.teacher-documents.create','coordination.teacher-documents.store']) }}">
        <i class="nav-icon fas fa-folder-open"></i>
        <p>Expediente docente</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('coordination.teacher-attendance.index') }}"
       class="nav-link {{ isActive('coordination.teacher-attendance.*') }}">
        <i class="nav-icon fas fa-user-check"></i>
        <p>Asistencia docente</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('coordination.quality.index') }}"
       class="nav-link {{ isActive('coordination.quality.*') }}">
        <i class="nav-icon fas fa-clipboard-check"></i>
        <p>SGC / Calidad</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('coordination.inventory.index') }}"
       class="nav-link {{ isActive('coordination.inventory.*') }}">
        <i class="nav-icon fas fa-boxes"></i>
        <p>Inventario</p>
    </a>
</li>

<li class="nav-item {{ isOpen(['coordination.finance.*']) }}">
    <a href="#"
       class="nav-link {{ isActive(['coordination.finance.*']) }}">
        <i class="nav-icon fas fa-wallet"></i>
        <p>Cartera vencida <i class="right fas fa-angle-left"></i></p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="{{ route('coordination.finance.dashboard') }}"
               class="nav-link {{ isActive('coordination.finance.dashboard') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Tablero</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('coordination.finance.concepts.index') }}"
               class="nav-link {{ isActive('coordination.finance.concepts.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Conceptos</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('coordination.finance.charges.index') }}"
               class="nav-link {{ isActive('coordination.finance.charges.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Cargos</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('coordination.finance.statements.index') }}"
               class="nav-link {{ isActive('coordination.finance.statements.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Estados de cuenta</p>
            </a>
        </li>
    </ul>
</li>

<li class="nav-item {{ isOpen(['coordination.reports.*', 'coordination.student-incident-reports.*', 'coordination.prefect-reports.*']) }}">
    <a href="#"
       class="nav-link {{ isActive(['coordination.reports.*', 'coordination.student-incident-reports.*', 'coordination.prefect-reports.*']) }}">
        <i class="nav-icon fas fa-flag"></i>
        <p>Revisión de reportes <i class="right fas fa-angle-left"></i></p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="{{ route('coordination.reports.index') }}"
               class="nav-link {{ isActive('coordination.reports.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Docentes</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('coordination.student-incident-reports.index') }}"
               class="nav-link {{ isActive('coordination.student-incident-reports.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Alumnos</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('coordination.prefect-reports.index') }}"
               class="nav-link {{ isActive('coordination.prefect-reports.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Prefectura</p>
            </a>
        </li>
    </ul>
</li>

<li class="nav-item">
    <a href="{{ route('coordination.follow-ups.index') }}"
       class="nav-link {{ isActive('coordination.follow-ups.*') }}">
        <i class="nav-icon fas fa-clipboard-list"></i>
        <p>Seguimientos</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('coordination.suspensions.index') }}"
       class="nav-link {{ isActive('coordination.suspensions.*') }}">
        <i class="nav-icon fas fa-user-slash"></i>
        <p>Suspensiones</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('admin.announcements.index') }}"
       class="nav-link {{ isActive('admin.announcements.*') }}">
        <i class="nav-icon fas fa-bullhorn"></i>
        <p>Avisos</p>
    </a>
</li>

<li class="nav-header text-uppercase text-muted">
    Historial del alumno
</li>

<li class="nav-item">
    <a href="{{ route('coordination.students.index') }}"
       class="nav-link {{ isActive(['students.*', 'coordination.students.*']) }}">
        <i class="nav-icon fas fa-user-graduate"></i>
        <p>Expediente de alumnos</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('students.create') }}"
       class="nav-link {{ isActive('students.create') }}">
        <i class="nav-icon fas fa-user-plus"></i>
        <p>Alta de alumnos</p>
    </a>
</li>

<li class="nav-header text-uppercase text-muted">
    Configuración de ciclos
</li>

<li class="nav-item">
    <a href="{{ route('coordination.schedules.groups-calendar') }}"
       class="nav-link {{ isActive('coordination.schedules.groups-calendar') }}">
        <i class="nav-icon fas fa-th-large"></i>
        <p>Grupos (calendario)</p>
    </a>
</li>

<li class="nav-item {{ isOpen(['modalities.*', 'levels.*', 'groups.*']) }}">
    <a href="#"
       class="nav-link {{ isActive(['modalities.*', 'levels.*', 'groups.*']) }}">
        <i class="nav-icon fas fa-school"></i>
        <p>Configuración de escuela <i class="right fas fa-angle-left"></i></p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="{{ route('modalities.index') }}"
               class="nav-link {{ isActive('modalities.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Modalidades</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('levels.index') }}"
               class="nav-link {{ isActive('levels.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Niveles</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('groups.index') }}"
               class="nav-link {{ isActive('groups.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Grupos</p>
            </a>
        </li>
    </ul>
</li>

<li class="nav-item {{ isOpen(['campuses.*', 'subjects.*', 'teachers.*', 'tutors.*', 'coordination.tutor-assignments.*', 'coordination.schedules.*', 'school-cycles.*', 'coordination.cycle-planning.*', 'coordination.cycle-promotions.*', 'academic-calendar-days.*', 'imports.*', 'coordination.temarios.*', 'temarios.*', 'coordination.paper-exams.*']) }}">
    <a href="#"
       class="nav-link {{ isActive(['campuses.*', 'subjects.*', 'teachers.*', 'tutors.*', 'coordination.tutor-assignments.*', 'coordination.schedules.*', 'school-cycles.*', 'coordination.cycle-planning.*', 'coordination.cycle-promotions.*', 'academic-calendar-days.*', 'imports.*', 'coordination.temarios.*', 'temarios.*', 'coordination.paper-exams.*']) }}">
        <i class="nav-icon fas fa-cogs"></i>
        <p>Administración académica <i class="right fas fa-angle-left"></i></p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="{{ route('campuses.index') }}"
               class="nav-link {{ isActive('campuses.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Campus</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('subjects.index') }}"
               class="nav-link {{ isActive('subjects.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Materias</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('teachers.index') }}"
               class="nav-link {{ isActive('teachers.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Profesores</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('tutors.index') }}"
               class="nav-link {{ isActive('tutors.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Tutores</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('coordination.tutor-assignments.index') }}"
               class="nav-link {{ isActive('coordination.tutor-assignments.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Asignar tutores</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('coordination.schedules.index') }}"
               class="nav-link {{ isActive('coordination.schedules.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Horarios</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('school-cycles.index') }}"
               class="nav-link {{ isActive(['school-cycles.index', 'school-cycles.edit', 'school-cycles.partials.*']) }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Ciclos y parciales</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('school-cycles.create') }}"
               class="nav-link {{ isActive('school-cycles.create') }}">
                <i class="far fa-plus-square nav-icon"></i>
                <p>Nuevo ciclo</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('coordination.cycle-planning.index') }}"
               class="nav-link {{ isActive('coordination.cycle-planning.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Editar grupos del ciclo</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('coordination.cycle-promotions.index') }}"
               class="nav-link {{ isActive('coordination.cycle-promotions.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Promoción de ciclo</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('academic-calendar-days.index') }}"
               class="nav-link {{ isActive('academic-calendar-days.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Calendario escolar</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('imports.attendances.create') }}"
               class="nav-link {{ isActive('imports.attendances.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Importar asistencias</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('imports.master-schedule.create') }}"
               class="nav-link {{ isActive('imports.master-schedule.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Importar horario maestro</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('imports.cycle-students.create') }}"
               class="nav-link {{ isActive('imports.cycle-students.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Cargar alumnos a ciclo</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('imports.grades.create') }}"
               class="nav-link {{ isActive('imports.grades.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Importar evaluación</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('coordination.temarios.index') }}"
               class="nav-link {{ isActive(['coordination.temarios.*', 'temarios.*']) }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Temarios</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('coordination.paper-exams.schedule') }}"
               class="nav-link {{ isActive('coordination.paper-exams.schedule') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Horarios de exámenes</p>
            </a>
        </li>
    </ul>
</li>
@endunlessrole
@endhasrole

@role('prefect')
<li class="nav-header text-uppercase text-muted">
    Prefectura
</li>

<li class="nav-item">
    <a href="{{ route('prefect.groups.index') }}"
       class="nav-link {{ isActive('prefect.groups.*') }}">
        <i class="nav-icon fas fa-object-group"></i>
        <p>Grupos</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('prefect.reports.index') }}"
       class="nav-link {{ isActive('prefect.reports.*') }}">
        <i class="nav-icon fas fa-flag"></i>
        <p>Reportes</p>
    </a>
</li>
@endrole

@role('teacher')
<li class="nav-header text-uppercase text-muted">
    Docente
</li>

<li class="nav-item">
    <a href="{{ route('dashboard') }}"
       class="nav-link {{ isActive('dashboard') }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Inicio</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('teacher.classes.index') }}"
       class="nav-link {{ isActive(['teacher.classes.*', 'teacher.evaluation.*', 'practices.*']) }}">
        <i class="nav-icon fas fa-chalkboard-teacher"></i>
        <p>Mis clases</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('teacher.attendance.index') }}"
       class="nav-link {{ isActive('teacher.attendance.*') }}">
        <i class="nav-icon fas fa-user-check"></i>
        <p>Registro de asistencia</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('teacher.didactic-plans.index') }}"
       class="nav-link {{ isActive('teacher.didactic-plans.*') }}">
        <i class="nav-icon fas fa-file-alt"></i>
        <p>Generación de planeaciones</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('teacher.question-banks.index') }}"
       class="nav-link {{ isActive('teacher.question-banks.*') }}">
        <i class="nav-icon fas fa-question-circle"></i>
        <p>Configuración de exámenes</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('teacher.reports.index') }}"
       class="nav-link {{ isActive('teacher.reports.*') }}">
        <i class="nav-icon fas fa-flag"></i>
        <p>Reportes</p>
    </a>
</li>

<li class="nav-item {{ isOpen(['teacher.follow-ups.*', 'teacher.justifications.*', 'teacher.document-requests.*']) }}">
    <a href="#"
       class="nav-link {{ isActive(['teacher.follow-ups.*', 'teacher.justifications.*', 'teacher.document-requests.*']) }}">
        <i class="nav-icon fas fa-bullhorn"></i>
        <p>Avisos <i class="right fas fa-angle-left"></i></p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="{{ route('teacher.follow-ups.index') }}"
               class="nav-link {{ isActive('teacher.follow-ups.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Seguimiento</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('teacher.justifications.index') }}"
               class="nav-link {{ isActive('teacher.justifications.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Justificantes</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('teacher.document-requests.index') }}"
               class="nav-link {{ isActive('teacher.document-requests.*') }}">
                <i class="far fa-circle nav-icon"></i>
                <p>Documentos solicitados</p>
            </a>
        </li>
    </ul>
</li>
@endrole

@role('student')
<li class="nav-header text-uppercase text-muted">
    Alumno
</li>

<li class="nav-item">
    <a href="{{ route('dashboard') }}"
       class="nav-link {{ isActive(['dashboard', 'student.subjects', 'student.subjects.show']) }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Inicio</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('student.practices.index') }}"
       class="nav-link {{ isActive('student.practices.*') }}">
        <i class="nav-icon fas fa-flask"></i>
        <p>Entregables</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('student.reports') }}"
       class="nav-link {{ isActive('student.reports') }}">
        <i class="nav-icon fas fa-flag"></i>
        <p>Mis reportes</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('student.incident-reports.index') }}"
       class="nav-link {{ isActive('student.incident-reports.*') }}">
        <i class="nav-icon fas fa-exclamation-triangle"></i>
        <p>Reportar situacion</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('student.followups') }}"
       class="nav-link {{ isActive('student.followups') }}">
        <i class="nav-icon fas fa-clipboard-list"></i>
        <p>Mis seguimientos</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('student.exams.index') }}"
       class="nav-link {{ isActive('student.exams.*') }}">
        <i class="nav-icon fas fa-file-signature"></i>
        <p>Exámenes en línea</p>
    </a>
</li>
@endrole

@hasanyrole('guardian|tutor')
<li class="nav-header text-uppercase text-muted">
    Tutor
</li>

<li class="nav-item">
    <a href="{{ route('tutor.subjects') }}"
       class="nav-link {{ isActive(['tutor.subjects', 'tutor.subjects.show']) }}">
        <i class="nav-icon fas fa-calendar-alt"></i>
        <p>Aprovechamiento</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('tutor.attendance') }}"
       class="nav-link {{ isActive('tutor.attendance') }}">
        <i class="nav-icon fas fa-user-check"></i>
        <p>Asistencia</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('tutor.account-statement') }}"
       class="nav-link {{ isActive('tutor.account-statement') }}">
        <i class="nav-icon fas fa-wallet"></i>
        <p>Estado de cuenta</p>
    </a>
</li>

<li class="nav-item">
    <a href="{{ route('tutor.followups') }}"
       class="nav-link {{ isActive('tutor.followups') }}">
        <i class="nav-icon fas fa-clipboard-list"></i>
        <p>Seguimientos</p>
    </a>
</li>
@endhasanyrole
