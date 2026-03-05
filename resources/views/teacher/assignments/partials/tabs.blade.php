@php
    $tab = request('tab', 'evaluation');
@endphp

<ul class="nav nav-tabs">
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'evaluation' ? 'active' : '' }}"
           href="?tab=evaluation">Evaluación</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'activities' ? 'active' : '' }}"
           href="?tab=activities">Actividades</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'attendance' ? 'active' : '' }}"
           href="?tab=attendance">Asistencia</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'teams' ? 'active' : '' }}"
           href="?tab=teams">Equipos</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'grades' ? 'active' : '' }}"
           href="?tab=grades">Calificaciones</a>
    </li>
</ul>

