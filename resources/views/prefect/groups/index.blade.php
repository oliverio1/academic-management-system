@extends('layouts.app')

@section('title', 'Grupos')

@section('content')
<div class="content px-3">
    <div class="clearfix"></div>
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Grupos</h3>
                </div>
                <div class="card-body table-responsive p-3">
                <table data-datatable="true" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Grupo</th>
                            <th>Nivel</th>
                            <th>Modalidad</th>
                            <th>Estatus</th>
                            <th>Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($groups as $group)
                            <tr>
                                <td>{{ $group->name }}</td>
                                <td>{{ $group->level->name ?? '-' }}</td>
                                <td>{{ $group->level->modality->name ?? '-' }}</td>
                                <td>
                                    @if($group->is_active)
                                        <span class="badge badge-success">Activo</span>
                                    @else
                                        <span class="badge badge-secondary">Inactivo</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('prefect.groups.attendance', $group) }}" class="btn btn-sm btn-primary">
                                        Registrar asistencia del dia
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No hay grupos registrados.</td>
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


