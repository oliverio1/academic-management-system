@extends('layouts.app')

@section('title', 'Grupos en alerta')

@section('content')
<div class="content px-3">
    <div class="clearfix"></div>
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h4>Reporte de grupos en alerta</h4>
                    <p class="text-muted mb-0">
                        Se muestran grupos con promedio general menor a 6 y asistencia menor a 80%.
                    </p>
                </div>
                <div class="card-body">
                    @if(empty($results))
                        <div class="alert alert-success mb-0">
                            No hay grupos en alerta con los criterios actuales.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Grupo</th>
                                        <th>Nivel</th>
                                        <th>Modalidad</th>
                                        <th class="text-center">Alumnos</th>
                                        <th class="text-center">Promedio general</th>
                                        <th class="text-center">% Asistencia</th>
                                        <th>Periodo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($results as $row)
                                        <tr>
                                            <td>{{ $row['group']->name }}</td>
                                            <td>{{ $row['level'] }}</td>
                                            <td>{{ $row['modality'] }}</td>
                                            <td class="text-center">{{ $row['students_count'] }}</td>
                                            <td class="text-center">
                                                <span class="badge badge-danger">{{ number_format((float) $row['average'], 1) }}</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-warning">{{ number_format((float) $row['attendance'], 0) }}%</span>
                                            </td>
                                            <td>{{ $row['period'] }}</td>
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
