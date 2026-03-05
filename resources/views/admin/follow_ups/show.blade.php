@extends('layouts.app')

@section('title', 'Detalle del seguimiento')

@section('content')
<div class="content px-3">
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">

                {{-- Encabezado --}}
                <div class="card-header">
                    <h4>Seguimiento del alumno</h4>
                    <p class="mb-1">
                        <strong>Alumno:</strong>
                        {{ $followUp->student->user->name }}
                    </p>
                    <p class="mb-1">
                        <strong>Grupo:</strong>
                        {{ $followUp->student->group->name }}
                    </p>
                    <p class="mb-0">
                        <strong>Progreso:</strong>
                        {{ $answered }} / {{ $total }} profesores respondieron
                        ({{ $progress }}%)
                    </p>
                </div>

                {{-- Cuerpo --}}
                <div class="card-body">

                    <table class="table table-bordered">
                        <tbody>

                        @foreach($followUp->teachers as $assignment)
                            @php
                                $response = $assignment->response;
                            @endphp

                            {{-- Cabecera del profesor --}}
                            <tr class="table-light">
                                <td colspan="2">
                                    <strong>
                                        {{ $assignment->teacher->user->name }}
                                    </strong>

                                    @if($assignment->answered_at)
                                        <span class="text-muted ms-2">
                                            respondió el
                                            {{ $assignment->answered_at->format('d/m/Y') }}
                                        </span>
                                    @else
                                        <span class="badge bg-secondary ms-2">
                                            Pendiente
                                        </span>
                                    @endif
                                </td>
                            </tr>

                            {{-- Encabezado de columnas --}}
                            <tr>
                                <th width="50%">Seguimiento académico</th>
                                <th width="50%">Seguimiento conductual</th>
                            </tr>

                            {{-- Contenido --}}
                            <tr>
                                <td>
                                    @if($response?->questionnaire['academic_performance'] ?? false)
                                        {{ $response->questionnaire['academic_performance'] }}
                                    @else
                                        <span class="text-muted">Sin respuesta</span>
                                    @endif
                                </td>

                                <td>
                                    @if($response?->questionnaire['behavioral_performance'] ?? false)
                                        {{ $response->questionnaire['behavioral_performance'] }}
                                    @else
                                        <span class="text-muted">Sin respuesta</span>
                                    @endif
                                </td>
                            </tr>

                            {{-- Comentarios adicionales --}}
                            @if($response?->comments)
                                <tr>
                                    <td colspan="2">
                                        <strong>Comentarios adicionales:</strong><br>
                                        {{ $response->comments }}
                                    </td>
                                </tr>
                            @endif

                        @endforeach

                        </tbody>
                    </table>

                </div>

            </div>
        </div>
    </div>
</div>
@endsection
