@extends('layouts.app')

@section('title', $group->name . ' - Detalles')

@section('content')
    @if(session('info'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <strong>{{ session('info') }}</strong>
        </div>
    @endif
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12 connectedSortable mt-3">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="las la-layer-group"></i>
                                Grupo {{ $group->name }}
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4"><strong>Nivel:</strong> {{ $group->level->name }}</div>
                                <div class="col-md-4"><strong>Modalidad:</strong> {{ $group->level->modality->name }}</div>
                                <div class="col-md-4"><strong>Capacidad:</strong> {{ $group->capacity }}</div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="small-box bg-info">
                                        <div class="inner">
                                            <h3>{{ $students->count() }}</h3>
                                            <p>Alumnos</p>
                                        </div>
                                        <div class="icon"><i class="fas fa-users"></i></div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="small-box bg-success">
                                        <div class="inner">
                                            <h3>{{ $group->assignments->count() }}</h3>
                                            <p>Materias</p>
                                        </div>
                                        <div class="icon"><i class="fas fa-book"></i></div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="small-box bg-warning">
                                        <div class="inner">
                                            <h3>{{ $generalAverage }}</h3>
                                            <p>Promedio general</p>
                                        </div>
                                        <div class="icon"><i class="fas fa-chart-line"></i></div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="small-box bg-danger">
                                        <div class="inner">
                                            <h3>{{ $attendancePercentage }}%</h3>
                                            <p>Asistencia</p>
                                        </div>
                                        <div class="icon"><i class="fas fa-calendar-check"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">
                                <i class="fas fa-users"></i> Estudiantes del grupo
                            </h4>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Alumno</th>
                                        <th>Matrícula</th>
                                        <th>Promedio</th>
                                        <th>Detalles</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $student)
                                        <tr>
                                            <td>{{ $student->id }}</td>
                                            <td>{{ $student->user->name }}</td>
                                            <td>{{ $student->enrollment_number }}</td>
                                            <td>
                                                {{ $performanceAverages[$student->id] ?? '—' }}
                                            </td>
                                            <td>
                                                <a href="{{ route('students.report-card', $student) }}"
                                                class="btn btn-sm btn-primary">
                                                    Ver
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
