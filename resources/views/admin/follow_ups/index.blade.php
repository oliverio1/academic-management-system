@extends('layouts.app')

@section('title', 'Seguimiento de alumnos')

@section('content')
<div class="content px-3">
    <div class="clearfix"></div>
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-sm-6">
                        <h4>Seguimientos solicitados</h4>
                        </div>
                        <div class="col-sm-6">
                            <a href="{{ route('coordination.follow-ups.create') }}" class="btn btn-primary"><i class="fas fa-plus mr-1"></i>Nuevo seguimiento</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Alumno</th>
                                <th>Tipo</th>
                                <th>Solicitado</th>
                                <th>Progreso</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($followUps as $followUp)
                                @php
                                    $total = $followUp->teachers->count();
                                    $answered = $followUp->teachers->where('status', 'answered')->count();
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $followUp->student->user->name }}</strong><br>
                                        <small class="text-muted">
                                            {{ $followUp->student->group->name ?? '' }}
                                        </small>
                                    </td>

                                    <td>
                                        <span class="badge badge-info">
                                            {{ ucfirst($followUp->type) }}
                                        </span>
                                    </td>

                                    <td>
                                        {{ $followUp->created_at->format('d M Y') }}
                                    </td>

                                    <td>
                                        {{ $answered }} / {{ $total }} profesores
                                    </td>

                                    <td class="text-center">
                                        <a href="{{ route('coordination.follow-ups.show', $followUp) }}"
                                        class="btn btn-sm btn-outline-secondary">
                                            Ver
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted p-4">
                                        No hay seguimientos registrados.
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
