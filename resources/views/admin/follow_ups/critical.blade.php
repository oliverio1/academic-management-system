@extends('layouts.app')

@section('title', 'Seguimientos críticos')

@section('content')
<div class="content px-3">
    <div class="clearfix"></div>
        <div class="row">
            <div class="col-md-12 mt-3">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-sm-6">
                                <h4>Seguimientos críticos</h4>
                                <p class="text-muted mb-0">
                                    Seguimientos abiertos sin respuesta docente por más de 7 días
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="table table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Alumno</th>
                                    <th>Tipo</th>
                                    <th>Antigüedad</th>
                                    <th class="text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($followUps as $followUp)
                                    <tr>
                                        <td>
                                            <strong>{{ $followUp->student->user->name }}</strong><br>
                                            <small class="text-muted">
                                                {{ $followUp->student->group->name ?? '' }}
                                            </small>
                                        </td>

                                        <td>
                                            <span class="badge badge-danger">
                                                {{ ucfirst($followUp->type) }}
                                            </span>
                                        </td>

                                        <td class="text-danger">
                                            {{ max(1, (int) $followUp->created_at->diffInDays(now())) }} días sin respuesta
                                        </td>

                                        <td class="text-center">
                                            <a href="{{ route('coordination.follow-ups.show', $followUp) }}"
                                            class="btn btn-sm btn-outline-danger">
                                                Revisar
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted p-4">
                                            No hay seguimientos críticos.
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
