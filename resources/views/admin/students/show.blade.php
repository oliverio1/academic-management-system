@extends('layouts.app')

@section('title', 'Detalle del alumno')

@section('content')
<div class="content-header">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
                <a href="{{ route('coordination.students.index') }}"
                   class="btn btn-sm btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>

                <h1 class="m-0 mt-2">{{ $student->user->name }}</h1>
                <small class="text-muted">
                    Grupo: {{ $student->group->name ?? '—' }} |
                    Nivel: {{ $student->group->level->name ?? '—' }} |
                    Modalidad: {{ $student->group->modality->name ?? '—' }}
                </small>
            </div>

            <div class="text-right">
                <a href="{{ route('coordination.students.attendance.history', $student) }}"
                   class="btn btn-sm btn-info mb-1">
                    <i class="fas fa-calendar-check"></i> Todas las asistencias
                </a>

                <a href="{{ route('coordination.students.grades.history', $student) }}"
                   class="btn btn-sm btn-success mb-1">
                    <i class="fas fa-chart-line"></i> Todas las calificaciones
                </a>
            </div>
        </div>

    </div>
</div>

<div class="content">
    <div class="container-fluid">

        {{-- INDICADORES RÁPIDOS --}}
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>—</h3>
                        <p>Promedio general</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>—%</h3>
                        <p>Asistencia</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>—</h3>
                        <p>Seguimientos abiertos</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- TABS --}}
        <div class="card">
            <div class="card-header p-2">
                <ul class="nav nav-pills">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#general">
                            General
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#attendance">
                            Asistencia
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#grades">
                            Evaluación
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#followups">
                            Seguimientos
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                <div class="tab-content">

                    {{-- GENERAL --}}
                    <div class="tab-pane fade" id="general"></div>

                    {{-- ASISTENCIA --}}
                    <div class="tab-pane fade" id="attendance"></div>
                    
                    {{-- EVALUACIÓN --}}
                    <div class="tab-pane fade" id="grades"></div>
                    
                    {{-- SEGUIMIENTOS --}}
                    <div class="tab-pane fade" id="followups"></div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@section('page_scripts')
    <script>
        function loadTab(tab, url) {
            const container = document.getElementById(tab);
            if (container.dataset.loaded) return;

            container.innerHTML = '<p class="text-muted">Cargando...</p>';

            fetch(url)
                .then(res => res.text())
                .then(html => {
                    container.innerHTML = html;
                    container.dataset.loaded = true;
                });
        }

        document.addEventListener('DOMContentLoaded', function () {
            const studentId = {{ $student->id }};

            loadTab('general', '{{ route('coordination.students.general', $student) }}');

            $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
                const tabId = e.target.getAttribute('href').substring(1);
                const routes = {
                    attendance: '{{ route('coordination.students.attendance', $student) }}',
                    grades: '{{ route('coordination.students.grades', $student) }}',
                    followups: '{{ route('coordination.students.followups', $student) }}',
                };

                if (routes[tabId]) {
                    loadTab(tabId, routes[tabId]);
                }
            });
        });
    </script>
@endsection