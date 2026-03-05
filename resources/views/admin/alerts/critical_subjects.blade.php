@extends('layouts.app')

@section('title', 'Materias en riesgo')

@section('content')
<div class="content px-3">
    <div class="clearfix"></div>
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-sm-6">
                            <h4>Alerta: Materias en riesgo — {{ $modality->name }}</h4>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @if(empty($results))
                        <div class="alert alert-success">
                            No hay alumnos con materias en riesgo en este periodo.
                        </div>
                    @else
                        @foreach($results as $item)
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-user-times text-danger mr-2"></i>
                                        Alumnos con materias en riesgo
                                    </h5>
                                </div>

                                <div class="card-body p-0">
                                    @foreach($item['subjects'] as $subject)
                                        <div class="mb-2">
                                            <strong>{{ $subject['subject']->name }}</strong>
                                            <span class="badge badge-danger ml-2">
                                                {{ $subject['final'] }}
                                            </span>

                                            <table class="table table-sm table-bordered mb-1">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th>Criterio</th>
                                                        <th class="text-center">Promedio</th>
                                                        <th class="text-center">%</th>
                                                        <th class="text-center">Aporta</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($subject['breakdown'] as $row)
                                                        <tr
                                                            @if($row['average'] < 6)
                                                                class="table-danger"
                                                            @endif
                                                        >
                                                            <td>
                                                                {{ $row['criterion'] }}

                                                                @if(strtolower($row['criterion']) === 'asistencia')
                                                                    <span class="badge badge-danger ml-1">
                                                                        Asistencia
                                                                    </span>
                                                                @endif
                                                            </td>

                                                            <td class="text-center">
                                                                {{ $row['average'] }}
                                                            </td>

                                                            <td class="text-center">
                                                                {{ $row['percentage'] }} %
                                                            </td>

                                                            <td class="text-center font-weight-bold">
                                                                {{ $row['contribution'] }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
