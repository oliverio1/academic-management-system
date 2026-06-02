@extends('layouts.app')

@section('title', 'Mis seguimientos')

@section('content')
<div class="content px-3">
    <div class="clearfix"></div>
    <div class="row">
        <div class="col-md-12 mt-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Mis seguimientos</h3>
                </div>
                <div class="card-body table-responsive p-3">
                <table data-datatable="true" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Solicitado por</th>
                            <th>Mensaje</th>
                            <th>Progreso</th>
                            <th>Estatus</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($followUps as $followUp)
                            @php
                                $totalTeachers = $followUp->teachers->count();
                                $answeredTeachers = $followUp->teachers->where('status', 'answered')->count();
                            @endphp
                            <tr>
                                <td>{{ $followUp->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $followUp->requester->name ?? 'N/D' }}</td>
                                <td style="max-width: 360px; white-space: normal;">{{ $followUp->message ?: '-' }}</td>
                                <td>{{ $answeredTeachers }} / {{ $totalTeachers }} docentes</td>
                                <td>
                                    @if($followUp->status === 'open')
                                        <span class="badge badge-warning">En proceso</span>
                                    @else
                                        <span class="badge badge-success">Cerrado</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No tienes seguimientos registrados.</td>
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


