@extends('layouts.app')

@section('title', 'Inasistencia parcial')

@section('content')


<div class="content px-3">
    <div class="clearfix"></div>
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-12">
                            <h4>Alerta: Inasistencia parcial — {{ $modality->name }}</h4>
                            <p class="text-muted">Periodo evaluado: {{ $since->format('d M Y') }} — {{ $endDate->format('d M Y') }}</p>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @forelse ($results as $item)

                        @php
                            $student = $item['student'];
                            $days    = $item['days'];
                        @endphp

                        <div class="card mb-3">
                            <div class="card-header">
                                <strong>{{ $student->user->name }}</strong>
                                <span class="text-muted">
                                    · {{ $student->group->name ?? 'Sin grupo' }}
                                </span>
                            </div>

                            <div class="card-body">

                                <p class="mb-2">
                                    <strong>Días con inasistencia parcial:</strong>
                                    {{ count($days) }}
                                </p>

                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th class="text-center">Faltas</th>
                                            <th>Materias</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($days as $day)
                                            <tr>
                                                <td>
                                                    {{ \Carbon\Carbon::parse($day['date'])->format('d/m/Y') }}
                                                </td>
                                                <td class="text-center">
                                                    {{ $day['absent'] }} / {{ $day['total'] }}
                                                </td>
                                                <td>
                                                    {{ implode(', ', $day['subjects']->toArray()) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                            </div>
                        </div>

                        @empty
                        <div class="alert alert-success">
                            No se detectaron alumnos con inasistencias parciales.
                        </div>
                        @endforelse
                </div>
            </div>
        </div>
    </div>
</div>








@endsection
