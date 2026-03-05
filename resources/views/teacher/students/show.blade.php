
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
                            <div class="col-md-6">
                                <h4 class="mb-0">
                                    {{ $student->user->name }} - {{ $student->group->name }}
                                </h4>
                            </div>
                            <div class="col-md-6">
                                <a href="{{ url()->previous() }}"
                                    class="btn btn-secondary float-right">
                                    Volver
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        Datos generales
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-1">
                                            <strong>Matrícula:</strong> {{ $student->enrollment_number }}
                                        </p>
                                        <p class="mb-1">
                                            <strong>Estado:</strong>
                                            {{ $student->is_active ? 'Activo' : 'Inactivo' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
    
                            {{-- RESUMEN ACADÉMICO --}}
                            <div class="col-md-8">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <h4 class="mb-0">
                                                    {{ $student->user->name }}
                                                </h4>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm mb-0">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Materia</th>
                                                    <th class="text-center">Asistencia</th>
                                                    <th class="text-center">Actividades</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($summaryBySubject as $item)
                                                    <tr>
                                                        {{-- MATERIA --}}
                                                        <td>
                                                            <a href="{{ route('teacher.classes.sessions.index', $item['assignment_id']) }}"
                                                                class="font-weight-bold text-decoration-none">
                                                                {{ $item['subject'] }}
                                                            </a>
                                                        </td>

                                                        {{-- ASISTENCIA --}}
                                                        <td class="text-center">
                                                            @if($item['attendance']['total'] > 0)
                                                                {{ $item['attendance']['attended'] }}
                                                                /
                                                                {{ $item['attendance']['total'] }}
                                                                ·
                                                                <strong>
                                                                    {{ $item['attendance']['percent'] }}%
                                                                </strong>
                                                            @else
                                                                <span class="text-muted">—</span>
                                                            @endif
                                                        </td>

                                                        {{-- ACTIVIDADES --}}
                                                        <td class="text-center">
                                                            @if($item['activities']['total'] > 0)
                                                                {{ $item['activities']['delivered'] }}
                                                                /
                                                                {{ $item['activities']['total'] }}
                                                                @if($item['activities']['average'] !== null)
                                                                    ·
                                                                    <strong>
                                                                        {{ $item['activities']['average'] }}
                                                                    </strong>
                                                                @endif
                                                            @else
                                                                <span class="text-muted">—</span>
                                                            @endif
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
                </div>
            </div>
        </div>
    </div>
@endsection

@section('page_css')
@endsection

@section('page_scripts')
@endsection