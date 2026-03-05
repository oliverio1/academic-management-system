@extends('layouts.app')

@section('title', 'Inasistencia total')

@section('content')

<div class="content px-3">
    <div class="clearfix"></div>
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-sm-6">
                            <h4>Reporte de inasistencias</h4>
                            <p class="text-muted">Periodo evaluado: {{ $since->format('d M Y') }} — {{ $endDate->format('d M Y') }}</p>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @if(empty($results))
                        <div class="alert alert-success">
                            No hay alumnos con inasistencia total en este periodo.
                        </div>
                    @else
                        <div class="card-body p-0">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Alumno</th>
                                        <th>Grupo</th>
                                        <th>Nivel</th>
                                        <th class="text-center">Días</th>
                                        <th>Periodo</th>
                                        <th class="text-center">Detalle</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($results as $item)
                                        @php
                                            $maxDays = collect($item['streaks'])
                                                ->map(fn ($s) => count($s))
                                                ->max();

                                            $longest = collect($item['streaks'])
                                                ->first(fn ($s) => count($s) === $maxDays);
                                        @endphp

                                        <tr>
                                            <td>
                                                <strong>{{ $item['student']->user->name }}</strong><br>
                                                <small class="text-muted">
                                                    Matrícula: {{ $item['student']->enrollment_number ?? '—' }}
                                                </small>
                                            </td>

                                            <td>{{ $item['student']->group->name }}</td>

                                            <td>{{ $item['student']->group->level->name }}</td>

                                            <td class="text-center">
                                                <span class="badge badge-danger badge-pill">
                                                    {{ $maxDays }}
                                                </span>
                                            </td>

                                            <td>
                                                {{ \Carbon\Carbon::parse($longest[0])->format('d M Y') }}
                                                —
                                                {{ \Carbon\Carbon::parse(end($longest))->format('d M Y') }}
                                            </td>

                                            <td class="text-center">
                                                <button
                                                    class="btn btn-sm btn-outline-secondary"
                                                    data-toggle="collapse"
                                                    data-target="#detail-{{ $item['student']->id }}"
                                                >
                                                    Ver
                                                </button>
                                            </td>
                                        </tr>

                                        {{-- Detalle colapsable --}}
                                        <tr class="collapse bg-light" id="detail-{{ $item['student']->id }}">
                                            <td colspan="7">
                                                @foreach($item['streaks'] as $streak)
                                                    <div class="mb-2">
                                                        <span class="badge badge-danger mr-2">
                                                            {{ count($streak) }} días
                                                        </span>
                                                        {{ collect($streak)
                                                            ->map(fn ($d) => \Carbon\Carbon::parse($d)->format('d M'))
                                                            ->implode(', ')
                                                        }}
                                                    </div>
                                                @endforeach
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
