
@extends('layouts.app')

@section('title', 'Modalidades')

@section('content')
    @if(session('info'))
        <div class="alert alert-primary" role="alert">
            <strong>{{ session('info') }}</strong>
        </div>    
    @endif
    <div class="content px-3">
        <div class="clearfix"></div>
        <div class="row">
            <div class="col-md-12 mt-3">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-sm-6">
                                <h4 class="mb-0">
                                    Grupo {{ $group->name }}
                                </h4>
                            </div>
                            <div class="col-sm-6">
                                <a href="{{ route('teacher.students.index') }}" class="btn btn-secondary float-right">Volver</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Alumno</th>
                                    <th class="text-center">Asistencia</th>
                                    <th class="text-center">Actividades</th>
                                    <th class="text-center">Detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($students as $student)
                                    <tr>
                                        <td>
                                            {{ $student->user->name }}
                                        </td>

                                        {{-- Asistencia (placeholder por ahora) --}}
                                        <td class="text-center">
                                            @php
                                                $stats = $attendanceStats[$student->id] ?? null;
                                            @endphp

                                            @if($stats && $stats->total > 0)
                                                {{ round(($stats->attended / $stats->total) * 100) }}%
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>

                                        {{-- Actividades (placeholder por ahora) --}}

                                        <td class="text-center">
                                            @php
                                                $delivered = $activityStats[$student->id]->delivered ?? 0;
                                            @endphp

                                            {{ $delivered }} / {{ $totalActivities }}
                                        </td>

                                        <td class="text-center">
                                            <a href="{{ route('teacher.students.show', $student) }}"
                                            class="btn btn-outline-secondary btn-sm">
                                                Ver
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>


@endsection

@section('page_css')
@endsection

@section('page_scripts')
@endsection