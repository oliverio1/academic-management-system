
@extends('layouts.app')

@section('title', 'Detalle del estudiante')

@section('content')
    <div class="content px-3">
        <div class="clearfix"></div>
        <div class="row">
            <div class="col-md-12 mt-3">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-sm-6">
                                <h3>{{ $student->user->name }}</h3>
                                <p class="mb-1"><strong>Matrícula:</strong> {{ $student->enrollment_number }}</p>
                                <p class="mb-1"><strong>Grupo:</strong> {{ $student->group->name }}</p>
                                <p class="mb-3">
                                    <strong>Estatus:</strong>
                                    <span class="badge {{ $student->is_active ? 'bg-success' : 'bg-danger' }}">
                                        {{ $student->is_active ? 'Activo' : 'Baja' }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header">Promedios por parcial</div>
                                    <div class="card-body">
                                        @foreach($promediosPorParcial as $parcial => $prom)
                                            <p class="mb-1">
                                                Parcial {{ $parcial }}:
                                                <strong>{{ $prom }}</strong>
                                            </p>
                                        @endforeach
                                        <hr>
                                        <p class="mb-0">
                                            Promedio general:
                                            <strong>{{ $promedioGeneral }}</strong>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header">Asistencia</div>
                                    <div class="card-body">
                                        @foreach($asistenciaPorParcial as $parcial => $porcentaje)
                                            <p class="mb-1">
                                                Parcial {{ $parcial }}:
                                                <strong>{{ $porcentaje }}%</strong>
                                            </p>
                                        @endforeach
                                        <hr>
                                        <p class="mb-0">
                                            Asistencia general:
                                            <strong>{{ $asistenciaGeneral }}%</strong>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="col-md-12">
                            <div class="card mb-3">
                                <div class="card-header">
                                    Historial de grupos
                                </div>

                                <div class="card-body p-0">
                                    @if($student->groupHistories->isEmpty())
                                        <p class="p-3 mb-0 text-muted">
                                            No hay historial de cambios de grupo.
                                        </p>
                                    @else
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Grupo</th>
                                                    <th>Nivel</th>
                                                    <th>Desde</th>
                                                    <th>Hasta</th>
                                                    <th>Motivo</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($student->groupHistories->sortBy('start_date') as $history)
                                                    <tr>
                                                        <td>{{ $history->group->name }}</td>
                                                        <td>{{ $history->group->level->name }}</td>
                                                        <td>{{ $history->start_date->format('d/m/Y') }}</td>
                                                        <td>
                                                            {{ $history->end_date
                                                                ? $history->end_date->format('d/m/Y')
                                                                : 'Actual' }}
                                                        </td>
                                                        <td>{{ $history->reason }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @endif
                                </div>
                            </div>
                            @if($student->followUps->isNotEmpty())
                                <ul class="mb-0">
                                    @foreach($student->followUps as $follow)
                                        <li>
                                            {{ $follow->created_at->translatedFormat('d M Y') }} –
                                            {{ $follow->description }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


@endsection

@section('page_css')
@endsection

@section('page_scripts')
    <script>
        $(document).ready(function () {
            $('#modalities').DataTable({
                dom: '<"area-fluid"<"row"<"col"l><"col"B><"col"f>>>rtip',
                "columnDefs": [
                    { "type": "num", "targets": 0 }
                ],
                "order": [[ 0, "asc" ]],
                buttons: [
                    'excelHtml5',
                    'pdfHtml5'
                ],
                language: {
                    url: '/datatables.json'
                }
            });
        });
    </script>
@endsection