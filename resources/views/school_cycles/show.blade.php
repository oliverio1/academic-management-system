@extends('layouts.app')

@section('title', 'Detalle del ciclo escolar')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0">{{ $schoolCycle->name }}</h4>
                        <small class="text-muted">{{ $schoolCycle->code }}</small>
                    </div>
                    <div>
                        <a href="{{ route('school-cycles.index') }}" class="btn btn-secondary">Volver</a>
                        <a href="{{ route('school-cycles.edit', $schoolCycle) }}" class="btn btn-warning">Editar</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="small-box bg-info">
                                <div class="inner">
                                    <h3>{{ $stats['groups'] }}</h3>
                                    <p>Grupos activos</p>
                                </div>
                                <div class="icon"><i class="fas fa-users"></i></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="small-box bg-primary">
                                <div class="inner">
                                    <h3>{{ $stats['partials'] }}</h3>
                                    <p>Parciales</p>
                                </div>
                                <div class="icon"><i class="fas fa-layer-group"></i></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="small-box bg-success">
                                <div class="inner">
                                    <h3>{{ $stats['schedules'] }}</h3>
                                    <p>Horarios</p>
                                </div>
                                <div class="icon"><i class="fas fa-calendar-alt"></i></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="small-box bg-secondary">
                                <div class="inner">
                                    <h3>{{ $stats['sessions'] }}</h3>
                                    <p>Sesiones</p>
                                </div>
                                <div class="icon"><i class="fas fa-clock"></i></div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-7">
                            <table class="table table-bordered">
                                <tbody>
                                    <tr>
                                        <th style="width: 220px;">Campus</th>
                                        <td>{{ $schoolCycle->campuses->pluck('name')->implode(', ') ?: ($schoolCycle->campus->name ?? 'Sin campus') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Modalidades</th>
                                        <td>{{ $schoolCycle->modalities->pluck('name')->implode(', ') ?: ($schoolCycle->modality->name ?? 'Sin modalidad') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Inicio</th>
                                        <td>{{ $schoolCycle->start_date?->format('d/m/Y') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Termino</th>
                                        <td>{{ $schoolCycle->end_date?->format('d/m/Y') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Estatus</th>
                                        <td>
                                            <span class="badge {{ $schoolCycle->is_active ? 'badge-success' : 'badge-secondary' }}">
                                                {{ $schoolCycle->is_active ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="col-lg-5">
                            <div class="list-group">
                                <a href="{{ route('school-cycles.partials.index', $schoolCycle) }}" class="list-group-item list-group-item-action">
                                    <i class="fas fa-layer-group mr-2"></i> Ver parciales
                                </a>
                                <a href="{{ route('coordination.cycle-planning.index', ['school_cycle_id' => $schoolCycle->id]) }}" class="list-group-item list-group-item-action">
                                    <i class="fas fa-th-large mr-2"></i> Editar grupos del ciclo
                                </a>
                                <a href="{{ route('imports.master-schedule.create') }}" class="list-group-item list-group-item-action">
                                    <i class="fas fa-file-excel mr-2"></i> Cargar horarios
                                </a>
                                <a href="{{ route('imports.cycle-students.create') }}" class="list-group-item list-group-item-action">
                                    <i class="fas fa-user-graduate mr-2"></i> Cargar alumnos
                                </a>
                            </div>
                        </div>
                    </div>

                    <h5 class="mt-4">Parciales</h5>
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>Orden</th>
                                <th>Nombre</th>
                                <th>Inicio</th>
                                <th>Termino</th>
                                <th>Activo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($schoolCycle->partials as $partial)
                                <tr>
                                    <td>{{ $partial->sort_order }}</td>
                                    <td>{{ $partial->name }}</td>
                                    <td>{{ $partial->start_date?->format('d/m/Y') }}</td>
                                    <td>{{ $partial->end_date?->format('d/m/Y') }}</td>
                                    <td>{{ $partial->is_active ? 'Si' : 'No' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No hay parciales registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
