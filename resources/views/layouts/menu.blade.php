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

@role('admin')
<li class="nav-header text-uppercase text-muted">
    Administración
</li>

{{-- DASHBOARD --}}
<li class="nav-item">
    <a href="{{ route('dashboard') }}"
       class="nav-link {{ isActive('dashboard') }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Dashboard</p>
    </a>
</li>

{{-- SEGUIMIENTOS --}}
<li class="nav-item">
    <a href="{{ route('coordination.follow-ups.index') }}"
       class="nav-link {{ isActive('coordination.follow-ups.*') }}">
        <i class="nav-icon fas fa-clipboard-list"></i>
        <p>Seguimientos</p>
    </a>
</li>

{{-- JUSTIFICANTES --}}
<li class="nav-item">
    <a href="{{ route('attendance_justifications.index') }}"
       class="nav-link {{ isActive('attendance_justifications.*') }}">
        <i class="nav-icon fas fa-user-clock"></i>
        <p>Justificantes</p>
    </a>
</li>

{{-- AVISOS --}}
<li class="nav-item">
    <a href="{{ route('admin.announcements.index') }}"
       class="nav-link {{ isActive('admin.announcements.*') }}">
        <i class="nav-icon fas fa-bullhorn"></i>
        <p>Avisos</p>
    </a>
</li>

<li class="nav-divider my-2"></li>

{{-- ALUMNOS --}}
<li class="nav-item">
    <a href="{{ route('coordination.students.index') }}"
       class="nav-link {{ isActive('coordination.students.*') }}">
        <i class="nav-icon fas fa-user-graduate"></i>
        <p>Alumnos</p>
    </a>
</li>

{{-- GRUPOS --}}
<li class="nav-item">
    <a href="{{ route('coordination.students.index') }}"
       class="nav-link {{ isActive('coordination.students.*') }}">
        <i class="nav-icon fas fa-layer-group"></i>
        <p>Grupos</p>
    </a>
</li>

{{-- PROFESORES --}}
<li class="nav-item">
    <a href="{{ route('coordination.students.index') }}"
       class="nav-link {{ isActive('coordination.students.*') }}">
        <i class="nav-icon fas fa-chalkboard-teacher"></i>
        <p>Profesores</p>
    </a>
</li>

<li class="nav-divider my-2"></li>

{{-- REPORTES --}}
<li class="nav-item">
    <a href="{{ route('coordination.students.index') }}"
       class="nav-link {{ isActive('oordination.students.*') }}">
        <i class="nav-icon fas fa-chart-line"></i>
        <p>Reportes</p>
    </a>
</li>
@endrole

@role('teacher')
<li class="nav-header text-uppercase text-muted">
    Docente
</li>

{{-- INICIO --}}
<li class="nav-item">
    <a href="{{ route('dashboard') }}"
       class="nav-link {{ isActive('dashboard') }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Inicio</p>
    </a>
</li>

{{-- MIS CLASES --}}
<li class="nav-item">
    <a href="{{ route('teacher.classes.index') }}"
       class="nav-link {{ isActive('teacher.classes.*') }}">
        <i class="nav-icon fas fa-chalkboard-teacher"></i>
        <p>Mis clases</p>
    </a>
</li>

{{-- ALUMNOS --}}
<li class="nav-item">
    <a href="{{ route('teacher.students.index') }}"
       class="nav-link {{ isActive('teacher.students.*') }}">
        <i class="nav-icon fas fa-users"></i>
        <p>Alumnos</p>
    </a>
</li>

{{-- SEGUIMIENTO --}}
<li class="nav-item">
    <a href="{{ route('teacher.follow-ups.index') }}"
       class="nav-link {{ isActive('teacher.follow-ups.*') }}">
        <i class="nav-icon fas fa-clipboard-check"></i>
        <p>Seguimiento</p>
    </a>
</li>

{{-- JUSTIFICANTES --}}
<li class="nav-item">
    <a href="{{ route('teacher.justifications.index') }}"
       class="nav-link {{ isActive('teacher.justifications.*') }}">
        <i class="nav-icon fas fa-file-medical"></i>
        <p>Justificantes</p>
    </a>
</li>
@endrole

{{-- MENÚ ESTUDIANTES --}}
@role('student')
    <li class="nav-item">
        <a href="#" class="nav-link">
            <i class="nav-icon las la-user-graduate"></i>
            <p>Estudiante<i class="right fas fa-angle-left"></i></p>
        </a>
        <ul class="nav nav-treeview">
            <li class="nav-item">
                <a href="#" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Mis Prácticas</p></a>
            </li>
        </ul>
    </li>
@endrole