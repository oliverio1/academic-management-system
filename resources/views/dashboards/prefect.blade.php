@extends('layouts.app')

@section('title', 'Dashboard Prefectura')

@section('content')
<div class="content px-3">
    <div class="clearfix"></div>
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Dashboard de prefectura</h3>
                    <small class="text-muted">Asistencia global del día: {{ $today->format('d/m/Y') }}</small>
                </div>
                <div class="card-body">
                    @if($groups->isEmpty())
                        <div class="alert alert-info mb-0">
                            No hay grupos activos en el ciclo/campus actual.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table data-datatable="true" class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Grupo</th>
                                        <th>Nivel</th>
                                        <th>Modalidad</th>
                                        <th>Estatus asistencia de hoy</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($groups as $group)
                                        <tr>
                                            <td>{{ $group->name }}</td>
                                            <td>{{ $group->level->name ?? '-' }}</td>
                                            <td>{{ $group->level->modality->name ?? '-' }}</td>
                                            <td>
                                                @if($group->attendance_registered_today)
                                                    <span class="badge badge-success">Registrada</span>
                                                @else
                                                    <span class="badge badge-warning">Pendiente</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('prefect.groups.attendance', $group) }}"
                                                   class="btn btn-sm {{ $group->attendance_registered_today ? 'btn-outline-success' : 'btn-primary' }}">
                                                    {{ $group->attendance_registered_today ? 'Editar asistencia' : 'Registrar asistencia' }}
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
