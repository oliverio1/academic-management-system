@extends('layouts.app')

@section('title', 'Registro docente bajo')

@section('content')
<div class="content px-3">
    <div class="clearfix"></div>
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4>Reporte de registro docente bajo</h4>
                    <p class="text-muted mb-0">
                        Se consideran sesiones anteriores al {{ $graceLimitDate->format('d/m/Y') }} (mas de 7 dias de atraso).
                        @if($activePeriod)
                            Periodo activo: {{ $activePeriod->name }}.
                        @endif
                    </p>
                </div>

                <div class="card-body">
                    @if($results->isEmpty())
                        <div class="alert alert-success mb-0">
                            No hay profesores con retrasos de registro mayores a 7 dias.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Profesor</th>
                                        <th class="text-center">Sesiones atrasadas</th>
                                        <th class="text-center">Sin asistencia</th>
                                        <th class="text-center">Sin actividad</th>
                                        <th>Materias / Grupos</th>
                                        <th class="text-center">Detalle</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($results as $index => $row)
                                        <tr>
                                            <td>{{ $row['teacher_name'] }}</td>
                                            <td class="text-center">
                                                <span class="badge badge-danger">{{ $row['late_sessions'] }}</span>
                                            </td>
                                            <td class="text-center">{{ $row['missing_attendance_sessions'] }}</td>
                                            <td class="text-center">{{ $row['missing_activity_sessions'] }}</td>
                                            <td>{{ implode(', ', $row['subjects']) }}</td>
                                            <td class="text-center">
                                                <button
                                                    class="btn btn-sm btn-outline-secondary"
                                                    data-toggle="collapse"
                                                    data-target="#detail-{{ $index }}"
                                                >
                                                    Ver
                                                </button>
                                            </td>
                                        </tr>
                                        <tr class="collapse bg-light" id="detail-{{ $index }}">
                                            <td colspan="6">
                                                <table class="table table-sm table-bordered mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Fecha</th>
                                                            <th>Materia</th>
                                                            <th>Grupo</th>
                                                            <th class="text-center">Asistencia</th>
                                                            <th class="text-center">Actividad</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($row['details'] as $detail)
                                                            <tr>
                                                                <td>{{ optional($detail['date'])->format('d/m/Y') }}</td>
                                                                <td>{{ $detail['subject'] }}</td>
                                                                <td>{{ $detail['group'] }}</td>
                                                                <td class="text-center">
                                                                    @if($detail['missing_attendance'])
                                                                        <span class="badge badge-danger">Pendiente</span>
                                                                    @else
                                                                        <span class="badge badge-success">Registrada</span>
                                                                    @endif
                                                                </td>
                                                                <td class="text-center">
                                                                    @if($detail['missing_activity'])
                                                                        <span class="badge badge-danger">Pendiente</span>
                                                                    @else
                                                                        <span class="badge badge-success">Registrada</span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
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
