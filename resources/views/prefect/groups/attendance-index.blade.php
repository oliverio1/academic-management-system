@extends('layouts.app')

@section('title', 'Asistencia global')

@section('content')
<div class="content px-3">
    <div class="clearfix"></div>
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0">Asistencia global - {{ $group->name }}</h3>
                        <small class="text-muted">
                            Seleccione el dia para registrar o editar la asistencia del grupo.
                            @if($cycle)
                                Ciclo: {{ $cycle->name }}
                            @endif
                        </small>
                    </div>
                    <a href="{{ route('prefect.groups.index') }}" class="btn btn-outline-secondary">Volver</a>
                </div>
                <div class="card-body table-responsive p-3">
                <table data-datatable="true" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Fecha</th>
                            <th>Dia</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['num'] }}</td>
                                <td>{{ $row['date']->format('d/m/Y') }}</td>
                                <td>{{ $row['date']->translatedFormat('l') }}</td>
                                <td class="text-center">
                                    @if($row['is_registered'])
                                        <span class="badge badge-success">Registrada</span>
                                    @else
                                        <span class="badge badge-warning">Pendiente</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($row['can_register'])
                                        <a href="{{ route('prefect.groups.attendance.day', ['group' => $group->id, 'date' => $row['date']->toDateString()]) }}"
                                           class="btn btn-sm {{ $row['is_registered'] ? 'btn-outline-success' : 'btn-warning' }}">
                                            {{ $row['is_registered'] ? 'Editar' : 'Registrar' }}
                                        </a>
                                    @else
                                        <button type="button"
                                                class="btn btn-sm btn-outline-secondary"
                                                disabled
                                                title="Disponible desde las 07:00 del {{ $row['date']->format('d/m/Y') }}">
                                            Bloqueado
                                        </button>
                                        <div class="small text-muted mt-1">Disponible 07:00</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    No hay sesiones registradas para este grupo en el ciclo actual.
                                </td>
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



